<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowCalendarRequest;
use App\Services\PlanCalendarBuilder;
use Inertia\Inertia;
use Inertia\Response;

class PlanCalendarEditController extends Controller
{
    /**
     * Show every day of the requested month with the controls to edit its events.
     */
    public function __invoke(ShowCalendarRequest $request, PlanCalendarBuilder $builder): Response
    {
        return Inertia::render('plan-calendar-edit', [
            ...$builder->month($request->query('month'), revealStaff: true),
            'categories' => $builder->categories(),
            'staff' => $builder->staff(),
        ]);
    }
}
