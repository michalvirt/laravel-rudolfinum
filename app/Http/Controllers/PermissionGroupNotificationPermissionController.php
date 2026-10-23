<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\UpdatePermissionGroupNotificationPermissionsRequest;
use App\Models\PermissionGroup;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PermissionGroupNotificationPermissionController extends Controller
{
    /**
     * Replace the notification permissions of a group. Its other permissions stay untouched.
     */
    public function __invoke(
        UpdatePermissionGroupNotificationPermissionsRequest $request,
        PermissionGroup $permissionGroup,
    ): RedirectResponse {
        $otherPermissions = $permissionGroup->permissions
            ->reject(fn (Permission $permission): bool => $permission->isNotification())
            ->all();

        $permissionGroup->update([
            'permissions' => [...$otherPermissions, ...$request->permissions()],
        ]);

        Inertia::flash('success', "Notification permissions of group {$permissionGroup->name} were saved.");

        return back();
    }
}
