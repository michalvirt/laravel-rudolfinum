<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlanEventRequest;
use App\Models\PlanEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanEventController extends Controller
{
    /**
     * Add an event without an imported name to a day, so objects can be added to it.
     */
    public function store(StorePlanEventRequest $request): RedirectResponse
    {
        PlanEvent::create(['date' => $request->validated('date')]);

        Inertia::flash('success', 'Event was added.');

        return back();
    }

    /**
     * Show or hide the original (imported) text of an event in the plan calendar view.
     * Deciding about the text also clears the mark of an accepted change and the confirmations of the assigned users.
     */
    public function update(Request $request, PlanEvent $planEvent): RedirectResponse
    {
        $planEvent->update([
            ...$request->validate(['show_original_text' => ['required', 'boolean']]),
            'original_text_changed' => false,
        ]);
        $planEvent->changeAcknowledgedBy()->detach();

        return back();
    }

    /**
     * Remove an event that was added by hand. Imported events cannot be removed.
     */
    public function destroy(PlanEvent $planEvent): RedirectResponse
    {
        abort_if($planEvent->google_sheet_import_id !== null, 403, 'An imported event cannot be removed.');

        $planEvent->delete();

        Inertia::flash('success', 'Event was removed.');

        return back();
    }
}
