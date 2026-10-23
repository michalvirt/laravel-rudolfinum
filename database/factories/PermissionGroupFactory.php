<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Models\PermissionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermissionGroup>
 */
class PermissionGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'permissions' => [],
        ];
    }

    /**
     * Indicate that the group grants every permission.
     */
    public function administrators(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Administrators',
            'permissions' => Permission::cases(),
        ]);
    }

    /**
     * Indicate that the group may view and edit the plan calendar only.
     */
    public function planners(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Planners',
            'permissions' => [Permission::ViewCalendar, Permission::ViewPlanCalendar, Permission::EditPlanCalendar, Permission::ReceivePlanEventChangeNotification],
        ]);
    }

    /**
     * Indicate that the group may view the calendar only.
     */
    public function viewers(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Viewers',
            'permissions' => [Permission::ViewCalendar, Permission::ViewPlanCalendar, Permission::ReceivePlanEventChangeNotification],
        ]);
    }
}
