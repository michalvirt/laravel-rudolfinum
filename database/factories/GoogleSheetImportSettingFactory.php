<?php

namespace Database\Factories;

use App\Models\GoogleSheetImportSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleSheetImportSetting>
 */
class GoogleSheetImportSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_key' => 'test-key',
            'spreadsheet_id' => 'sheet-id',
            'sheet_names' => ['Říjen', 'Listopad'],
            'sheet_name' => 'Listopad',
            'first_row' => 15,
            'last_row' => 40,
            'year' => 2026,
            'auto_import' => false,
            'cron_expression' => '0 * * * *',
        ];
    }

    /**
     * Indicate that the import runs automatically.
     */
    public function automatic(string $cronExpression = '0 * * * *'): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_import' => true,
            'cron_expression' => $cronExpression,
        ]);
    }
}
