<?php

use App\Models\GoogleSheetImport;
use App\Models\PermissionGroup;
use App\Models\PlanEvent;
use App\Models\PlanEventCategory;
use App\Models\PlanEventObject;
use App\Models\PlanMonthSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->for(PermissionGroup::factory()->administrators())->create());
});

test('it lists every day of the current month by default', function () {
    $this->travelTo('2026-10-21');

    $this->get(route('plan-calendar-view'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plan-calendar-view')
            ->where('month', '2026-10')
            ->where('previousMonth', '2026-09')
            ->where('nextMonth', '2026-11')
            ->where('currentMonth', '2026-10')
            ->has('days', 31)
            ->where('days.0.date', '2026-10-01')
            ->where('days.30.date', '2026-10-31')
        );
});

test('it lists the days of the requested month', function () {
    $this->get(route('plan-calendar-view', ['month' => '2028-02']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('month', '2028-02')
            ->has('days', 29)
            ->where('days.0.weekday', 'Tuesday')
        );
});

test('it navigates across year boundaries', function () {
    $this->get(route('plan-calendar-view', ['month' => '2026-12']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('previousMonth', '2026-11')
            ->where('nextMonth', '2027-01')
        );
});

test('it marks today and weekends', function () {
    $this->travelTo('2026-10-21');

    $this->get(route('plan-calendar-view'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.20.isToday', true)
            ->where('days.0.isToday', false)
            ->where('days.1.isWeekend', false)
            ->where('days.2.isWeekend', true)
            ->where('days.3.isWeekend', true)
        );
});

test('it rejects an invalid month', function () {
    $this->get(route('plan-calendar-view', ['month' => 'invalid']))
        ->assertSessionHasErrors('month');
});

test('it shows the imported events on their days', function () {
    $import = GoogleSheetImport::factory()->create(['date' => '2026-11-10', 'name' => 'Koncert pro svobodu']);
    $event = PlanEvent::factory()->create([
        'date' => '2026-11-10',
        'name' => 'Koncert pro svobodu',
        'google_sheet_import_id' => $import->id,
    ]);
    PlanEvent::factory()->create(['date' => '2026-12-01']);

    $this->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('days.0.events', 0)
            ->has('days.9.events', 1)
            ->where('days.9.events.0.id', $event->id)
            ->where('days.9.events.0.name', 'Koncert pro svobodu')
            ->where('days.9.events.0.hasChanges', false)
            ->where('days.9.events.0.changedFields', [])
            ->where('days.9.events.0.changedName', null)
            ->where('days.9.events.0.showOriginalText', true)
            ->where('days.9.events.0.isImported', true)
            ->has('days.9.events.0.objects', 0)
        );
});

test('it shows the objects of an event with their category colors and the original text switch', function () {
    $event = PlanEvent::factory()->create(['date' => '2026-11-10', 'name' => 'Koncert', 'show_original_text' => false]);
    $category = PlanEventCategory::factory()->create(['label' => 'Koncert', 'background_color' => '#112233', 'font_color' => '#ffeedd']);
    $first = PlanEventObject::factory()->for($event, 'planEvent')->for($category, 'category')->create(['text' => 'ČF 19:30']);
    $second = PlanEventObject::factory()->for($event, 'planEvent')->for($category, 'category')->create(['text' => 'Hrazda']);

    $this->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.9.events.0.name', 'Koncert')
            ->where('days.9.events.0.showOriginalText', false)
            ->has('days.9.events.0.objects', 2)
            ->where('days.9.events.0.objects.0.id', $first->id)
            ->where('days.9.events.0.objects.0.text', 'ČF 19:30')
            ->where('days.9.events.0.objects.0.category.label', 'Koncert')
            ->where('days.9.events.0.objects.0.category.background_color', '#112233')
            ->where('days.9.events.0.objects.0.category.font_color', '#ffeedd')
            ->where('days.9.events.0.objects.1.id', $second->id)
        );
});

test('it flags the events whose imported row changed in the sheet', function () {
    $import = GoogleSheetImport::factory()
        ->changed(['name' => 'Nový název', 'operator_1' => 'Jan Salát'])
        ->create(['date' => '2026-11-10', 'name' => 'Starý název']);
    PlanEvent::factory()->create(['date' => '2026-11-10', 'name' => 'Starý název', 'google_sheet_import_id' => $import->id]);
    PlanEvent::factory()->create(['date' => '2026-11-11', 'name' => 'Ručně zadáno']);

    $this->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.9.events.0.name', 'Starý název')
            ->where('days.9.events.0.hasChanges', true)
            ->where('days.9.events.0.changedFields', ['name', 'operator_1'])
            ->where('days.9.events.0.changedName', 'Nový název')
            ->where('days.10.events.0.hasChanges', false)
        );
});

test('it shows who works on an event', function () {
    PlanMonthSetting::factory()->staffShown()->create(['month' => '2026-11']);
    $operator = User::factory()->create(['name' => 'Jan', 'surname' => 'Novák']);
    $event = PlanEvent::factory()->create(['date' => '2026-11-03', 'operator_1' => $operator->id]);

    $this->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.2.events.0.id', $event->id)
            ->where('days.2.events.0.assignments.operator_1', $operator->id)
            ->where('days.2.events.0.assignments.engineer_1', null)
            ->where('staff', fn ($staff) => collect($staff)->contains(fn ($member) => $member['id'] === $operator->id && $member['surname'] === 'Novák'))
        );
});

test('it tells whether the original text of an event was changed by an accepted change', function () {
    PlanEvent::factory()->create(['date' => '2026-11-10', 'original_text_changed' => true]);
    PlanEvent::factory()->create(['date' => '2026-11-11']);

    $this->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.9.events.0.originalTextChanged', true)
            ->where('days.10.events.0.originalTextChanged', false)
        );
});
