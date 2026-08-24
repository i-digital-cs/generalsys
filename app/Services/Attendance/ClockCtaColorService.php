<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Punch-button colors vs scheduled start (clock-in) or end (clock-out).
 *
 * Green: ±5 minutes. Yellow: 15 min before through 30 min after (except green).
 * Orange: 16+ min before or 31+ min after. Red: no scheduled time.
 */
class ClockCtaColorService
{
    public const GREEN = 'green';

    public const YELLOW = 'yellow';

    public const ORANGE = 'orange';

    public const RED = 'red';

    public function __construct(
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * @return array{green_before: int, green_after: int, yellow_before: int, yellow_after: int, orange_before: int, orange_after: int}
     */
    public function windows(): array
    {
        $cfg = config('attendance.cta_colors', []);

        return [
            'green_before' => (int) ($cfg['green_before'] ?? 5),
            'green_after' => (int) ($cfg['green_after'] ?? 5),
            'yellow_before' => (int) ($cfg['yellow_before'] ?? 15),
            'yellow_after' => (int) ($cfg['yellow_after'] ?? 30),
            'orange_before' => (int) ($cfg['orange_before'] ?? 16),
            'orange_after' => (int) ($cfg['orange_after'] ?? 31),
        ];
    }

    public function color(?CarbonInterface $expected, ?CarbonInterface $at = null): string
    {
        if ($expected === null) {
            return self::RED;
        }

        $delta = $this->deltaMinutes($expected, $at ?? now());
        $w = $this->windows();

        if ($delta <= -$w['orange_before'] || $delta >= $w['orange_after']) {
            return self::ORANGE;
        }

        if ($delta >= -$w['green_before'] && $delta <= $w['green_after']) {
            return self::GREEN;
        }

        if ($delta >= -$w['yellow_before'] && $delta <= $w['yellow_after']) {
            return self::YELLOW;
        }

        return self::RED;
    }

    /**
     * @param  list<string>  $shiftTypes
     * @return array{color: string, expectedAt: ?Carbon, clockInStarts: array<string, ?string>}
     */
    public function buttonState(
        User $user,
        ?Attendance $open,
        ?string $shiftType,
        array $shiftTypes = [],
        ?CarbonInterface $at = null,
    ): array {
        $at = $at ?? now();

        if ($open !== null) {
            $open->setRelation('user', $user);
            $expected = $this->attendance->expectedEndForAttendance($open);

            return [
                'color' => $this->color($expected, $at),
                'expectedAt' => $expected,
                'clockInStarts' => [],
            ];
        }

        $expected = $shiftType !== null && $shiftType !== ''
            ? $this->attendance->expectedStartFor($user, $shiftType, $at)
            : null;

        $starts = [];
        foreach ($shiftTypes as $type) {
            $starts[$type] = $this->attendance->expectedStartFor($user, $type, $at)?->toIso8601String();
        }

        return [
            'color' => $this->color($expected, $at),
            'expectedAt' => $expected,
            'clockInStarts' => $starts,
        ];
    }

    private function deltaMinutes(CarbonInterface $expected, CarbonInterface $at): int
    {
        $exp = Carbon::instance($expected)->timezone(config('app.timezone'))->startOfMinute();
        $now = Carbon::instance($at)->timezone(config('app.timezone'))->startOfMinute();

        return (int) (($now->timestamp - $exp->timestamp) / 60);
    }
}
