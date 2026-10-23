<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderPlanEventObjectsRequest;
use App\Models\PlanEvent;
use App\Models\PlanEventObject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PlanEventObjectOrderController extends Controller
{
    /**
     * Save the order in which the objects of an event are shown.
     */
    public function __invoke(ReorderPlanEventObjectsRequest $request, PlanEvent $planEvent): RedirectResponse
    {
        DB::transaction(function () use ($request, $planEvent): void {
            foreach ($request->orderedIds() as $position => $id) {
                PlanEventObject::where('plan_event_id', $planEvent->id)
                    ->whereKey($id)
                    ->update(['position' => $position]);
            }
        });

        return back();
    }
}
