<?php

use App\Models\GoogleSheetImport;
use App\Models\PermissionGroup;
use App\Models\PlanEvent;
use App\Models\PlanEventCategory;
use App\Models\PlanEventObject;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->planner = User::factory()->for(PermissionGroup::factory()->planners())->create();
});

test('it redirects guests to the login form', function () {
    $event = PlanEvent::factory()->create();
    $object = PlanEventObject::factory()->create();

    $this->get(route('plan-calendar-edit'))->assertRedirect(route('login'));
    $this->post(route('plan-events.store'), ['date' => '2026-11-01'])->assertRedirect(route('login'));
    $this->put(route('plan-events.update', $event), ['show_original_text' => false])->assertRedirect(route('login'));
    $this->delete(route('plan-events.destroy', $event))->assertRedirect(route('login'));
    $this->post(route('plan-event-objects.store'))->assertRedirect(route('login'));
    $this->put(route('plan-event-objects.update', $object))->assertRedirect(route('login'));
    $this->delete(route('plan-event-objects.destroy', $object))->assertRedirect(route('login'));
    $this->put(route('plan-events.objects.order', $event), ['objects' => [$object->id]])->assertRedirect(route('login'));
});

test('it forbids every edit action without the edit plan calendar permission', function () {
    $event = PlanEvent::factory()->create(['show_original_text' => true]);
    $object = PlanEventObject::factory()->create();
    $category = PlanEventCategory::factory()->create();

    $this->actingAs(User::factory()->for(PermissionGroup::factory()->viewers())->create());

    $this->get(route('plan-calendar-edit'))->assertForbidden();
    $this->post(route('plan-events.store'), ['date' => '2026-11-01'])->assertForbidden();
    $this->put(route('plan-events.update', $event), ['show_original_text' => false])->assertForbidden();
    $this->delete(route('plan-events.destroy', $event))->assertForbidden();
    $this->post(route('plan-event-objects.store'), ['plan_event_id' => $event->id, 'plan_event_category_id' => $category->id, 'text' => 'Text'])->assertForbidden();
    $this->put(route('plan-event-objects.update', $object), ['plan_event_category_id' => $category->id, 'text' => 'Text'])->assertForbidden();
    $this->delete(route('plan-event-objects.destroy', $object))->assertForbidden();
    $this->put(route('plan-events.objects.order', $object->planEvent), ['objects' => [$object->id]])->assertForbidden();

    expect($event->refresh()->show_original_text)->toBeTrue()
        ->and(PlanEventObject::count())->toBe(1);
});

