<?php

use App\Models\PermissionGroup;
use App\Models\PlanAvailability;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-22 12:00:00');
    $this->viewer = User::factory()->for(PermissionGroup::factory()->planners())->create();
});

test('it redirects guests to the login form', function () {
    $this->put(route('plan-calendar-view.availability'), ['date' => '2026-11-03', 'available' => true])
        ->assertRedirect(route('login'));

    expect(PlanAvailability::count())->toBe(0);
});

test('it forbids users who cannot view the plan calendar', function () {
    $user = User::factory()->for(PermissionGroup::factory()->viewers())->create();

    $this->actingAs($user)
        ->put(route('plan-calendar-view.availability'), ['date' => '2026-11-03', 'available' => true])
        ->assertForbidden();

    expect(PlanAvailability::count())->toBe(0);
});

test('it saves a day the logged in user has time to work', function () {
    $this->actingAs($this->viewer)
        ->put(route('plan-calendar-view.availability'), ['date' => '2026-11-03', 'available' => true])
        ->assertRedirect();

    $availability = PlanAvailability::sole();

    expect($availability->user_id)->toBe($this->viewer->id)
        ->and($availability->date->toDateString())->toBe('2026-11-03');
});

test('it keeps the original timestamp when a selected day is selected again', function () {
    $this->travelTo('2026-10-20 08:00:00');
    $this->actingAs($this->viewer)
        ->put(route('plan-calendar-view.availability'), ['date' => '2026-11-03', 'available' => true]);

    $this->travelTo('2026-10-21 09:00:00');
    $this->put(route('plan-calendar-view.availability'), ['date' => '2026-11-03', 'available' => true]);

    expect(PlanAvailability::sole()->created_at->toDateTimeString())->toBe('2026-10-20 08:00:00');
});

test('it clears a day only for the logged in user', function () {
    $colleague = User::factory()->create();
    PlanAvailability::factory()->for($this->viewer)->create(['date' => '2026-11-03']);
    PlanAvailability::factory()->for($this->viewer)->create(['date' => '2026-11-04']);
    PlanAvailability::factory()->for($colleague)->create(['date' => '2026-11-03']);

    $this->actingAs($this->viewer)
        ->put(route('plan-calendar-view.availability'), ['date' => '2026-11-03', 'available' => false])
        ->assertRedirect();

    expect(PlanAvailability::count())->toBe(2)
        ->and(PlanAvailability::where('user_id', $colleague->id)->count())->toBe(1)
        ->and(PlanAvailability::where('user_id', $this->viewer->id)->count())->toBe(1);
});

test('it rejects days of the current and the past months', function (string $date) {
    $this->actingAs($this->viewer)
        ->put(route('plan-calendar-view.availability'), ['date' => $date, 'available' => true])
        ->assertSessionHasErrors('date');

    expect(PlanAvailability::count())->toBe(0);
})->with(['past month' => '2026-09-30', 'today' => '2026-10-22', 'end of the current month' => '2026-10-31']);

test('it accepts the first day of the next month', function () {
    $this->actingAs($this->viewer)
        ->put(route('plan-calendar-view.availability'), ['date' => '2026-11-01', 'available' => true])
        ->assertSessionHasNoErrors();

    expect(PlanAvailability::count())->toBe(1);
});

test('it validates the date and the flag', function (array $input, string $field) {
    $this->actingAs($this->viewer)
        ->put(route('plan-calendar-view.availability'), $input)
        ->assertSessionHasErrors($field);

    expect(PlanAvailability::count())->toBe(0);
})->with([
    'missing date' => [['available' => true], 'date'],
    'malformed date' => [['date' => '3.11.2026', 'available' => true], 'date'],
    'missing flag' => [['date' => '2026-11-03'], 'available'],
    'invalid flag' => [['date' => '2026-11-03', 'available' => 'maybe'], 'available'],
]);

test('it lists on every day the users who have time, the first to select it first', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $third = User::factory()->create();

    $this->travelTo('2026-10-20 08:00:00');
    PlanAvailability::factory()->for($second)->create(['date' => '2026-11-03']);
    $this->travelTo('2026-10-20 07:00:00');
    PlanAvailability::factory()->for($first)->create(['date' => '2026-11-03']);
    $this->travelTo('2026-10-20 09:00:00');
    PlanAvailability::factory()->for($third)->create(['date' => '2026-11-03']);
    PlanAvailability::factory()->for($third)->create(['date' => '2026-12-01']);

    $this->actingAs($this->viewer)
        ->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.2.availableUserIds', [$first->id, $second->id, $third->id])
            ->where('days.0.availableUserIds', [])
        );
});

test('it shows the planner who has time on the edit page', function () {
    $colleague = User::factory()->create();
    PlanAvailability::factory()->for($colleague)->create(['date' => '2026-11-03']);

    $this->actingAs($this->viewer)
        ->get(route('plan-calendar-edit', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.2.availableUserIds', [$colleague->id])
        );
});

test('it deletes the days of a deleted user', function () {
    $user = User::factory()->create();
    PlanAvailability::factory()->for($user)->create();

    $user->delete();

    expect(PlanAvailability::count())->toBe(0);
});
