<?php

use App\Models\PermissionGroup;
use App\Models\PlanEvent;
use App\Models\PlanMonthSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->planner = User::factory()->for(PermissionGroup::factory()->planners())->create();
    $this->operator = User::factory()->create();
    PlanEvent::factory()->create(['date' => '2026-11-03', 'operator_1' => $this->operator->id]);
});

test('it redirects guests to the login form', function () {
    $this->put(route('plan-calendar-edit.staff-visibility'), ['month' => '2026-11', 'visible' => true])
        ->assertRedirect(route('login'));

    expect(PlanMonthSetting::count())->toBe(0);
});

test('it forbids users who cannot edit the plan calendar', function () {
    $this->actingAs(User::factory()->for(PermissionGroup::factory()->viewers())->create())
        ->put(route('plan-calendar-edit.staff-visibility'), ['month' => '2026-11', 'visible' => true])
        ->assertForbidden();

    expect(PlanMonthSetting::count())->toBe(0);
});

test('it hides the operators and engineers of every month in the view by default', function () {
    $this->actingAs($this->planner)
        ->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('staffVisible', false)
            ->where('days.2.events.0.assignments.operator_1', null)
        );
});

test('it keeps showing the operators and engineers on the edit page of a hidden month', function () {
    $this->actingAs($this->planner)
        ->get(route('plan-calendar-edit', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('staffVisible', false)
            ->where('days.2.events.0.assignments.operator_1', $this->operator->id)
        );
});

test('it shows the operators and engineers of a month in the view only', function () {
    $this->actingAs($this->planner)
        ->put(route('plan-calendar-edit.staff-visibility'), ['month' => '2026-11', 'visible' => true])
        ->assertRedirect();

    expect(PlanMonthSetting::sole())
        ->month->toBe('2026-11')
        ->staff_visible->toBeTrue();

    $this->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('staffVisible', true)
            ->where('days.2.events.0.assignments.operator_1', $this->operator->id)
        );

    $this->get(route('plan-calendar-view', ['month' => '2026-12']))
        ->assertInertia(fn (Assert $page) => $page->where('staffVisible', false));
});

test('it hides the operators and engineers of a month again', function () {
    PlanMonthSetting::factory()->staffShown()->create(['month' => '2026-11']);

    $this->actingAs($this->planner)
        ->put(route('plan-calendar-edit.staff-visibility'), ['month' => '2026-11', 'visible' => false])
        ->assertRedirect();

    expect(PlanMonthSetting::sole()->staff_visible)->toBeFalse();

    $this->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('staffVisible', false)
            ->where('days.2.events.0.assignments.operator_1', null)
        );
});

test('it validates the month and the flag', function (array $input, string $field) {
    $this->actingAs($this->planner)
        ->put(route('plan-calendar-edit.staff-visibility'), $input)
        ->assertSessionHasErrors($field);

    expect(PlanMonthSetting::count())->toBe(0);
})->with([
    'missing month' => [['visible' => true], 'month'],
    'malformed month' => [['month' => '11/2026', 'visible' => true], 'month'],
    'missing flag' => [['month' => '2026-11'], 'visible'],
    'invalid flag' => [['month' => '2026-11', 'visible' => 'maybe'], 'visible'],
]);
