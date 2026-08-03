<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Builds a month grid for dashboard calendars (markers keyed by Y-m-d).
 */
class MonthCalendarBuilder
{
    /**
     * @param  array<string, list<array{kind: string, label: string}>>  $markersByDate
     * @return array{
     *     year: int,
     *     month: int,
     *     cursor: string,
     *     label: string,
     *     weekdays: list<string>,
     *     cells: list<array{date: ?string, day: ?int, inMonth: bool, isToday: bool, markers: list<array{kind: string, label: string}>}>
     * }
     */
    public function build(?CarbonInterface $month = null, array $markersByDate = []): array
    {
        $cursor = Carbon::parse($month ?? now())->startOfMonth();
        $today = now()->toDateString();

        $weekdays = [];
        $probe = $cursor->copy()->startOfWeek();
        for ($i = 0; $i < 7; $i++) {
            $weekdays[] = $probe->copy()->addDays($i)->translatedFormat('D');
        }

        $gridStart = $cursor->copy()->startOfWeek();
        $gridEnd = $cursor->copy()->endOfMonth()->endOfWeek();

        $cells = [];
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $date = $day->toDateString();
            $inMonth = $day->month === $cursor->month;

            $cells[] = [
                'date' => $inMonth ? $date : null,
                'day' => $inMonth ? $day->day : null,
                'inMonth' => $inMonth,
                'isToday' => $inMonth && $date === $today,
                'markers' => $inMonth ? ($markersByDate[$date] ?? []) : [],
            ];
        }

        return [
            'year' => $cursor->year,
            'month' => $cursor->month,
            'cursor' => $cursor->format('Y-m'),
            'label' => $cursor->translatedFormat('F Y'),
            'weekdays' => $weekdays,
            'cells' => $cells,
        ];
    }

    public function parseMonthParam(?string $cal): Carbon
    {
        if ($cal !== null && preg_match('/^\d{4}-\d{2}$/', $cal) === 1) {
            return Carbon::createFromFormat('Y-m', $cal)->startOfMonth();
        }

        return now()->startOfMonth();
    }
}
