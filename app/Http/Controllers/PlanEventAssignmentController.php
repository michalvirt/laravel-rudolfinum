<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePlanEventAssignmentsRequest;
use App\Models\PlanEvent;
use Illuminate\Http\RedirectResponse;

class PlanEventAssignmentController extends Controller
{
    /**
     * Save the operators and engineers assigned to an event.
     */
    public function __invoke(UpdatePlanEventAssignmentsRequest $request, PlanEvent $planEvent): RedirectResponse
    {
        $planEvent->update($request->assignments());

        return back();
    }
}
