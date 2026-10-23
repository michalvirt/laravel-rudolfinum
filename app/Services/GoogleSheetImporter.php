<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Models\GoogleSheetImport;
use App\Models\GoogleSheetImportSetting;
use App\Models\PlanEvent;
use App\Models\PlanMonthSetting;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class GoogleSheetImporter
{
    public function __construct(private readonly EmailNotifier $notifier) {}

    /**
     * The sheet column (zero based) holding each imported field. Column A holds the date
     * and column H is not imported.
     *
     * @var array<string, int>
     */
    private const FIELD_COLUMNS = [
        'weekday' => 1,
        'service' => 2,
        'name' => 3,
        'comment' => 4,
        'operator_1' => 5,
        'operator_2' => 6,
        'htl_1' => 8,
        'htl_2' => 9,
    ];

    /**
     * The fields that make a row worth importing when it is not stored yet.
     *
     * @var list<string>
     */
    private const CONTENT_FIELDS = ['name', 'comment', 'operator_1', 'operator_2', 'htl_1', 'htl_2'];

    /**
     * Describe the outcome of an import in a sentence.
     *
     * @param  array{created: int, changed: int, unchanged: int, skippedRows: list<int>}  $result
     */
    public static function summarize(array $result): string
    {
        return "{$result['created']} new, {$result['changed']} changed, {$result['unchanged']} unchanged.";
    }

    /**
     * Get the spreadsheet id from a bare id or a pasted spreadsheet URL.
     */
    public static function extractSpreadsheetId(string $value): string
    {
        $value = trim($value);

        return preg_match('~/spreadsheets/d/([A-Za-z0-9_-]+)~', $value, $matches) === 1 ? $matches[1] : $value;
    }

    /**
     * List the titles of the tabs of a spreadsheet.
     *
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function fetchSheetNames(string $apiKey, string $spreadsheetId): array
    {
        $titles = $this->get($apiKey, "spreadsheets/{$spreadsheetId}", ['fields' => 'sheets.properties.title'])
            ->json('sheets.*.properties.title', []);

        return array_values(array_filter(array_map(strval(...), $titles), filled(...)));
    }

    /**
     * Import the configured tab and rows, then record the outcome on the settings.
     *
     * New rows are stored and copied to the plan calendar. Rows that are already stored are
     * compared with the sheet and flagged when they differ.
     *
     * @return array{created: int, changed: int, unchanged: int, skippedRows: list<int>}
     *
     * @throws ValidationException
     */
    public function import(GoogleSheetImportSetting $setting): array
    {
        try {
            $result = $this->runImport($setting);
        } catch (ValidationException $exception) {
            $setting->update([
                'last_imported_at' => now(),
                'last_import_status' => ImportStatus::Failed,
                'last_import_message' => implode(' ', array_merge(...array_values($exception->errors()))),
            ]);

            $this->notify(fn () => $this->notifier->notifyImportFailed($setting, (string) $setting->last_import_message));

            throw $exception;
        }

        $setting->update([
            'last_imported_at' => now(),
            'last_import_status' => ImportStatus::Success,
            'last_import_message' => self::summarize($result),
        ]);

        $this->notify(fn () => $this->notifier->notifyImportSucceeded($setting, $result));

        return $result;
    }

    /**
     * Accept the values of the sheet for a row and copy them to the plan calendar.
     */
    public function accept(GoogleSheetImport $import): void
    {
        if (! $import->hasPendingChanges()) {
            return;
        }

        $changes = collect($import->changed_fields)
            ->map(fn (?string $new, string $field): array => ['old' => $import->getAttribute($field), 'new' => $new])
            ->all();
        $eventName = $import->name;
        $assignedUserIds = $this->assignedUserIds($import);

        DB::transaction(function () use ($import): void {
            $nameChanged = array_key_exists('name', $import->changed_fields ?? []);

            $import->fill($import->changed_fields ?? []);
            $import->changed_fields = null;
            $import->save();

            $this->syncPlanEvent($import, $nameChanged);
        });

        if ($assignedUserIds !== [] && PlanMonthSetting::isStaffVisibleOn($import->date)) {
            $this->notify(fn () => $this->notifier->notifyPlanEventChanged(
                $import->date->toDateString(),
                $import->name ?? $eventName,
                $changes,
                $assignedUserIds,
            ));
        }
    }

    /**
     * Get the ids of the operators and engineers assigned to the plan event paired with a row.
     *
     * @return list<string>
     */
    private function assignedUserIds(GoogleSheetImport $import): array
    {
        $event = PlanEvent::where('google_sheet_import_id', $import->id)->first();

        if ($event === null) {
            return [];
        }

        $userIds = [];

        foreach (PlanEvent::ASSIGNMENT_COLUMNS as $column) {
            $userId = $event->getAttribute($column);

            if (is_string($userId) && ! in_array($userId, $userIds, true)) {
                $userIds[] = $userId;
            }
        }

        return $userIds;
    }

    /**
     * Create the email notifications about an import. A failing notification never fails the import.
     *
     * @param  Closure(): mixed  $notify
     */
    private function notify(Closure $notify): void
    {
        try {
            $notify();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array{created: int, changed: int, unchanged: int, skippedRows: list<int>}
     *
     * @throws ValidationException
     */
    private function runImport(GoogleSheetImportSetting $setting): array
    {
        if (! $setting->isReadyToImport()) {
            throw ValidationException::withMessages([
                'sheet' => 'Save the connection, the tab, the rows and the year before importing.',
            ]);
        }

        $values = $this->get(
            (string) $setting->api_key,
            "spreadsheets/{$setting->spreadsheet_id}/values/".rawurlencode($setting->range()),
        )->json('values', []);

        ['rows' => $rows, 'skippedRows' => $skippedRows] = $this->parseRows(
            array_values(array_map(
                fn (array $row): array => array_values(array_map(fn (mixed $cell): string => trim((string) $cell), $row)),
                $values,
            )),
            (int) $setting->year,
            $setting->first_row,
        );

        if ($rows === []) {
            throw ValidationException::withMessages([
                'sheet' => "The range {$setting->range()} does not contain any rows with a date.",
            ]);
        }

        $result = ['created' => 0, 'changed' => 0, 'unchanged' => 0, 'skippedRows' => $skippedRows];

        DB::transaction(function () use ($setting, $rows, &$result): void {
            foreach ($rows as $row) {
                $outcome = $this->importRow($setting, $row);

                if ($outcome !== null) {
                    $result[$outcome]++;
                }
            }
        });

        return $result;
    }

    /**
     * Store a new row or compare an existing one. Returns null when nothing was done.
     *
     * @param  array{row: int, date: string, attributes: array<string, string|null>}  $row
     * @return 'created'|'changed'|'unchanged'|null
     */
    private function importRow(GoogleSheetImportSetting $setting, array $row): ?string
    {
        $import = GoogleSheetImport::where('spreadsheet_id', $setting->spreadsheet_id)
            ->where('sheet_name', $setting->sheet_name)
            ->whereDate('date', $row['date'])
            ->first();

        if ($import === null) {
            $hasContent = collect(self::CONTENT_FIELDS)->contains(fn (string $field): bool => $row['attributes'][$field] !== null);

            if (! $hasContent) {
                return null;
            }

            $import = GoogleSheetImport::create([
                'spreadsheet_id' => $setting->spreadsheet_id,
                'sheet_name' => $setting->sheet_name,
                'row_number' => $row['row'],
                'date' => $row['date'],
                'last_checked_at' => now(),
                ...$row['attributes'],
            ]);

            $this->syncPlanEvent($import);

            return 'created';
        }

        $changes = [];

        foreach (GoogleSheetImport::COMPARED_FIELDS as $field) {
            if ($import->{$field} !== $row['attributes'][$field]) {
                $changes[$field] = $row['attributes'][$field];
            }
        }

        $import->update([
            'row_number' => $row['row'],
            'last_checked_at' => now(),
            'changed_fields' => $changes === [] ? null : $changes,
        ]);

        return $changes === [] ? 'unchanged' : 'changed';
    }

    /**
     * Copy the event name of an import to its paired plan calendar day. When the name was changed
     * by an accepted change, the original text is shown again and marked as changed,
     * so the assigned users are asked to confirm it again.
     */
    private function syncPlanEvent(GoogleSheetImport $import, bool $nameChanged = false): void
    {
        if ($import->name === null) {
            PlanEvent::where('google_sheet_import_id', $import->id)->delete();

            return;
        }

        $planEvent = PlanEvent::updateOrCreate(
            ['google_sheet_import_id' => $import->id],
            [
                'date' => $import->date->toDateString(),
                'name' => $import->name,
                ...($nameChanged ? ['show_original_text' => true, 'original_text_changed' => true] : []),
            ],
        );

        if ($nameChanged) {
            $planEvent->changeAcknowledgedBy()->detach();
        }
    }

    /**
     * Convert sheet rows into attributes. Column A holds the date (e.g. "1.11."), B the weekday,
     * C the service, D the event name, E the comment, F and G the operators and I and J the
     * HTL technicians.
     *
     * @param  list<list<string>>  $rows
     * @return array{rows: list<array{row: int, date: string, attributes: array<string, string|null>}>, skippedRows: list<int>}
     */
    private function parseRows(array $rows, int $year, int $firstRow): array
    {
        $parsed = [];
        $skippedRows = [];
        $seenDates = [];
        $previousMonth = null;

        foreach ($rows as $index => $row) {
            if (array_filter($row, filled(...)) === []) {
                continue;
            }

            $rowNumber = $firstRow + $index;
            $date = $this->parseDate($row[0] ?? '', $year, $previousMonth);

            if ($date === null || isset($seenDates[$date->toDateString()])) {
                $skippedRows[] = $rowNumber;

                continue;
            }

            $seenDates[$date->toDateString()] = true;
            $previousMonth = (int) $date->format('n');

            $parsed[] = [
                'row' => $rowNumber,
                'date' => $date->toDateString(),
                'attributes' => array_map(
                    fn (int $column): ?string => filled($row[$column] ?? null) ? $row[$column] : null,
                    self::FIELD_COLUMNS,
                ),
            ];
        }

        return ['rows' => $parsed, 'skippedRows' => $skippedRows];
    }

    /**
     * Parse a "d.m." or "d.m.Y" date, moving to the next year when the months wrap around.
     */
    private function parseDate(string $value, int &$year, ?int $previousMonth): ?CarbonImmutable
    {
        if (preg_match('/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})?$/', $value, $matches) !== 1) {
            return null;
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];

        if (isset($matches[3])) {
            $year = (int) $matches[3];
        } elseif ($previousMonth !== null && $month < $previousMonth) {
            $year++;
        }

        return checkdate($month, $day, $year)
            ? CarbonImmutable::create($year, $month, $day)->startOfDay()
            : null;
    }

    /**
     * Send a read-only request to the Google Sheets API.
     *
     * @param  array<string, string>  $query
     *
     * @throws ValidationException
     */
    private function get(string $apiKey, string $path, array $query = []): Response
    {
        try {
            return Http::baseUrl('https://sheets.googleapis.com/v4')
                ->withHeader('X-Goog-Api-Key', $apiKey)
                ->connectTimeout(3)
                ->timeout(10)
                ->retry([200, 500], 0, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->serverError() || $exception->response->status() === 429));
                })
                ->get($path, $query)
                ->throw();
        } catch (RequestException $exception) {
            $reason = $exception->response->json('error.message');

            throw ValidationException::withMessages([
                'sheet' => 'Google Sheets rejected the request (HTTP '.$exception->response->status().')'
                    .(is_string($reason) ? ": {$reason}" : '.')
                    .' Check the API key, the sheet id and that the sheet is shared with everyone who has the link.',
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'sheet' => 'Google Sheets could not be reached. Try again later.',
            ]);
        }
    }
}
