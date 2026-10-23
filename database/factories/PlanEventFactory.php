<?php

namespace Database\Factories;

use App\Models\PlanEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanEvent>
 */
class PlanEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'name' => '19:30-22 '.fake()->sentence(2),
            'show_original_text' => true,
            'original_text_changed' => false,
            'google_sheet_import_id' => null,
        ];
    }
}
