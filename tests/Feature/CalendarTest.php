<?php

use App\Models\PermissionGroup;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->for(PermissionGroup::factory()->administrators())->create());
});

test('it shows the current month by default', function () {
    $this->travelTo('2026-10-21');

    $this->get(route('calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar')
            ->where('month', '2026-10')
            ->where('previousMonth', '2026-09')
            ->where('nextMonth', '2026-11')
            ->where('currentMonth', '2026-10')
            ->has('weekdays', 7)
        );
});

test('it shows the requested month with full weeks starting on monday', function () {
    $this->get(route('calendar', ['month' => '2026-02']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('month', '2026-02')
            ->where('previousMonth', '2026-01')
            ->where('nextMonth', '2026-03')
            ->where('weekdays.0', 'Mon')
            ->has('weeks', 5)
            ->where('weeks.0.0.date', '2026-01-26')
            ->where('weeks.0.6.date', '2026-02-01')
            ->where('weeks.4.6.date', '2026-03-01')
        );
});

test('it navigates across year boundaries', function () {
    $this->get(route('calendar', ['month' => '2026-12']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('nextMonth', '2027-01')
            ->where('previousMonth', '2026-11')
        );
});

test('it marks today and days outside the month', function () {
    $this->travelTo('2026-10-21');

    $this->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('weeks.0.0.date', '2026-09-28')
            ->where('weeks.0.0.isCurrentMonth', false)
            ->where('weeks.3.2.date', '2026-10-21')
            ->where('weeks.3.2.isToday', true)
            ->where('weeks.3.2.isCurrentMonth', true)
        );
});

test('it rejects an invalid month', function () {
    $this->get(route('calendar', ['month' => 'invalid']))
        ->assertSessionHasErrors('month');
});