test('it shows the days of a month with their events, objects and the categories', function () {
    $import = GoogleSheetImport::factory()->changed(['name' => 'Nový název'])->create();
    $imported = PlanEvent::factory()->create(['date' => '2026-11-10', 'name' => 'Koncert', 'google_sheet_import_id' => $import->id]);
    $manual = PlanEvent::factory()->create(['date' => '2026-11-11', 'name' => null]);
    $zkouska = PlanEventCategory::factory()->create(['label' => 'Zkouška']);
    $koncert = PlanEventCategory::factory()->create(['label' => 'Koncert']);
    $object = PlanEventObject::factory()->for($imported, 'planEvent')->for($koncert, 'category')->create(['text' => 'ČF']);

    $this->actingAs($this->planner)
        ->get(route('plan-calendar-edit', ['month' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plan-calendar-edit')
            ->where('month', '2026-11')
            ->has('days', 30)
            ->where('days.9.events.0.id', $imported->id)
            ->where('days.9.events.0.isImported', true)
            ->where('days.9.events.0.hasChanges', true)
            ->where('days.9.events.0.objects.0.id', $object->id)
            ->where('days.9.events.0.objects.0.plan_event_category_id', $koncert->id)
            ->where('days.10.events.0.id', $manual->id)
            ->where('days.10.events.0.isImported', false)
            ->where('days.10.events.0.name', null)
            ->has('categories', 2)
            ->where('categories.0.id', $koncert->id)
            ->where('categories.1.id', $zkouska->id)
        );
});

test('it rejects an invalid month', function () {
    $this->actingAs($this->planner)
        ->get(route('plan-calendar-edit', ['month' => 'invalid']))
        ->assertSessionHasErrors('month');
});

test('it adds an event to a day', function () {
    $this->actingAs($this->planner)
        ->post(route('plan-events.store'), ['date' => '2026-11-15'])
        ->assertRedirect();

    $event = PlanEvent::firstOrFail();

    expect(Str::isUuid($event->id))->toBeTrue()
        ->and($event->date->toDateString())->toBe('2026-11-15')
        ->and($event->name)->toBeNull()
        ->and($event->show_original_text)->toBeTrue()
        ->and($event->google_sheet_import_id)->toBeNull();
});

test('it validates the date of a new event', function (mixed $date) {
    $this->actingAs($this->planner)
        ->post(route('plan-events.store'), ['date' => $date])
        ->assertSessionHasErrors('date');

    expect(PlanEvent::count())->toBe(0);
})->with(['missing' => [null], 'wrong format' => ['15.11.2026'], 'not a date' => ['2026-13-45']]);

test('it hides and shows the original text of an event', function () {
    $event = PlanEvent::factory()->create();

    $this->actingAs($this->planner)
        ->put(route('plan-events.update', $event), ['show_original_text' => false])
        ->assertRedirect();

    expect($event->refresh()->show_original_text)->toBeFalse();

    $this->put(route('plan-events.update', $event), ['show_original_text' => true]);

    expect($event->refresh()->show_original_text)->toBeTrue();
});

test('it clears the mark of an accepted change when the original text is decided on', function () {
    $event = PlanEvent::factory()->create(['original_text_changed' => true]);

    $this->actingAs($this->planner)
        ->put(route('plan-events.update', $event), ['show_original_text' => true])
        ->assertRedirect();

    expect($event->refresh()->original_text_changed)->toBeFalse();
});

test('it validates the original text switch', function (mixed $value) {
    $event = PlanEvent::factory()->create();

    $this->actingAs($this->planner)
        ->put(route('plan-events.update', $event), ['show_original_text' => $value])
        ->assertSessionHasErrors('show_original_text');

    expect($event->refresh()->show_original_text)->toBeTrue();
})->with(['missing' => [null], 'text' => ['maybe']]);

test('it assigns operators and engineers to an event', function () {
    $event = PlanEvent::factory()->create();
    [$first, $second, $third, $fourth] = User::factory()->count(4)->create();

    $this->actingAs($this->planner)
        ->put(route('plan-events.assignments', $event), [
            'operator_1' => $first->id,
            'operator_2' => $second->id,
            'operator_3' => null,
            'engineer_1' => $third->id,
            'engineer_2' => $fourth->id,
            'engineer_3' => null,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $event->refresh();

    expect($event->operator_1)->toBe($first->id)
        ->and($event->operator_2)->toBe($second->id)
        ->and($event->operator_3)->toBeNull()
        ->and($event->engineer_1)->toBe($third->id)
        ->and($event->engineer_2)->toBe($fourth->id)
        ->and($event->engineer_3)->toBeNull();
});

test('it clears an assignment', function () {
    $user = User::factory()->create();
    $event = PlanEvent::factory()->create(['operator_1' => $user->id]);

    $this->actingAs($this->planner)
        ->put(route('plan-events.assignments', $event), [
            'operator_1' => null, 'operator_2' => null, 'operator_3' => null,
            'engineer_1' => null, 'engineer_2' => null, 'engineer_3' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($event->refresh()->operator_1)->toBeNull();
});

test('it lets a user work on events of different days', function () {
    $user = User::factory()->create();
    $first = PlanEvent::factory()->create(['date' => '2026-11-03']);
    $second = PlanEvent::factory()->create(['date' => '2026-11-04']);

    $this->actingAs($this->planner);

    foreach ([$first, $second] as $event) {
        $this->put(route('plan-events.assignments', $event), [
            'operator_1' => $user->id, 'operator_2' => null, 'operator_3' => null,
            'engineer_1' => null, 'engineer_2' => null, 'engineer_3' => null,
        ])->assertSessionHasNoErrors();
    }

    expect($first->refresh()->operator_1)->toBe($user->id)
        ->and($second->refresh()->operator_1)->toBe($user->id);
});

test('it does not assign a user to more than one position of an event', function (string $first, string $second) {
    $user = User::factory()->create();
    $event = PlanEvent::factory()->create();
    $assignments = array_fill_keys(PlanEvent::ASSIGNMENT_COLUMNS, null);

    $this->actingAs($this->planner)
        ->put(route('plan-events.assignments', $event), [...$assignments, $first => $user->id, $second => $user->id])
        ->assertSessionHasErrors($second);

    expect($event->refresh()->{$first})->toBeNull()
        ->and($event->{$second})->toBeNull();
})->with([
    'two operators' => ['operator_1', 'operator_2'],
    'two engineers' => ['engineer_1', 'engineer_2'],
    'an operator and an engineer' => ['operator_1', 'engineer_1'],
]);

test('it does not assign a user to two events of the same day', function () {
    $user = User::factory()->create();
    $first = PlanEvent::factory()->create(['date' => '2026-11-03', 'engineer_1' => $user->id]);
    $second = PlanEvent::factory()->create(['date' => '2026-11-03']);
    $assignments = array_fill_keys(PlanEvent::ASSIGNMENT_COLUMNS, null);

    $this->actingAs($this->planner)
        ->put(route('plan-events.assignments', $second), [...$assignments, 'operator_1' => $user->id])
        ->assertSessionHasErrors('operator_1');

    expect($second->refresh()->operator_1)->toBeNull();

    $this->put(route('plan-events.assignments', $first), [...$assignments, 'operator_1' => $user->id])
        ->assertSessionHasNoErrors();

    expect($first->refresh()->operator_1)->toBe($user->id);
});

test('it frees a user for another event of the day once the user is unassigned', function () {
    $user = User::factory()->create();
    $first = PlanEvent::factory()->create(['date' => '2026-11-03', 'operator_1' => $user->id]);
    $second = PlanEvent::factory()->create(['date' => '2026-11-03']);
    $assignments = array_fill_keys(PlanEvent::ASSIGNMENT_COLUMNS, null);

    $this->actingAs($this->planner)
        ->put(route('plan-events.assignments', $first), $assignments)
        ->assertSessionHasNoErrors();
    $this->put(route('plan-events.assignments', $second), [...$assignments, 'engineer_1' => $user->id])
        ->assertSessionHasNoErrors();

    expect($second->refresh()->engineer_1)->toBe($user->id);
});

test('it fills the positions of operators and engineers one after another', function (string $position, array $before) {
    $event = PlanEvent::factory()->create();
    $assignments = array_fill_keys(PlanEvent::ASSIGNMENT_COLUMNS, null);

    $this->actingAs($this->planner)
        ->put(route('plan-events.assignments', $event), [...$assignments, $position => User::factory()->create()->id])
        ->assertSessionHasErrors($position);

    expect($event->refresh()->{$position})->toBeNull();

    foreach ([...$before, $position] as $column) {
        $assignments[$column] = User::factory()->create()->id;

        $this->put(route('plan-events.assignments', $event), $assignments)
            ->assertSessionHasNoErrors();
    }

    expect($event->refresh()->{$position})->toBe($assignments[$position]);
})->with([
    'second operator' => ['operator_2', ['operator_1']],
    'third operator' => ['operator_3', ['operator_1', 'operator_2']],
    'second engineer' => ['engineer_2', ['engineer_1']],
    'third engineer' => ['engineer_3', ['engineer_1', 'engineer_2']],
]);

test('it validates the assignments of an event', function (array $overrides, string $field) {
    $event = PlanEvent::factory()->create();
    $assignments = array_fill_keys(PlanEvent::ASSIGNMENT_COLUMNS, null);

    $this->actingAs($this->planner)
        ->put(route('plan-events.assignments', $event), [...$assignments, ...$overrides])
        ->assertSessionHasErrors($field);
})->with([
    'unknown user' => [['operator_1' => 'missing'], 'operator_1'],
    'array instead of a user' => [['engineer_3' => ['x']], 'engineer_3'],
]);

test('it requires every position to be submitted', function () {
    $event = PlanEvent::factory()->create();

    $this->actingAs($this->planner)
        ->put(route('plan-events.assignments', $event), ['operator_1' => null])
        ->assertSessionHasErrors(['operator_2', 'engineer_3']);
});

test('it unassigns a user who is removed', function () {
    $user = User::factory()->create();
    $event = PlanEvent::factory()->create(['engineer_2' => $user->id]);

    $user->delete();

    expect($event->refresh()->engineer_2)->toBeNull();
});

test('it shows the assignments and the users to choose from', function () {
    $user = User::factory()->create(['name' => 'Jan', 'surname' => 'Novák']);
    $event = PlanEvent::factory()->create(['date' => '2026-11-03', 'engineer_2' => $user->id]);

    $this->actingAs($this->planner)
        ->get(route('plan-calendar-edit', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.2.events.0.id', $event->id)
            ->where('days.2.events.0.assignments.engineer_2', $user->id)
            ->where('days.2.events.0.assignments.operator_1', null)
            ->has('days.2.events.0.assignments', 6)
            ->has('staff', 2)
            ->where('staff', fn ($staff) => collect($staff)->contains('surname', 'Novák'))
        );
});

test('it removes an event added by hand together with its objects', function () {
    $event = PlanEvent::factory()->create(['name' => null]);
    PlanEventObject::factory()->for($event, 'planEvent')->create();

    $this->actingAs($this->planner)
        ->delete(route('plan-events.destroy', $event))
        ->assertRedirect();

    $this->assertModelMissing($event);
    expect(PlanEventObject::count())->toBe(0);
});

test('it does not remove an imported event', function () {
    $import = GoogleSheetImport::factory()->create();
    $event = PlanEvent::factory()->create(['google_sheet_import_id' => $import->id]);

    $this->actingAs($this->planner)
        ->delete(route('plan-events.destroy', $event))
        ->assertForbidden();

    $this->assertModelExists($event);
});

test('it adds an object with a category to an event', function () {
    $event = PlanEvent::factory()->create();
    $category = PlanEventCategory::factory()->create();

    $this->actingAs($this->planner)
        ->post(route('plan-event-objects.store'), [
            'plan_event_id' => $event->id,
            'plan_event_category_id' => $category->id,
            'text' => 'ČF 19:30',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $object = PlanEventObject::firstOrFail();

    expect(Str::isUuid($object->id))->toBeTrue()
        ->and($object->plan_event_id)->toBe($event->id)
        ->and($object->plan_event_category_id)->toBe($category->id)
        ->and($object->text)->toBe('ČF 19:30');
});

test('it adds more than one object to an event', function () {
    $event = PlanEvent::factory()->create();
    $category = PlanEventCategory::factory()->create();

    $this->actingAs($this->planner);

    foreach (['První', 'Druhý', 'Třetí'] as $text) {
        $this->post(route('plan-event-objects.store'), [
            'plan_event_id' => $event->id,
            'plan_event_category_id' => $category->id,
            'text' => $text,
        ]);
    }

    expect($event->objects->pluck('text')->all())->toBe(['První', 'Druhý', 'Třetí']);
});

test('it validates a new object', function (array $overrides, string $field) {
    $event = PlanEvent::factory()->create();
    $category = PlanEventCategory::factory()->create();

    $this->actingAs($this->planner)
        ->post(route('plan-event-objects.store'), [
            'plan_event_id' => $event->id,
            'plan_event_category_id' => $category->id,
            'text' => 'Text',
            ...$overrides,
        ])
        ->assertSessionHasErrors($field);

    expect(PlanEventObject::count())->toBe(0);
})->with([
    'missing event' => [['plan_event_id' => null], 'plan_event_id'],
    'unknown event' => [['plan_event_id' => 'missing'], 'plan_event_id'],
    'missing category' => [['plan_event_category_id' => null], 'plan_event_category_id'],
    'unknown category' => [['plan_event_category_id' => 'missing'], 'plan_event_category_id'],
    'missing text' => [['text' => ''], 'text'],
    'too long text' => [['text' => str_repeat('a', 256)], 'text'],
]);

test('it changes the text and category of an object but not its event', function () {
    $object = PlanEventObject::factory()->create(['text' => 'Starý text']);
    $otherEvent = PlanEvent::factory()->create();
    $category = PlanEventCategory::factory()->create();

    $this->actingAs($this->planner)
        ->put(route('plan-event-objects.update', $object), [
            'plan_event_id' => $otherEvent->id,
            'plan_event_category_id' => $category->id,
            'text' => 'Nový text',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $planEventId = $object->plan_event_id;
    $object->refresh();

    expect($object->text)->toBe('Nový text')
        ->and($object->plan_event_category_id)->toBe($category->id)
        ->and($object->plan_event_id)->toBe($planEventId);
});

test('it validates an updated object', function () {
    $object = PlanEventObject::factory()->create(['text' => 'Starý text']);

    $this->actingAs($this->planner)
        ->put(route('plan-event-objects.update', $object), ['plan_event_category_id' => 'missing', 'text' => ''])
        ->assertSessionHasErrors(['plan_event_category_id', 'text']);

    expect($object->refresh()->text)->toBe('Starý text');
});

test('it removes an object', function () {
    $object = PlanEventObject::factory()->create();
    $event = $object->planEvent;
    $category = $object->category;

    $this->actingAs($this->planner)
        ->delete(route('plan-event-objects.destroy', $object))
        ->assertRedirect();

    $this->assertModelMissing($object);
    $this->assertModelExists($event);
    $this->assertModelExists($category);
});

test('it adds new objects behind the existing ones', function () {
    $event = PlanEvent::factory()->create();
    $category = PlanEventCategory::factory()->create();
    PlanEventObject::factory()->for($event, 'planEvent')->for($category, 'category')->create(['text' => 'První', 'position' => 4]);

    $this->actingAs($this->planner)->post(route('plan-event-objects.store'), [
        'plan_event_id' => $event->id,
        'plan_event_category_id' => $category->id,
        'text' => 'Druhý',
    ]);

    expect($event->objects->pluck('text')->all())->toBe(['První', 'Druhý'])
        ->and($event->objects->last()->position)->toBe(5);
});

test('it changes the order of the objects of an event', function () {
    $event = PlanEvent::factory()->create();
    [$first, $second, $third] = PlanEventObject::factory()
        ->count(3)
        ->for($event, 'planEvent')
        ->sequence(['position' => 0], ['position' => 1], ['position' => 2])
        ->create();
    $otherObject = PlanEventObject::factory()->create(['position' => 7]);

    $this->actingAs($this->planner)
        ->put(route('plan-events.objects.order', $event), ['objects' => [$third->id, $first->id, $second->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($event->objects()->pluck('id')->all())->toBe([$third->id, $first->id, $second->id])
        ->and($otherObject->refresh()->position)->toBe(7);

    $this->get(route('plan-calendar-edit', ['month' => $event->date->format('Y-m')]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.'.($event->date->day - 1).'.events.0.objects.0.id', $third->id)
            ->where('days.'.($event->date->day - 1).'.events.0.objects.2.id', $second->id)
        );
});

test('it validates the new order of the objects', function (Closure $payload) {
    $event = PlanEvent::factory()->create();
    [$first, $second] = PlanEventObject::factory()
        ->count(2)
        ->for($event, 'planEvent')
        ->sequence(['position' => 0], ['position' => 1])
        ->create();
    $foreign = PlanEventObject::factory()->create();

    $this->actingAs($this->planner)
        ->put(route('plan-events.objects.order', $event), ['objects' => $payload($first, $second, $foreign)])
        ->assertSessionHasErrors();

    expect($event->objects()->pluck('id')->all())->toBe([$first->id, $second->id]);
})->with([
    'missing objects' => [fn () => null],
    'an object is left out' => [fn ($first) => [$first->id]],
    'an unknown object' => [fn ($first, $second) => [$second->id, $first->id, 'missing']],
    'an object of another event' => [fn ($first, $second, $foreign) => [$second->id, $first->id, $foreign->id]],
    'a duplicated object' => [fn ($first) => [$first->id, $first->id]],
]);
