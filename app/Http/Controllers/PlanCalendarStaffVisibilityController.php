<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePlanMonthStaffVisibilityRequest;
use App\Models\PlanMonthSetting;
use Illuminate\Http\RedirectResponse;

class PlanCalendarStaffVisibilityController extends Controller
{
    /**
     * Show or hide the operators and engineers of a month in the plan calendar view.
     */
    public function __invoke(UpdatePlanMonthStaffVisibilityRequest $request): RedirectResponse
    {
        PlanMonthSetting::updateOrCreate(
            ['month' => $request->validated('month')],
            ['staff_visible' => $request->boolean('visible')],
        );

        return back();
    }
}
