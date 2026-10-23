<?php

namespace App\Models;

use Database\Factories\GoogleSheetImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Stores the accepted values of one row of a Google Sheet tab. Differences found by later
 * imports are kept in `changed_fields` (field => value now in the sheet) until accepted.
 *
 * @property string $id
 * @property string $spreadsheet_id
 * @property string $sheet_name
 * @property int $row_number
 * @property Carbon $date
 * @property string|null $weekday
 * @property string|null $service
 * @property string|null $name
 * @property string|null $comment
 * @property string|null $operator_1
 * @property string|null $operator_2
 * @property string|null $htl_1
 * @property string|null $htl_2
 * @property array<string, string|null>|null $changed_fields
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PlanEvent|null $planEvent
 */
#[Table('google_sheet_import')]
#[Fillable([
    'spreadsheet_id', 'sheet_name', 'row_number', 'date', 'weekday', 'service', 'name', 'comment',
    'operator_1', 'operator_2', 'htl_1', 'htl_2', 'changed_fields', 'last_checked_at',
])]
class GoogleSheetImport extends Model
{
    /** @use HasFactory<GoogleSheetImportFactory> */
    use HasFactory, HasUuids;

    /**
     * The imported fields that are compared with the sheet on every import.
     *
     * @var list<string>
     */
    public const COMPARED_FIELDS = [
        'weekday', 'service', 'name', 'comment', 'operator_1', 'operator_2', 'htl_1', 'htl_2',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'changed_fields' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<PlanEvent, $this>
     */
    public function planEvent(): HasOne
    {
        return $this->hasOne(PlanEvent::class);
    }

    /**
     * Determine whether the sheet differs from the accepted values.
     */
    public function hasPendingChanges(): bool
    {
        return $this->changed_fields !== null && $this->changed_fields !== [];
    }
}
