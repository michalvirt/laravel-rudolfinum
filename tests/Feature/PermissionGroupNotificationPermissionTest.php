<?php

use App\Enums\Permission;
use App\Models\PermissionGroup;
use App\Models\User;

beforeEach(function () {
    $this->administrator = User::factory()->for(PermissionGroup::factory()->administrators())->create();
    $this->group = PermissionGroup::factory()->planners()->create();
});

test('it redirects guests to the login form', function () {
    $this->put(route('permission-groups.notification-permissions.update', $this->group))
        ->assertRedirect(route('login'));
});

test('it forbids users without the manage accounts permission', function () {
    $user = User::factory()->for(PermissionGroup::factory()->viewers())->create();

    $this->actingAs($user)
        ->put(route('permission-groups.notification-permissions.update', $this->group), ['permissions' => ['importGoogleSheet.notification']])
        ->assertForbidden();

    expect($this->group->refresh()->grants(Permission::ReceiveImportGoogleSheetNotification))->toBeFalse();
});

test('it sets the notification permissions of a group and keeps its other permissions', function () {
    $this->actingAs($this->administrator)
        ->put(route('permission-groups.notification-permissions.update', $this->group), ['permissions' => ['importGoogleSheet.notification']])
        ->assertRedirect();

    expect($this->group->refresh()->permissions->all())->toEqualCanonicalizing([
        Permission::ViewCalendar,
        Permission::ViewPlanCalendar,
        Permission::EditPlanCalendar,
        Permission::ReceiveImportGoogleSheetNotification,
    ]);
});

test('it clears the notification permissions of a group when none is sent', function () {
    $this->group->update(['permissions' => [Permission::ViewCalendar, Permission::ReceiveImportGoogleSheetNotification]]);

    $this->actingAs($this->administrator)
        ->put(route('permission-groups.notification-permissions.update', $this->group))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->group->refresh()->permissions->all())->toBe([Permission::ViewCalendar]);
});

test('it does not duplicate a notification permission the group already has', function () {
    $this->group->update(['permissions' => [Permission::ReceiveImportGoogleSheetNotification]]);

    $this->actingAs($this->administrator)
        ->put(route('permission-groups.notification-permissions.update', $this->group), ['permissions' => ['importGoogleSheet.notification']])
        ->assertRedirect();

    expect($this->group->refresh()->permissions->all())->toBe([Permission::ReceiveImportGoogleSheetNotification]);
});

test('it only lets notification permissions be set on a group', function (array $permissions) {
    $this->actingAs($this->administrator)
        ->put(route('permission-groups.notification-permissions.update', $this->group), ['permissions' => $permissions])
        ->assertSessionHasErrors('permissions.0');

    expect($this->group->refresh()->grants(Permission::ManageAccounts))->toBeFalse();
})->with([
    'a permission that is not a notification' => [['accounts.manage']],
    'an unknown permission' => [['everything.do']],
]);

test('it validates that the permissions are a list', function () {
    $this->actingAs($this->administrator)
        ->put(route('permission-groups.notification-permissions.update', $this->group), ['permissions' => 'importGoogleSheet.notification'])
        ->assertSessionHasErrors('permissions');
});
