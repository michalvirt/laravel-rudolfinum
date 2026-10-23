<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\PermissionGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * List every account together with the permission groups that can be assigned and the notification
     * permissions that can be set on accounts and groups.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('accounts', [
            'accounts' => User::with('permissionGroup')
                ->orderBy('surname')
                ->orderBy('name')
                ->get()
                ->map(fn (User $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'surname' => $account->surname,
                    'email' => $account->email,
                    'phone' => $account->phone,
                    'permission_group_id' => $account->permission_group_id,
                    'permission_group' => $account->permissionGroup?->name,
                    'notification_permissions' => $this->notificationPermissionValues($account->permissions),
                    'isCurrentUser' => $account->is($request->user()),
                ])
                ->all(),
            'permissionGroups' => PermissionGroup::orderBy('name')
                ->get()
                ->map(fn (PermissionGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'notification_permissions' => $this->notificationPermissionValues($group->permissions),
                ])
                ->all(),
            'notificationPermissions' => array_map(fn (Permission $permission): array => [
                'value' => $permission->value,
                'label' => $permission->label(),
            ], Permission::notifications()),
        ]);
    }

    /**
     * Create a new account.
     */
    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = User::create($request->validated());

        Inertia::flash('success', "Account {$account->fullName()} was created.");

        return back();
    }

    /**
     * Update the profile and permission group of an account.
     */
    public function update(UpdateAccountRequest $request, User $account): RedirectResponse
    {
        $account->update($request->validated());

        Inertia::flash('success', "Account {$account->fullName()} was updated.");

        return back();
    }

    /**
     * Delete an account. Administrators cannot delete their own account.
     */
    public function destroy(Request $request, User $account): RedirectResponse
    {
        abort_if($account->is($request->user()), 403, 'You cannot delete your own account.');

        $account->delete();

        Inertia::flash('success', "Account {$account->fullName()} was deleted.");

        return back();
    }

    /**
     * The values of the notification permissions in a list of permissions.
     *
     * @param  Collection<int, Permission>|null  $permissions
     * @return list<string>
     */
    private function notificationPermissionValues(?Collection $permissions): array
    {
        $notifications = array_filter(
            $permissions?->all() ?? [],
            fn (Permission $permission): bool => $permission->isNotification(),
        );

        return array_values(array_map(fn (Permission $permission): string => $permission->value, $notifications));
    }
}
