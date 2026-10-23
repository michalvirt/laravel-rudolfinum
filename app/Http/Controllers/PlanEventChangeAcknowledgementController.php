<?php

namespace App\Http\Controllers;

use App\Models\PlanEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlanEventChangeAcknowledgementController extends Controller
{
    /**
     * Let a user assigned to an event confirm that they know about the accepted change of its text.
     * Only the logged in user is affected, the mark stays for the planners and the other assigned users.
     */
    public function __invoke(Request $request, PlanEvent $planEvent): RedirectResponse
    {
        $user = $request->user();

        abort_unless($planEvent->isAssignedTo($user), 403, 'Only a user assigned to the event can acknowledge its change.');

        if ($planEvent->original_text_changed) {
            $planEvent->changeAcknowledgedBy()->syncWithoutDetaching([$user->id]);
        }

        return back();
    }
}
