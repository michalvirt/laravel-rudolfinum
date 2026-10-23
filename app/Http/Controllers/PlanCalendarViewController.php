<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowCalendarRequest;
use App\Services\PlanCalendarBuilder;
use Inertia\Inertia;
use Inertia\Response;

class PlanCalendarViewController extends Controller
{
    /**
     * Show every day of the requested month (defaults to the current month) as a row.
     */
    public function __invoke(ShowCalendarRequest $request, PlanCalendarBuilder $builder): Response
    {
        return Inertia::render('plan-calendar-view', [
            ...$builder->month($request->query('month'), viewer: $request->user()),
            'staff' => $builder->staff(),
        ]);
    }
}
