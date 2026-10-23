<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveEmailNotificationActionSettingRequest;
use App\Models\EmailNotificationActionSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EmailNotificationActionSettingController extends Controller
{
    /**
     * Save the name, sending mode, receiver permission and whether the notification is enabled.
     */
    public function __invoke(
        SaveEmailNotificationActionSettingRequest $request,
        EmailNotificationActionSetting $emailNotificationActionSetting,
    ): RedirectResponse {
        $emailNotificationActionSetting->update($request->validated());

        Inertia::flash('success', "Settings of {$emailNotificationActionSetting->label} were saved.");

        return back();
    }
}
