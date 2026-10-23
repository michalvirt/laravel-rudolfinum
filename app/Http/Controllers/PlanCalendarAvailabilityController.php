<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePlanAvailabilityRequest;
use Illuminate\Http\RedirectResponse;

class PlanCalendarAvailabilityController extends Controller
{
    /**
     * Mark a day as one the logged in user has time to work, or clear the mark again.
     *
     * Selecting a day that is already selected keeps its original timestamp, so the order is kept.
     */
    public function __invoke(UpdatePlanAvailabilityRequest $request): RedirectResponse
    {
        $user = $request->user();
        $date = $request->validated('date');
        $selected = $user->planAvailabilities()->whereDate('date', $date);

        if (! $request->boolean('available')) {
            $selected->delete();
        } elseif (! $selected->exists()) {
            $user->planAvailabilities()->create(['date' => $date]);
        }

        return back();
    }
}
