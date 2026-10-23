<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PlanMonthSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How a month of the plan calendar is shown. A month without a setting hides its operators and engineers in the plan calendar view.
 *
 * @property string $id
 * @property string $month formatted as Y-m
 * @property bool $staff_visible whether the operators and engineers are shown in the plan calendar view
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['month', 'staff_visible'])]
class PlanMonthSetting extends Model
{
    /** @use HasFactory<PlanMonthSettingFactory> */
    use HasFactory, HasUuids;

    /**
     * Determine whether the operators and engineers of the month holding a date are shown in the plan calendar view.
     */
    public static function isStaffVisibleOn(CarbonInterface $date): bool
    {
        return (bool) self::where('month', $date->format('Y-m'))->value('staff_visible');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'staff_visible' => 'boolean',
        ];
    }
}
