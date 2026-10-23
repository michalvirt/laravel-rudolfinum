<?php

namespace Database\Factories;

use App\Models\GoogleSheetImport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleSheetImport>
 */
class GoogleSheetImportFactory extends Factory
{
    private static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spreadsheet_id' => 'sheet-id',
            'sheet_name' => 'Listopad',
            'row_number' => fake()->numberBetween(15, 40),
            'date' => CarbonImmutable::parse('2026-01-01')->addDays(self::$sequence++)->toDateString(),
            'weekday' => fake()->randomElement(['po', 'út', 'st', 'čt', 'pá', 'so', 'ne']),
            'service' => 'služba',
            'name' => '19:30-22 '.fake()->sentence(2),
            'comment' => null,
            'operator_1' => fake()->name(),
            'operator_2' => null,
            'htl_1' => null,
            'htl_2' => null,
            'changed_fields' => null,
            'last_checked_at' => now(),
        ];
    }

    /**
     * Indicate that the sheet now differs from the accepted values.
     *
     * @param  array<string, string|null>  $changedFields
     */
    public function changed(array $changedFields = ['name' => 'Changed event']): static
    {
        return $this->state(fn (array $attributes) => ['changed_fields' => $changedFields]);
    }
}
