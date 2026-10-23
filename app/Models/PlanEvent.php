<?php

namespace App\Models;

use Database\Factories\PlanEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property Carbon $date
 * @property string|null $name
 * @property bool $show_original_text
 * @property bool $original_text_changed
 * @property string|null $google_sheet_import_id
 * @property string|null $operator_1
 * @property string|null $operator_2
 * @property string|null $operator_3
 * @property string|null $engineer_1
 * @property string|null $engineer_2
 * @property string|null $engineer_3
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GoogleSheetImport|null $googleSheetImport
 * @property-read Collection<int, PlanEventObject> $objects
 * @property-read Collection<int, User> $changeAcknowledgedBy
 */
#[Fillable([
    'date', 'name', 'show_original_text', 'original_text_changed', 'google_sheet_import_id',
    'operator_1', 'operator_2', 'operator_3', 'engineer_1', 'engineer_2', 'engineer_3',
])]
class PlanEvent extends Model
{
    /** @use HasFactory<PlanEventFactory> */
    use HasFactory, HasUuids;

    /**
     * The columns holding the operators of the event, in the order they are filled.
     *
     * @var list<string>
     */
    public const array OPERATOR_COLUMNS = ['operator_1', 'operator_2', 'operator_3'];

    /**
     * The columns holding the engineers of the event, in the order they are filled.
     *
     * @var list<string>
     */
    public const array ENGINEER_COLUMNS = ['engineer_1', 'engineer_2', 'engineer_3'];

    /**
     * The columns holding the user assigned to a position of the event.
     *
     * @var list<string>
     */
    public const array ASSIGNMENT_COLUMNS = [...self::OPERATOR_COLUMNS, ...self::ENGINEER_COLUMNS];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'show_original_text' => 'boolean',
            'original_text_changed' => 'boolean',
        ];
    }

    /**
     * The imported Google Sheet row this plan event is paired with.
     *
     * @return BelongsTo<GoogleSheetImport, $this>
     */
    public function googleSheetImport(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetImport::class);
    }

    /**
     * The objects shown behind the name of the event, in the order chosen by the planner.
     *
     * @return HasMany<PlanEventObject, $this>
     */
    public function objects(): HasMany
    {
        return $this->hasMany(PlanEventObject::class)->orderBy('position')->orderBy('created_at')->orderBy('id');
    }

    /**
     * The assigned users who confirmed that they know about the accepted change of the original text.
     *
     * @return BelongsToMany<User, $this>
     */
    public function changeAcknowledgedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'plan_event_change_acknowledgements')->withTimestamps();
    }

    /**
     * Determine whether the user holds any operator or engineer position of the event.
     */
    public function isAssignedTo(User $user): bool
    {
        return collect(self::ASSIGNMENT_COLUMNS)->contains(fn (string $column): bool => $this->{$column} === $user->id);
    }
}
