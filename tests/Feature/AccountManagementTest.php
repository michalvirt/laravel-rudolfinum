<?php

use App\Enums\Permission;
use App\Models\PermissionGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->administrator = User::factory()->for(PermissionGroup::factory()->administrators())->create();
});

/**
 * @return array<string, string>
 */
function validAccountPayload(array $overrides = []): array
{
    return [
        'name' => 'Jana',
        'surname' => 'Nováková',
        'email' => 'jana.novakova@example.com',
        'phone' => '+420 123 456 789',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
        ...$overrides,
    ];
}

test('it redirects guests to the login form', function () {
    $account = User::factory()->create();

    $this->get(route('accounts.index'))->assertRedirect(route('login'));
    $this->post(route('accounts.store'), validAccountPayload())->assertRedirect(route('login'));
    $this->put(route('accounts.update', $account), validAccountPayload())->assertRedirect(route('login'));
    $this->delete(route('accounts.destroy', $account))->assertRedirect(route('login'));
    $this->put(route('accounts.password.update', $account), validAccountPayload())->assertRedirect(route('login'));
});

test('it forbids every account action without the manage accounts permission', function (string $group) {
    $user = User::factory()->for(PermissionGroup::factory()->{$group}())->create();
    $account = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('accounts.index'))->assertForbidden();
    $this->post(route('accounts.store'), validAccountPayload())->assertForbidden();
    $this->put(route('accounts.update', $account), validAccountPayload())->assertForbidden();
    $this->delete(route('accounts.destroy', $account))->assertForbidden();
    $this->put(route('accounts.password.update', $account), validAccountPayload())->assertForbidden();

    expect(User::count())->toBe(3);
})->with(['planners', 'viewers']);

test('it lists accounts and assignable permission groups', function () {
    $planners = PermissionGroup::factory()->planners()->create();
    $account = User::factory()->for($planners)->create(['name' => 'Adam', 'surname' => 'Aaron']);

    $this->actingAs($this->administrator)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounts')
            ->has('accounts', 2)
            ->where('accounts.0.id', $account->id)
            ->where('accounts.0.permission_group', 'Planners')
            ->where('accounts.0.isCurrentUser', false)
            ->where('accounts.1.id', $this->administrator->id)
            ->where('accounts.1.isCurrentUser', true)
            ->missing('accounts.0.password')
            ->has('permissionGroups', 2)
        );
});

test('it lists the notification permissions of accounts and groups', function () {
    $group = PermissionGroup::factory()->create(['permissions' => [Permission::ViewCalendar, Permission::ReceiveImportGoogleSheetNotification]]);
    $account = User::factory()->for($group)->create([
        'name' => 'Adam',
        'surname' => 'Aaron',
        'permissions' => [Permission::ReceiveImportGoogleSheetNotification],
    ]);

    $this->actingAs($this->administrator)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.0.id', $account->id)
            ->where('accounts.0.notification_permissions', ['importGoogleSheet.notification'])
            ->where('permissionGroups.1.notification_permissions', ['importGoogleSheet.notification'])
            ->where('notificationPermissions', [
                ['value' => 'importGoogleSheet.notification', 'label' => 'Google Sheet import notification'],
                ['value' => 'planEventChange.notification', 'label' => 'Plan event change notification'],
            ])
        );
});

test('it creates an account with notification permissions', function () {
    $this->actingAs($this->administrator)
        ->post(route('accounts.store'), validAccountPayload(['permissions' => ['importGoogleSheet.notification']]))
        ->assertRedirect();

    $account = User::where('email', 'jana.novakova@example.com')->firstOrFail();

    expect($account->permissions->all())->toBe([Permission::ReceiveImportGoogleSheetNotification]);
});

test('it sets and clears the notification permissions of an account', function () {
    $account = User::factory()->create();

    $this->actingAs($this->administrator)
        ->put(route('accounts.update', $account), validAccountPayload(['permissions' => ['importGoogleSheet.notification']]))
        ->assertRedirect();

    expect($account->refresh()->permissions->all())->toBe([Permission::ReceiveImportGoogleSheetNotification]);

    $this->put(route('accounts.update', $account), validAccountPayload())->assertRedirect();

    expect($account->refresh()->permissions->all())->toBe([]);
});

test('it only lets notification permissions be set on an account', function (array $permissions) {
    $account = User::factory()->create();

    $this->actingAs($this->administrator)
        ->put(route('accounts.update', $account), validAccountPayload(['permissions' => $permissions]))
        ->assertSessionHasErrors('permissions.0');

    expect($account->refresh()->permissions->all())->toBe([]);
})->with([
    'a permission that is not a notification' => [['accounts.manage']],
    'an unknown permission' => [['everything.do']],
]);

test('it grants the permissions set on the account', function () {
    $account = User::factory()->for(PermissionGroup::factory()->viewers())
        ->create(['permissions' => [Permission::ReceiveImportGoogleSheetNotification]]);

    expect($account->hasPermission(Permission::ReceiveImportGoogleSheetNotification))->toBeTrue()
        ->and($account->hasPermission(Permission::ViewCalendar))->toBeTrue()
        ->and($account->hasPermission(Permission::ManageAccounts))->toBeFalse()
        ->and($account->permissionValues())->toEqualCanonicalizing(['calendar.view', 'plan-calendar.view', 'planEventChange.notification', 'importGoogleSheet.notification']);
});

