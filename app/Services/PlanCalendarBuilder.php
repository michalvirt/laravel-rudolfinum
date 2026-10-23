<?php

namespace App\Services;

use App\Models\PlanAvailability;
use App\Models\PlanEvent;
use App\Models\PlanEventCategory;
use App\Models\PlanEventObject;
use App\Models\PlanMonthSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PlanCalendarBuilder
{
    /**
     * Describe every day of a month (the current month by default) with its plan events and
     * the users who have time to work on it, the ones who selected the day first at the top.
     *
     * The operators and engineers of a month are left out unless the planner showed them or `$revealStaff` is set.
     * An event awaits the acknowledgement of the `$viewer` when its original text was changed by an accepted
     * change, the viewer is assigned to it and has not confirmed the change yet.
     *
     * @param  string|null  $requestedMonth  a month formatted as Y-m
     * @param  bool  $revealStaff  whether to keep the operators and engineers of a month that is not shown in the view
     * @param  User|null  $viewer  the user who looks at the calendar
     * @return array{month: string, monthLabel: string, previousMonth: string, nextMonth: string, currentMonth: string, staffVisible: bool, days: list<array<string, mixed>>}
     */
    public function month(?string $requestedMonth, bool $revealStaff = false, ?User $viewer = null): array
    {
        $today = CarbonImmutable::today();
        $month = filled($requestedMonth)
            ? CarbonImmutable::createFromFormat('!Y-m', $requestedMonth) ?: $today->startOfMonth()
            : $today->startOfMonth();

        $staffVisible = PlanMonthSetting::where('month', $month->format('Y-m'))->value('staff_visible') ?? false;
        $showStaff = $staffVisible || $revealStaff;

        $events = PlanEvent::whereDate('date', '>=', $month->toDateString())
            ->whereDate('date', '<=', $month->endOfMonth()->toDateString())
            ->with(['googleSheetImport', 'objects.category'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $acknowledgedEventIds = $viewer === null
            ? []
            : $viewer->planEventChangeAcknowledgements()->whereIn('plan_events.id', $events->modelKeys())->pluck('plan_events.id')->all();

        $events = $events->groupBy(fn (PlanEvent $event): string => $event->date->toDateString());

        $availableUserIds = PlanAvailability::whereDate('date', '>=', $month->toDateString())
            ->whereDate('date', '<=', $month->endOfMonth()->toDateString())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (PlanAvailability $availability): string => $availability->date->toDateString())
            ->map(fn (Collection $availabilities): array => $availabilities->pluck('user_id')->all());

        $days = [];
        for ($day = $month; $day <= $month->endOfMonth(); $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'day' => $day->day,
                'weekday' => $day->translatedFormat('l'),
                'isWeekend' => $day->isWeekend(),
                'isToday' => $day->isSameDay($today),
                'availableUserIds' => $availableUserIds->get($day->toDateString(), []),
                'events' => $events->get($day->toDateString(), collect())
                    ->map(fn (PlanEvent $event): array => $this->event(
                        $event,
                        $showStaff,
                        $viewer !== null
                            && $event->original_text_changed
                            && $event->isAssignedTo($viewer)
                            && ! in_array($event->id, $acknowledgedEventIds, true),
                    ))
                    ->all(),
            ];
        }

        return [
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->translatedFormat('F Y'),
            'previousMonth' => $month->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $month->addMonthNoOverflow()->format('Y-m'),
            'currentMonth' => $today->format('Y-m'),
            'staffVisible' => (bool) $staffVisible,
            'days' => $days,
        ];
    }

    /**
     * List the categories an object can be assigned to.
     *
     * @return list<array{id: string, label: string, background_color: string, font_color: string}>
     */
    public function categories(): array
    {
        return array_values(array_map(
            fn (PlanEventCategory $category): array => $this->category($category),
            PlanEventCategory::orderBy('label')->get()->all(),
        ));
    }

    /**
     * List the users who can be assigned as an operator or an engineer of an event.
     *
     * @return list<array{id: string, name: string, surname: string}>
     */
    public function staff(): array
    {
        return array_values(array_map(
            fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'surname' => $user->surname],
            User::orderBy('surname')->orderBy('name')->get()->all(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function event(PlanEvent $event, bool $showStaff, bool $awaitsChangeAcknowledgement): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'showOriginalText' => $event->show_original_text,
            'originalTextChanged' => $event->original_text_changed,
            'awaitsChangeAcknowledgement' => $awaitsChangeAcknowledgement,
            'isImported' => $event->google_sheet_import_id !== null,
            'hasChanges' => $event->googleSheetImport?->hasPendingChanges() ?? false,
            'changedFields' => array_keys($event->googleSheetImport->changed_fields ?? []),
            'changedName' => $event->googleSheetImport->changed_fields['name'] ?? null,
            'assignments' => collect(PlanEvent::ASSIGNMENT_COLUMNS)
                ->mapWithKeys(fn (string $column): array => [$column => $showStaff ? $event->{$column} : null])
                ->all(),
            'objects' => $event->objects
                ->map(fn (PlanEventObject $object): array => [
                    'id' => $object->id,
                    'text' => $object->text,
                    'plan_event_category_id' => $object->plan_event_category_id,
                    'category' => $this->category($object->category),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{id: string, label: string, background_color: string, font_color: string}
     */
    private function category(PlanEventCategory $category): array
    {
        return [
            'id' => $category->id,
            'label' => $category->label,
            'background_color' => $category->background_color,
            'font_color' => $category->font_color,
        ];
    }
}
