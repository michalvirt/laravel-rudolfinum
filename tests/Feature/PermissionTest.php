<?php

use App\Enums\Permission;
use App\Models\PermissionGroup;
use App\Models\User;

test('it redirects guests to the login form', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['calendar', 'plan-calendar-view', 'plan-calendar-edit', 'plan-categories.index', 'accounts.index', 'plan-import.index', 'email-notifications.index']);

test('it grants access according to the permission group', function (string $group, string $routeName, int $status) {
    $user = User::factory()->for(PermissionGroup::factory()->{$group}())->create();

    $this->actingAs($user)->get(route($routeName))->assertStatus($status);
})->with([
    'administrators view the calendar' => ['administrators', 'calendar', 200],
    'administrators view the plan calendar' => ['administrators', 'plan-calendar-view', 200],
    'planners view the plan calendar' => ['planners', 'plan-calendar-view', 200],
    'planners cannot view the calendar' => ['planners', 'calendar', 403],
    'viewers view the calendar' => ['viewers', 'calendar', 200],
    'viewers cannot view the plan calendar' => ['viewers', 'plan-calendar-view', 403],
    'administrators manage accounts' => ['administrators', 'accounts.index', 200],
    'planners cannot manage accounts' => ['planners', 'accounts.index', 403],
    'viewers cannot manage accounts' => ['viewers', 'accounts.index', 403],
    'administrators edit the plan calendar' => ['administrators', 'plan-calendar-edit', 200],
    'planners edit the plan calendar' => ['planners', 'plan-calendar-edit', 200],
    'viewers cannot edit the plan calendar' => ['viewers', 'plan-calendar-edit', 403],
    'administrators manage plan categories' => ['administrators', 'plan-categories.index', 200],
    'planners cannot manage plan categories' => ['planners', 'plan-categories.index', 403],
    'viewers cannot manage plan categories' => ['viewers', 'plan-categories.index', 403],
    'administrators import the plan' => ['administrators', 'plan-import.index', 200],
    'planners cannot import the plan' => ['planners', 'plan-import.index', 403],
    'viewers cannot import the plan' => ['viewers', 'plan-import.index', 403],
    'administrators manage email notifications' => ['administrators', 'email-notifications.index', 200],
    'planners cannot manage email notifications' => ['planners', 'email-notifications.index', 403],
    'viewers cannot manage email notifications' => ['viewers', 'email-notifications.index', 403],
]);

test('it grants nothing to a user without a permission group', function () {
    $user = User::factory()->create(['permission_group_id' => null]);

    expect($user->hasPermission(Permission::ViewCalendar))->toBeFalse()
        ->and($user->permissionValues())->toBe([]);

    $this->actingAs($user)->get(route('calendar'))->assertForbidden();
});

test('it lists the permission values of a user', function () {
    $user = User::factory()->for(PermissionGroup::factory()->administrators())->create();

    expect($user->permissionValues())->toBe(['calendar.view', 'plan-calendar.view', 'accounts.manage', 'plan-import.manage', 'plan-calendar.edit', 'plan-categories.manage', 'email-notifications.manage', 'importGoogleSheet.notification', 'planEventChange.notification']);
});
