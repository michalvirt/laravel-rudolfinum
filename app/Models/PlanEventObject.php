<?php

namespace App\Models;

use Database\Factories\PlanEventObjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A text with a category shown behind the name of a plan event.
 *
 * @property string $id
 * @property string $plan_event_id
 * @property string $plan_event_category_id
 * @property string $text
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PlanEvent $planEvent
 * @property-read PlanEventCategory $category
 */
#[Fillable(['plan_event_id', 'plan_event_category_id', 'text', 'position'])]
class PlanEventObject extends Model
{
    /** @use HasFactory<PlanEventObjectFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<PlanEvent, $this>
     */
    public function planEvent(): BelongsTo
    {
        return $this->belongsTo(PlanEvent::class);
    }

    /**
     * @return BelongsTo<PlanEventCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PlanEventCategory::class, 'plan_event_category_id');
    }
}
