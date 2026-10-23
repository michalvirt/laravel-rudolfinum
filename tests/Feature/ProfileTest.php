<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create(['password' => 'current-password']);
});

/**
 * @param  array<string, string|null>  $overrides
 * @return array<string, string|null>
 */
function validProfilePayload(array $overrides = []): array
{
    return [
        'name' => 'Jana',
        'surname' => 'Nováková',
        'email' => 'jana.novakova@example.com',
        'phone' => '+420 123 456 789',
        ...$overrides,
    ];
}

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function validProfilePasswordPayload(array $overrides = []): array
{
    return [
        'current_password' => 'current-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
        ...$overrides,
    ];
}

test('it redirects guests to the login form', function () {
    $this->put(route('profile.update'), validProfilePayload())->assertRedirect(route('login'));
    $this->put(route('profile.password.update'), validProfilePasswordPayload())->assertRedirect(route('login'));
});

test('it shows the profile of the signed in user on the welcome page', function () {
    $this->actingAs($this->user)
        ->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->where('auth.user.email', $this->user->email)
            ->where('auth.user.phone', $this->user->phone));
});

test('it updates the profile of the signed in user', function () {
    $this->actingAs($this->user)
        ->put(route('profile.update'), validProfilePayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->user->refresh())
        ->name->toBe('Jana')
        ->surname->toBe('Nováková')
        ->email->toBe('jana.novakova@example.com')
        ->phone->toBe('+420 123 456 789');
});

test('it clears the phone of the signed in user', function () {
    $this->actingAs($this->user)
        ->put(route('profile.update'), validProfilePayload(['phone' => null]))
        ->assertSessionHasNoErrors();

    expect($this->user->refresh()->phone)->toBeNull();
});

test('it only updates the profile of the signed in user', function () {
    $other = User::factory()->create();

    $this->actingAs($this->user)
        ->put(route('profile.update'), validProfilePayload(['id' => $other->id]));

    expect($other->refresh()->email)->not->toBe('jana.novakova@example.com');
});

test('it does not let the profile change the permission group or permissions', function () {
    $permissionGroupId = $this->user->permission_group_id;

    $this->actingAs($this->user)
        ->put(route('profile.update'), validProfilePayload([
            'permission_group_id' => 'anything',
            'permissions' => ['accounts.manage'],
        ]))
        ->assertSessionHasNoErrors();

    expect($this->user->refresh()->permission_group_id)->toBe($permissionGroupId)
        ->and($this->user->permissions->all())->toBe([]);
});

test('it validates the profile', function (array $overrides, string $field) {
    $this->actingAs($this->user)
        ->put(route('profile.update'), validProfilePayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'missing surname' => [['surname' => ''], 'surname'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'missing email' => [['email' => ''], 'email'],
]);

test('it lets the user keep their own email address', function () {
    $this->actingAs($this->user)
        ->put(route('profile.update'), validProfilePayload(['email' => $this->user->email]))
        ->assertSessionHasNoErrors();
});

test('it rejects an email address that is used by another account', function () {
    $other = User::factory()->create();

    $this->actingAs($this->user)
        ->put(route('profile.update'), validProfilePayload(['email' => $other->email]))
        ->assertSessionHasErrors('email');

    expect($this->user->refresh()->email)->not->toBe($other->email);
});

test('it changes the password of the signed in user', function () {
    $rememberToken = $this->user->remember_token;

    $this->actingAs($this->user)
        ->put(route('profile.password.update'), validProfilePasswordPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->user->refresh();

    expect(Hash::check('new-password', $this->user->password))->toBeTrue()
        ->and($this->user->remember_token)->not->toBe($rememberToken);
});

test('it keeps the current session and signs out the other sessions when the password is changed', function () {
    config(['session.driver' => 'database']);

    DB::table('sessions')->insert([
        ['id' => 'other-session', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'foreign-session', 'user_id' => User::factory()->create()->id, 'payload' => '', 'last_activity' => time()],
    ]);

    $this->actingAs($this->user)
        ->put(route('profile.password.update'), validProfilePasswordPayload());

    expect(DB::table('sessions')->where('id', 'other-session')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'foreign-session')->exists())->toBeTrue();
});

test('it validates a password change', function (array $overrides, string $field) {
    $this->actingAs($this->user)
        ->put(route('profile.password.update'), validProfilePasswordPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(Hash::check('current-password', $this->user->refresh()->password))->toBeTrue();
})->with([
    'missing current password' => [['current_password' => ''], 'current_password'],
    'wrong current password' => [['current_password' => 'wrong-password'], 'current_password'],
    'missing password' => [['password' => '', 'password_confirmation' => ''], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'different'], 'password'],
]);