test('it creates an account', function () {
    $planners = PermissionGroup::factory()->planners()->create();

    $this->actingAs($this->administrator)
        ->post(route('accounts.store'), validAccountPayload(['permission_group_id' => $planners->id]))
        ->assertRedirect();

    $account = User::where('email', 'jana.novakova@example.com')->firstOrFail();

    expect($account->name)->toBe('Jana')
        ->and($account->surname)->toBe('Nováková')
        ->and($account->phone)->toBe('+420 123 456 789')
        ->and($account->permission_group_id)->toBe($planners->id)
        ->and(Hash::check('new-password', $account->password))->toBeTrue();
});

test('it validates a new account', function (array $overrides, string $field) {
    $this->actingAs($this->administrator)
        ->post(route('accounts.store'), validAccountPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(User::count())->toBe(1);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'missing surname' => [['surname' => ''], 'surname'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'unknown permission group' => [['permission_group_id' => 'missing'], 'permission_group_id'],
    'missing password' => [['password' => '', 'password_confirmation' => ''], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'different'], 'password'],
]);

test('it rejects an email address that is already taken', function () {
    $this->actingAs($this->administrator)
        ->post(route('accounts.store'), validAccountPayload(['email' => $this->administrator->email]))
        ->assertSessionHasErrors('email');
});

test('it updates an account without touching its password', function () {
    $viewers = PermissionGroup::factory()->viewers()->create();
    $account = User::factory()->create();
    $passwordHash = $account->password;

    $this->actingAs($this->administrator)
        ->put(route('accounts.update', $account), validAccountPayload([
            'email' => 'changed@example.com',
            'permission_group_id' => $viewers->id,
            'password' => 'ignored-password',
        ]))
        ->assertRedirect();

    $account->refresh();

    expect($account->name)->toBe('Jana')
        ->and($account->email)->toBe('changed@example.com')
        ->and($account->permission_group_id)->toBe($viewers->id)
        ->and($account->password)->toBe($passwordHash);
});

test('it removes the permission group of an account', function () {
    $account = User::factory()->create();

    $this->actingAs($this->administrator)
        ->put(route('accounts.update', $account), validAccountPayload(['permission_group_id' => null]))
        ->assertRedirect();

    expect($account->refresh()->permission_group_id)->toBeNull();
});

test('it lets an account keep its own email address when updating', function () {
    $account = User::factory()->create();

    $this->actingAs($this->administrator)
        ->put(route('accounts.update', $account), validAccountPayload(['email' => $account->email]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

test('it rejects an update that uses the email address of another account', function () {
    $account = User::factory()->create();

    $this->actingAs($this->administrator)
        ->put(route('accounts.update', $account), validAccountPayload(['email' => $this->administrator->email]))
        ->assertSessionHasErrors('email');
});

test('it deletes an account', function () {
    $account = User::factory()->create();

    $this->actingAs($this->administrator)
        ->delete(route('accounts.destroy', $account))
        ->assertRedirect();

    $this->assertModelMissing($account);
});

test('it does not let an administrator delete their own account', function () {
    $this->actingAs($this->administrator)
        ->delete(route('accounts.destroy', $this->administrator))
        ->assertForbidden();

    $this->assertModelExists($this->administrator);
});

test('it resets the password of an account', function () {
    $account = User::factory()->create();
    $rememberToken = $account->remember_token;

    $this->actingAs($this->administrator)
        ->put(route('accounts.password.update', $account), [
            'password' => 'reset-password',
            'password_confirmation' => 'reset-password',
        ])
        ->assertRedirect();

    $account->refresh();

    expect(Hash::check('reset-password', $account->password))->toBeTrue()
        ->and($account->remember_token)->not->toBe($rememberToken);

    auth()->logout();

    $this->post(route('login.store'), ['email' => $account->email, 'password' => 'reset-password'])
        ->assertRedirect(route('home'));
});

test('it signs the account out of its sessions when its password is reset', function () {
    config(['session.driver' => 'database']);

    $account = User::factory()->create();

    DB::table('sessions')->insert([
        ['id' => 'account-session', 'user_id' => $account->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'other-session', 'user_id' => $this->administrator->id, 'payload' => '', 'last_activity' => time()],
    ]);

    $this->actingAs($this->administrator)
        ->put(route('accounts.password.update', $account), [
            'password' => 'reset-password',
            'password_confirmation' => 'reset-password',
        ]);

    expect(DB::table('sessions')->where('id', 'account-session')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'other-session')->exists())->toBeTrue();
});

test('it validates a password reset', function (array $payload) {
    $account = User::factory()->create();
    $passwordHash = $account->password;

    $this->actingAs($this->administrator)
        ->put(route('accounts.password.update', $account), $payload)
        ->assertSessionHasErrors('password');

    expect($account->refresh()->password)->toBe($passwordHash);
})->with([
    'missing password' => [[]],
    'unconfirmed password' => [['password' => 'reset-password', 'password_confirmation' => 'different']],
]);
