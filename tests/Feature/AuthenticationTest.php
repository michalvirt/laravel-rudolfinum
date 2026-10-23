<?php

use App\Models\PermissionGroup;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

test('it shows the login form to guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('login'));
});

test('it redirects authenticated users away from the login form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('home'));
});

test('it logs a user in with their email address and password', function () {
    $user = User::factory()->create(['email' => 'jana.novakova@example.com', 'password' => 'password']);

    $this->post(route('login.store'), ['email' => 'jana.novakova@example.com', 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('it redirects to the originally requested page after login', function () {
    $user = User::factory()->create();

    $this->get(route('calendar'))->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('calendar'));
});

test('it rejects a wrong password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('it rejects an unknown email address', function () {
    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('it validates the login credentials', function (array $payload, string $field) {
    $this->post(route('login.store'), $payload)->assertSessionHasErrors($field);
})->with([
    'missing email' => [['password' => 'password'], 'email'],
    'invalid email' => [['email' => 'not-an-email', 'password' => 'password'], 'email'],
    'missing password' => [['email' => 'user@example.com'], 'password'],
]);

test('it throttles repeated failed login attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();

    RateLimiter::clear(strtolower($user->email).'|127.0.0.1');
});

test('it logs a user out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('it does not let guests log out', function () {
    $this->post(route('logout'))->assertRedirect(route('login'));
});

test('it shares the authenticated user and permissions with the frontend', function () {
    $user = User::factory()->for(PermissionGroup::factory()->planners())->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
            ->where('auth.user.permission_group', 'Planners')
            ->where('auth.user.permissions', ['calendar.view', 'plan-calendar.view', 'plan-calendar.edit', 'planEventChange.notification'])
            ->missing('auth.user.password')
        );
});

test('it shares no user with guests', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});
