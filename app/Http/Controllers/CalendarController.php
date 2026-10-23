<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowCalendarRequest;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Show the calendar for the requested month (defaults to the current month).
     */
    public function __invoke(ShowCalendarRequest $request): Response
    {
        $today = CarbonImmutable::today();
        $month = $request->filled('month')
            ? CarbonImmutable::createFromFormat('!Y-m', $request->string('month')->toString())
            : $today->startOfMonth();

        $gridStart = $month->startOfWeek();
        $gridEnd = $month->endOfMonth()->endOfWeek();

        $days = [];
        for ($day = $gridStart; $day <= $gridEnd; $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'day' => $day->day,
                'isCurrentMonth' => $day->isSameMonth($month),
                'isToday' => $day->isSameDay($today),
            ];
        }

        return Inertia::render('calendar', [
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->translatedFormat('F Y'),
            'previousMonth' => $month->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $month->addMonthNoOverflow()->format('Y-m'),
            'currentMonth' => $today->format('Y-m'),
            'weekdays' => collect(range(0, 6))
                ->map(fn (int $offset): string => $gridStart->addDays($offset)->translatedFormat('D'))
                ->all(),
            'weeks' => array_chunk($days, 7),
        ]);
    }
}
