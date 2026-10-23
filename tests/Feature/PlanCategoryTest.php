<?php

use App\Models\PermissionGroup;
use App\Models\PlanEventCategory;
use App\Models\PlanEventObject;
use App\Models\User;
use Database\Seeders\PlanEventCategorySeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->administrator = User::factory()->for(PermissionGroup::factory()->administrators())->create();
});

/**
 * @return array<string, string>
 */
function categoryPayload(array $overrides = []): array
{
    return [
        'label' => 'Koncert',
        'background_color' => '#1565c0',
        'font_color' => '#ffffff',
        ...$overrides,
    ];
}

test('it redirects guests to the login form', function () {
    $category = PlanEventCategory::factory()->create();

    $this->get(route('plan-categories.index'))->assertRedirect(route('login'));
    $this->post(route('plan-categories.store'), categoryPayload())->assertRedirect(route('login'));
    $this->put(route('plan-categories.update', $category), categoryPayload())->assertRedirect(route('login'));
    $this->delete(route('plan-categories.destroy', $category))->assertRedirect(route('login'));
});

test('it forbids every category action without the manage plan categories permission', function (string $group) {
    $category = PlanEventCategory::factory()->create(['label' => 'Original']);

    $this->actingAs(User::factory()->for(PermissionGroup::factory()->{$group}())->create());

    $this->get(route('plan-categories.index'))->assertForbidden();
    $this->post(route('plan-categories.store'), categoryPayload())->assertForbidden();
    $this->put(route('plan-categories.update', $category), categoryPayload())->assertForbidden();
    $this->delete(route('plan-categories.destroy', $category))->assertForbidden();

    expect(PlanEventCategory::count())->toBe(1)
        ->and($category->refresh()->label)->toBe('Original');
})->with(['planners', 'viewers']);

test('it lists the categories with the number of objects using them', function () {
    $zkouska = PlanEventCategory::factory()->create(['label' => 'Zkouška', 'background_color' => '#2e7d32', 'font_color' => '#ffffff']);
    $koncert = PlanEventCategory::factory()->create(['label' => 'Koncert']);
    PlanEventObject::factory()->count(2)->for($zkouska, 'category')->create();

    $this->actingAs($this->administrator)
        ->get(route('plan-categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plan-categories')
            ->has('categories', 2)
            ->where('categories.0.id', $koncert->id)
            ->where('categories.0.objectsCount', 0)
            ->where('categories.1.id', $zkouska->id)
            ->where('categories.1.label', 'Zkouška')
            ->where('categories.1.background_color', '#2e7d32')
            ->where('categories.1.font_color', '#ffffff')
            ->where('categories.1.objectsCount', 2)
        );
});

test('it creates a category with a label and colors', function () {
    $this->actingAs($this->administrator)
        ->post(route('plan-categories.store'), categoryPayload(['background_color' => '#AABBCC', 'font_color' => '#000000']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $category = PlanEventCategory::firstOrFail();

    expect(Str::isUuid($category->id))->toBeTrue()
        ->and($category->label)->toBe('Koncert')
        ->and($category->background_color)->toBe('#AABBCC')
        ->and($category->font_color)->toBe('#000000');
});

test('it validates a category', function (array $overrides, string $field) {
    $this->actingAs($this->administrator)
        ->post(route('plan-categories.store'), categoryPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(PlanEventCategory::count())->toBe(0);
})->with([
    'missing label' => [['label' => ''], 'label'],
    'too long label' => [['label' => str_repeat('a', 101)], 'label'],
    'missing background color' => [['background_color' => ''], 'background_color'],
    'invalid background color' => [['background_color' => 'blue'], 'background_color'],
    'short background color' => [['background_color' => '#fff'], 'background_color'],
    'missing font color' => [['font_color' => ''], 'font_color'],
    'invalid font color' => [['font_color' => '#12345g'], 'font_color'],
]);

test('it rejects a label that is already taken', function () {
    PlanEventCategory::factory()->create(['label' => 'Koncert']);

    $this->actingAs($this->administrator)
        ->post(route('plan-categories.store'), categoryPayload())
        ->assertSessionHasErrors('label');

    expect(PlanEventCategory::count())->toBe(1);
});

test('it updates a category', function () {
    $category = PlanEventCategory::factory()->create(['label' => 'Starý']);

    $this->actingAs($this->administrator)
        ->put(route('plan-categories.update', $category), categoryPayload(['label' => 'Nový', 'background_color' => '#000000', 'font_color' => '#ffff00']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $category->refresh();

    expect($category->label)->toBe('Nový')
        ->and($category->background_color)->toBe('#000000')
        ->and($category->font_color)->toBe('#ffff00');
});

test('it lets a category keep its own label when updating', function () {
    $category = PlanEventCategory::factory()->create(['label' => 'Koncert']);

    $this->actingAs($this->administrator)
        ->put(route('plan-categories.update', $category), categoryPayload(['font_color' => '#111111']))
        ->assertSessionHasNoErrors();

    expect($category->refresh()->font_color)->toBe('#111111');
});

test('it rejects an update that uses the label of another category', function () {
    PlanEventCategory::factory()->create(['label' => 'Koncert']);
    $category = PlanEventCategory::factory()->create(['label' => 'Zkouška']);

    $this->actingAs($this->administrator)
        ->put(route('plan-categories.update', $category), categoryPayload())
        ->assertSessionHasErrors('label');

    expect($category->refresh()->label)->toBe('Zkouška');
});

test('it deletes a category that no object uses', function () {
    $category = PlanEventCategory::factory()->create();

    $this->actingAs($this->administrator)
        ->delete(route('plan-categories.destroy', $category))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($category);
});

test('it does not delete a category that objects use', function () {
    $category = PlanEventCategory::factory()->create(['label' => 'Koncert']);
    PlanEventObject::factory()->count(2)->for($category, 'category')->create();

    $this->actingAs($this->administrator)
        ->delete(route('plan-categories.destroy', $category))
        ->assertSessionHasErrors('category');

    $this->assertModelExists($category);
    expect(PlanEventObject::count())->toBe(2);
});

test('it seeds the default categories once', function () {
    $this->seed(PlanEventCategorySeeder::class);
    $this->seed(PlanEventCategorySeeder::class);

    expect(PlanEventCategory::orderBy('label')->pluck('label')->all())->toBe(['Instalace', 'Koncert', 'Zkouška']);
});
