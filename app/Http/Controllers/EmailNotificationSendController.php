<?php

namespace App\Http\Controllers;

use App\Models\EmailNotificationLog;
use App\Services\EmailNotifier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EmailNotificationSendController extends Controller
{
    /**
     * Send one notification that is waiting or whose send failed.
     */
    public function one(EmailNotificationLog $emailNotificationLog, EmailNotifier $notifier): RedirectResponse
    {
        if ($emailNotificationLog->is_sent) {
            Inertia::flash('warning', "Notification for {$emailNotificationLog->receiver_email} was already sent.");

            return back();
        }

        if ($notifier->send($emailNotificationLog)) {
            Inertia::flash('success', "Notification for {$emailNotificationLog->receiver_email} was sent.");
        } else {
            Inertia::flash('warning', "Notification for {$emailNotificationLog->receiver_email} could not be sent: {$emailNotificationLog->error_message}");
        }

        return back();
    }

    /**
     * Send every notification that is waiting for the next batch.
     */
    public function pending(EmailNotifier $notifier): RedirectResponse
    {
        Inertia::flash('success', "Sent {$notifier->sendPending()} waiting notifications.");

        return back();
    }

    /**
     * Send every notification whose send failed again.
     */
    public function failed(EmailNotifier $notifier): RedirectResponse
    {
        Inertia::flash('success', "Sent {$notifier->resendFailed()} failed notifications again.");

        return back();
    }
}
