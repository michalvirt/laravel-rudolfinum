<?php

namespace Database\Factories;

use App\Models\PlanEvent;
use App\Models\PlanEventCategory;
use App\Models\PlanEventObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanEventObject>
 */
class PlanEventObjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_event_id' => PlanEvent::factory(),
            'plan_event_category_id' => PlanEventCategory::factory(),
            'text' => fake()->words(2, true),
        ];
    }
}
