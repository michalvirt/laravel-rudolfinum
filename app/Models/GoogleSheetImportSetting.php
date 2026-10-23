<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Cron\CronExpression;
use Database\Factories\GoogleSheetImportSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The settings of one saved Google Sheets import (connection, tab, rows, year and schedule).
 *
 * @property string $id
 * @property string|null $api_key
 * @property string|null $spreadsheet_id
 * @property list<string>|null $sheet_names
 * @property string|null $sheet_name
 * @property int $first_row
 * @property int $last_row
 * @property int|null $year
 * @property bool $auto_import
 * @property string $cron_expression
 * @property Carbon|null $last_imported_at
 * @property ImportStatus|null $last_import_status
 * @property string|null $last_import_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'api_key', 'spreadsheet_id', 'sheet_names', 'sheet_name', 'first_row', 'last_row', 'year',
    'auto_import', 'cron_expression', 'last_imported_at', 'last_import_status', 'last_import_message',
])]
#[Hidden(['api_key'])]
class GoogleSheetImportSetting extends Model
{
    /** @use HasFactory<GoogleSheetImportSettingFactory> */
    use HasFactory, HasUuids;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'first_row' => 15,
        'last_row' => 40,
        'auto_import' => false,
        'cron_expression' => '0 * * * *',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'sheet_names' => 'array',
            'auto_import' => 'boolean',
            'last_imported_at' => 'datetime',
            'last_import_status' => ImportStatus::class,
        ];
    }

    /**
     * Pick the API key to use: the submitted one, the saved one or the default from the configuration.
     */
    public static function effectiveApiKey(?string $submitted, ?self $setting = null): ?string
    {
        foreach ([$submitted, $setting?->api_key, config('services.google_sheets.api_key')] as $apiKey) {
            if (is_string($apiKey) && $apiKey !== '') {
                return $apiKey;
            }
        }

        return null;
    }

    /**
     * The rows imported from the tab of this import.
     *
     * @return Builder<GoogleSheetImport>
     */
    public function importedRows(): Builder
    {
        return GoogleSheetImport::query()
            ->where('spreadsheet_id', $this->spreadsheet_id)
            ->where('sheet_name', $this->sheet_name);
    }

    /**
     * Determine whether everything needed to read the sheet is set.
     */
    public function isReadyToImport(): bool
    {
        return filled($this->api_key)
            && filled($this->spreadsheet_id)
            && filled($this->sheet_name)
            && $this->year !== null;
    }

    /**
     * The A1 range of the configured tab and rows (columns A to J).
     */
    public function range(): string
    {
        $sheetName = str_replace("'", "''", (string) $this->sheet_name);

        return "'{$sheetName}'!A{$this->first_row}:J{$this->last_row}";
    }

    /**
     * Determine whether the automatic import should run at the given moment.
     */
    public function isImportDue(?Carbon $at = null): bool
    {
        return $this->auto_import
            && CronExpression::isValidExpression($this->cron_expression)
            && (new CronExpression($this->cron_expression))->isDue($at ?? now());
    }
}
