<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePlanEventCategoryRequest;
use App\Models\PlanEventCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlanEventCategoryController extends Controller
{
    /**
     * List the categories together with the number of objects using them.
     */
    public function index(): Response
    {
        return Inertia::render('plan-categories', [
            'categories' => PlanEventCategory::withCount('objects')
                ->orderBy('label')
                ->get()
                ->map(fn (PlanEventCategory $category): array => [
                    'id' => $category->id,
                    'label' => $category->label,
                    'background_color' => $category->background_color,
                    'font_color' => $category->font_color,
                    'objectsCount' => (int) $category->getAttribute('objects_count'),
                ])
                ->all(),
        ]);
    }

    /**
     * Create a category.
     */
    public function store(SavePlanEventCategoryRequest $request): RedirectResponse
    {
        $category = PlanEventCategory::create($request->validated());

        Inertia::flash('success', "Category {$category->label} was created.");

        return back();
    }

    /**
     * Update the label and colors of a category.
     */
    public function update(SavePlanEventCategoryRequest $request, PlanEventCategory $planEventCategory): RedirectResponse
    {
        $planEventCategory->update($request->validated());

        Inertia::flash('success', "Category {$planEventCategory->label} was updated.");

        return back();
    }

    /**
     * Delete a category that no object uses.
     *
     * @throws ValidationException
     */
    public function destroy(PlanEventCategory $planEventCategory): RedirectResponse
    {
        $objectsCount = $planEventCategory->objects()->count();

        if ($objectsCount > 0) {
            throw ValidationException::withMessages([
                'category' => "Category {$planEventCategory->label} is used by {$objectsCount} objects. Remove or change them first.",
            ]);
        }

        $planEventCategory->delete();

        Inertia::flash('success', "Category {$planEventCategory->label} was deleted.");

        return back();
    }
}
