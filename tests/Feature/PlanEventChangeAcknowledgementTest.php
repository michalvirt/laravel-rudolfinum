<?php

use App\Models\GoogleSheetImport;
use App\Models\PermissionGroup;
use App\Models\PlanEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->group = PermissionGroup::factory()->viewers()->create();
    $this->assigned = User::factory()->for($this->group)->create();
    $this->event = PlanEvent::factory()->create([
        'date' => '2026-11-10',
        'original_text_changed' => true,
        'operator_1' => $this->assigned->id,
    ]);
});

test('it redirects guests to the login form', function () {
    $this->post(route('plan-events.change-acknowledgement', $this->event))
        ->assertRedirect(route('login'));

    expect($this->event->changeAcknowledgedBy()->count())->toBe(0);
});

test('it forbids users who cannot view the plan calendar', function () {
    $user = User::factory()->create();
    $this->event->update(['engineer_1' => $user->id]);

    $this->actingAs($user)
        ->post(route('plan-events.change-acknowledgement', $this->event))
        ->assertForbidden();
});

test('it forbids users who are not assigned to the event', function () {
    $other = User::factory()->for(PermissionGroup::factory()->planners())->create();

    $this->actingAs($other)
        ->post(route('plan-events.change-acknowledgement', $this->event))
        ->assertForbidden();

    expect($this->event->changeAcknowledgedBy()->count())->toBe(0);
});

test('it lets an assigned operator or engineer acknowledge the change', function () {
    $engineer = User::factory()->for($this->group)->create();
    $this->event->update(['engineer_2' => $engineer->id]);

    $this->actingAs($this->assigned)
        ->post(route('plan-events.change-acknowledgement', $this->event))
        ->assertRedirect();
    $this->actingAs($engineer)
        ->post(route('plan-events.change-acknowledgement', $this->event));
    $this->post(route('plan-events.change-acknowledgement', $this->event));

    expect($this->event->changeAcknowledgedBy()->pluck('users.id')->all())
        ->toEqualCanonicalizing([$this->assigned->id, $engineer->id])
        ->and($this->event->refresh()->original_text_changed)->toBeTrue();
});

test('it ignores an acknowledgement of an event without a changed text', function () {
    $this->event->update(['original_text_changed' => false]);

    $this->actingAs($this->assigned)
        ->post(route('plan-events.change-acknowledgement', $this->event))
        ->assertRedirect();

    expect($this->event->changeAcknowledgedBy()->count())->toBe(0);
});

test('it flags the changed event only for the assigned user until the change is acknowledged', function () {
    $other = User::factory()->for(PermissionGroup::factory()->planners())->create();
    PlanEvent::factory()->create(['date' => '2026-11-11', 'original_text_changed' => true]);

    $this->actingAs($this->assigned)
        ->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.9.events.0.awaitsChangeAcknowledgement', true)
            ->where('days.10.events.0.awaitsChangeAcknowledgement', false)
        );

    $this->actingAs($other)
        ->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.9.events.0.originalTextChanged', true)
            ->where('days.9.events.0.awaitsChangeAcknowledgement', false)
        );

    $this->actingAs($this->assigned)
        ->post(route('plan-events.change-acknowledgement', $this->event));

    $this->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.9.events.0.awaitsChangeAcknowledgement', false)
        );
});

test('it flags the change for the assigned user even when the staff of the month is hidden', function () {
    $this->actingAs($this->assigned)
        ->get(route('plan-calendar-view', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('staffVisible', false)
            ->where('days.9.events.0.assignments.operator_1', null)
            ->where('days.9.events.0.awaitsChangeAcknowledgement', true)
        );
});

test('it asks for a new acknowledgement when another accepted change renames the event', function () {
    $import = GoogleSheetImport::factory()->changed(['name' => 'Nový název'])->create(['name' => 'Starý název']);
    $this->event->update(['google_sheet_import_id' => $import->id]);
    $this->event->changeAcknowledgedBy()->attach($this->assigned);

    $this->actingAs(User::factory()->for(PermissionGroup::factory()->administrators())->create())
        ->post(route('plan-import.changes.accept', $import));

    expect($this->event->changeAcknowledgedBy()->count())->toBe(0);
});

test('it forgets the acknowledgements when a planner decides about the original text', function () {
    $this->event->changeAcknowledgedBy()->attach($this->assigned);

    $this->actingAs(User::factory()->for(PermissionGroup::factory()->planners())->create())
        ->put(route('plan-events.update', $this->event), ['show_original_text' => true]);

    expect($this->event->changeAcknowledgedBy()->count())->toBe(0);
});
