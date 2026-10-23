<?php

namespace App\Models;

use Database\Factories\PlanEventCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $label
 * @property string $background_color
 * @property string $font_color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['label', 'background_color', 'font_color'])]
class PlanEventCategory extends Model
{
    /** @use HasFactory<PlanEventCategoryFactory> */
    use HasFactory, HasUuids;

    /**
     * @return HasMany<PlanEventObject, $this>
     */
    public function objects(): HasMany
    {
        return $this->hasMany(PlanEventObject::class);
    }
}
