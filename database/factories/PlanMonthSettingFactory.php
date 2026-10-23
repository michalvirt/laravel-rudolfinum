<?php

namespace Database\Factories;

use App\Models\PlanMonthSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanMonthSetting>
 */
class PlanMonthSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => fake()->dateTimeBetween('-1 year', '+1 year')->format('Y-m'),
            'staff_visible' => false,
        ];
    }

    /**
     * Indicate that the operators and engineers of the month are shown in the plan calendar view.
     */
    public function staffShown(): static
    {
        return $this->state(fn (array $attributes) => [
            'staff_visible' => true,
        ]);
    }
}
