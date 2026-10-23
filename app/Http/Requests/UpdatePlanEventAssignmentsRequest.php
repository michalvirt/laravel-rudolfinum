<?php

namespace App\Http\Requests;

use App\Models\PlanEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePlanEventAssignmentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return collect(PlanEvent::ASSIGNMENT_COLUMNS)
            ->mapWithKeys(fn (string $column): array => [$column => ['present', 'nullable', 'string', 'exists:users,id']])
            ->all();
    }

    /**
     * Get the after validation callbacks: a user can take only one position of one event a day
     * and a position can be filled only after the one before it.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $assigned = [];

                foreach (PlanEvent::ASSIGNMENT_COLUMNS as $column) {
                    $userId = $this->input($column);

                    if ($userId === null) {
                        continue;
                    }

                    if (isset($assigned[$userId])) {
                        $validator->errors()->add($column, 'A user can be assigned to only one position of an event.');

                        continue;
                    }

                    $assigned[$userId] = true;
                }

                $this->rejectUsersWorkingOnAnotherEventOfTheDay($validator);

                foreach ([PlanEvent::OPERATOR_COLUMNS, PlanEvent::ENGINEER_COLUMNS] as $columns) {
                    foreach ($columns as $index => $column) {
                        if ($index > 0 && $this->input($column) !== null && $this->input($columns[$index - 1]) === null) {
                            $validator->errors()->add($column, 'Select the previous position first.');
                        }
                    }
                }
            },
        ];
    }

    /**
     * Reject every user who is already assigned to another event on the same day.
     */
    private function rejectUsersWorkingOnAnotherEventOfTheDay(Validator $validator): void
    {
        /** @var PlanEvent $planEvent */
        $planEvent = $this->route('planEvent');
        $userIds = array_values(array_filter($this->assignments()));

        if ($userIds === []) {
            return;
        }

        $busyUserIds = PlanEvent::whereDate('date', $planEvent->date->toDateString())
            ->whereKeyNot($planEvent->id)
            ->where(function (Builder $query) use ($userIds): void {
                foreach (PlanEvent::ASSIGNMENT_COLUMNS as $column) {
                    $query->orWhereIn($column, $userIds);
                }
            })
            ->get()
            ->flatMap(fn (PlanEvent $other): array => array_map(
                fn (string $column): ?string => $other->{$column},
                PlanEvent::ASSIGNMENT_COLUMNS,
            ))
            ->filter()
            ->all();

        foreach ($this->assignments() as $column => $userId) {
            if ($userId !== null && in_array($userId, $busyUserIds, true)) {
                $validator->errors()->add($column, 'This user is already assigned to another event on the same day.');
            }
        }
    }

    /**
     * The user assigned to every position of the event.
     *
     * @return array<string, string|null>
     */
    public function assignments(): array
    {
        return collect(PlanEvent::ASSIGNMENT_COLUMNS)
            ->mapWithKeys(fn (string $column): array => [$column => $this->input($column)])
            ->all();
    }
}
