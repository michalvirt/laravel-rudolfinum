<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePlanEventObjectRequest;
use App\Models\PlanEventObject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlanEventObjectController extends Controller
{
    /**
     * Add an object to an event.
     */
    public function store(SavePlanEventObjectRequest $request): RedirectResponse
    {
        $position = PlanEventObject::where('plan_event_id', $request->validated('plan_event_id'))->max('position');

        PlanEventObject::create([...$request->validated(), 'position' => is_numeric($position) ? (int) $position + 1 : 0]);

        Inertia::flash('success', 'Object was added.');

        return back();
    }

    /**
     * Change the text or the category of an object.
     */
    public function update(SavePlanEventObjectRequest $request, PlanEventObject $planEventObject): RedirectResponse
    {
        $planEventObject->update($request->safe()->only(['plan_event_category_id', 'text']));

        Inertia::flash('success', 'Object was updated.');

        return back();
    }

    /**
     * Remove an object from an event.
     */
    public function destroy(PlanEventObject $planEventObject): RedirectResponse
    {
        $planEventObject->delete();

        Inertia::flash('success', 'Object was removed.');

        return back();
    }
}
