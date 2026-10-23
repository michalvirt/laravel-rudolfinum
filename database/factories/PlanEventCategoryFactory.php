<?php

namespace Database\Factories;

use App\Models\PlanEventCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanEventCategory>
 */
class PlanEventCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->word(),
            'background_color' => fake()->hexColor(),
            'font_color' => '#ffffff',
        ];
    }
}
