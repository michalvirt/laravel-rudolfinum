<?php

use App\Models\PermissionGroup;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

test('it seeds the permission groups and eight users', function () {
    $this->seed(DatabaseSeeder::class);

    expect(PermissionGroup::pluck('name')->all())->toEqualCanonicalizing(['Administrators', 'Planners', 'Viewers'])
        ->and(User::count())->toBe(8);

    User::with('permissionGroup')->each(function (User $user) {
        expect(Str::isUuid($user->id))->toBeTrue()
            ->and($user->name)->not->toBeEmpty()
            ->and($user->surname)->not->toBeEmpty()
            ->and($user->email)->not->toBeEmpty()
            ->and($user->phone)->not->toBeEmpty()
            ->and($user->permissionGroup)->not->toBeNull()
            ->and(Hash::check('password', $user->password))->toBeTrue();
    });
});

test('it does not duplicate groups or users when seeded again', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(PermissionGroup::count())->toBe(3)
        ->and(User::count())->toBe(8);
});

test('it identifies models by uuid', function () {
    $group = PermissionGroup::factory()->create();
    $user = User::factory()->for($group)->create();

    expect(Str::isUuid($user->id))->toBeTrue()
        ->and(Str::isUuid($group->id))->toBeTrue()
        ->and($user->permission_group_id)->toBe($group->id);
});
