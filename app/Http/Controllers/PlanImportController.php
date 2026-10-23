<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePlanImportRequest;
use App\Models\GoogleSheetImport;
use App\Models\GoogleSheetImportSetting;
use App\Services\GoogleSheetImporter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlanImportController extends Controller
{
    /**
     * List every saved import with its settings and the rows of the selected one.
     */
    public function index(Request $request): Response
    {
        $defaultSetting = new GoogleSheetImportSetting;
        $settings = GoogleSheetImportSetting::orderBy('created_at')->get();
        $selected = $settings->firstWhere('id', $request->query('import')) ?? $settings->first();

        $changedCounts = GoogleSheetImport::whereNotNull('changed_fields')
            ->selectRaw('spreadsheet_id, sheet_name, count(*) as total')
            ->groupBy('spreadsheet_id', 'sheet_name')
            ->get()
            ->mapWithKeys(fn (GoogleSheetImport $row): array => [
                "{$row->spreadsheet_id}|{$row->sheet_name}" => (int) $row->getAttribute('total'),
            ]);

        return Inertia::render('plan-import', [
            'imports' => $settings
                ->map(fn (GoogleSheetImportSetting $setting): array => [
                    'id' => $setting->id,
                    'hasApiKey' => filled($setting->api_key),
                    'spreadsheet_id' => $setting->spreadsheet_id,
                    'sheet_names' => $setting->sheet_names ?? [],
                    'sheet_name' => $setting->sheet_name,
                    'first_row' => $setting->first_row,
                    'last_row' => $setting->last_row,
                    'year' => $setting->year,
                    'auto_import' => $setting->auto_import,
                    'cron_expression' => $setting->cron_expression,
                    'last_imported_at' => $setting->last_imported_at?->toIso8601String(),
                    'last_import_status' => $setting->last_import_status?->value,
                    'last_import_message' => $setting->last_import_message,
                    'changedCount' => $changedCounts->get("{$setting->spreadsheet_id}|{$setting->sheet_name}", 0),
                ])
                ->all(),
            'selectedId' => $selected?->id,
            'rows' => $selected === null
                ? []
                : $selected->importedRows()
                    ->orderBy('date')
                    ->get()
                    ->map(fn (GoogleSheetImport $import): array => [
                        'id' => $import->id,
                        'row_number' => $import->row_number,
                        'date' => $import->date->toDateString(),
                        'weekday' => $import->weekday,
                        'service' => $import->service,
                        'name' => $import->name,
                        'comment' => $import->comment,
                        'operator_1' => $import->operator_1,
                        'operator_2' => $import->operator_2,
                        'htl_1' => $import->htl_1,
                        'htl_2' => $import->htl_2,
                        'changed_fields' => $import->changed_fields ?? (object) [],
                        'hasChanges' => $import->hasPendingChanges(),
                    ])
                    ->all(),
            'defaults' => [
                'hasApiKey' => filled(config('services.google_sheets.api_key')),
                'spreadsheet_id' => (string) config('services.google_sheets.spreadsheet_id'),
                'first_row' => $defaultSetting->first_row,
                'last_row' => $defaultSetting->last_row,
                'year' => (int) CarbonImmutable::today()->format('Y'),
                'cron_expression' => $defaultSetting->cron_expression,
            ],
        ]);
    }

    /**
     * Save a new import.
     */
    public function store(SavePlanImportRequest $request, GoogleSheetImporter $importer): RedirectResponse
    {
        $setting = $this->save(new GoogleSheetImportSetting, $request, $importer);

        Inertia::flash('success', "Import of {$setting->sheet_name} was created. Run it to load the rows.");

        return redirect()->route('plan-import.index', ['import' => $setting->id]);
    }

    /**
     * Update the settings of a saved import.
     */
    public function update(SavePlanImportRequest $request, GoogleSheetImportSetting $googleSheetImportSetting, GoogleSheetImporter $importer): RedirectResponse
    {
        $this->save($googleSheetImportSetting, $request, $importer);

        Inertia::flash('success', "Import of {$googleSheetImportSetting->sheet_name} was updated.");

        return redirect()->route('plan-import.index', ['import' => $googleSheetImportSetting->id]);
    }

    /**
     * Delete a saved import. The rows it imported and the plan calendar stay untouched.
     */
    public function destroy(GoogleSheetImportSetting $googleSheetImportSetting): RedirectResponse
    {
        $googleSheetImportSetting->delete();

        Inertia::flash('success', "Import of {$googleSheetImportSetting->sheet_name} was deleted.");

        return redirect()->route('plan-import.index');
    }

    /**
     * Check the connection and the tab, then store the settings.
     *
     * @throws ValidationException
     */
    private function save(GoogleSheetImportSetting $setting, SavePlanImportRequest $request, GoogleSheetImporter $importer): GoogleSheetImportSetting
    {
        $apiKey = (string) GoogleSheetImportSetting::effectiveApiKey(
            $request->string('api_key')->toString(),
            $setting->exists ? $setting : null,
        );

        $sheetNames = $importer->fetchSheetNames($apiKey, $request->string('spreadsheet_id')->toString());

        if (! in_array($request->string('sheet_name')->toString(), $sheetNames, true)) {
            throw ValidationException::withMessages([
                'sheet_name' => 'The tab does not exist in the spreadsheet.',
            ]);
        }

        $setting->fill([
            ...$request->safe()->except('api_key'),
            'api_key' => $apiKey,
            'sheet_names' => $sheetNames,
            'auto_import' => $request->boolean('auto_import'),
        ])->save();

        return $setting;
    }
}
