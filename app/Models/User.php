<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property string $name
 * @property string $surname
 * @property string $email
 * @property string|null $phone
 * @property string|null $permission_group_id
 * @property Collection<int, Permission>|null $permissions Permissions granted to the user on top of the group's
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PermissionGroup|null $permissionGroup
 */
#[Fillable(['name', 'surname', 'email', 'phone', 'password', 'permission_group_id', 'permissions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * The default values of the attributes of a new user.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'permissions' => '[]',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => AsEnumCollection::of(Permission::class),
        ];
    }

    /**
     * @return BelongsTo<PermissionGroup, $this>
     */
    public function permissionGroup(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class);
    }

    /**
     * The days the user has time and can go to work.
     *
     * @return HasMany<PlanAvailability, $this>
     */
    public function planAvailabilities(): HasMany
    {
        return $this->hasMany(PlanAvailability::class);
    }

    /**
     * The plan events whose accepted change of the original text the user confirmed.
     *
     * @return BelongsToMany<PlanEvent, $this>
     */
    public function planEventChangeAcknowledgements(): BelongsToMany
    {
        return $this->belongsToMany(PlanEvent::class, 'plan_event_change_acknowledgements')->withTimestamps();
    }

    /**
     * The name and the surname of the user.
     */
    public function fullName(): string
    {
        return "{$this->name} {$this->surname}";
    }

    /**
     * Determine whether the user or the user's permission group grants the given permission.
     */
    public function hasPermission(Permission $permission): bool
    {
        return $this->permissions?->contains($permission)
            || ($this->permissionGroup?->grants($permission) ?? false);
    }

    /**
     * Get the values of every permission granted to the user, directly or by the permission group.
     *
     * @return list<string>
     */
    public function permissionValues(): array
    {
        $permissions = [
            ...($this->permissionGroup?->permissions->all() ?? []),
            ...($this->permissions?->all() ?? []),
        ];

        return array_values(array_unique(array_map(
            fn (Permission $permission): string => $permission->value,
            $permissions,
        )));
    }
}
