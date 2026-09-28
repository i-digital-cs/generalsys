<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use Illuminate\Console\Command;

/**
 * Repair stale attendance.planned_hours (and credited hours) when employee
 * default/assignment expected hours changed after clock-in (OT base was wrong).
 */
class SyncAttendancePlannedHoursCommand extends Command
{
    protected $signature = 'attendance:sync-planned-hours
                            {--dry-run : Report mismatches without writing}
                            {--user= : Limit to one user id}';

    protected $description = 'Re-resolve planned hours from profile/assignment and recalculate attendance credits.';

    public function handle(AttendanceTimeCalculator $calculator, AttendanceService $attendanceService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user');

        $query = Attendance::query()->with('user')->orderBy('id');
        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }

        $checked = 0;
        $fixed = 0;

        $query->chunkById(100, function ($rows) use ($calculator, $attendanceService, $dryRun, &$checked, &$fixed): void {
            foreach ($rows as $attendance) {
                if ($attendance->user === null) {
                    continue;
                }

                $checked++;
                $live = $calculator->livePlannedHours($attendance);
                $stored = round((float) $attendance->planned_hours, 2);

                if (abs($live - $stored) < 0.001) {
                    continue;
                }

                $this->line(sprintf(
                    'user=%d date=%s type=%s stored=%.2f live=%.2f raw=%.2f',
                    $attendance->user_id,
                    $attendance->date?->toDateString() ?? (string) $attendance->date,
                    $attendance->shift_type,
                    $stored,
                    $live,
                    (float) $attendance->raw_actual_hours,
                ));

                if ($dryRun) {
                    $fixed++;

                    continue;
                }

                $calculator->recalculate($attendance->fresh(['segments', 'user']), $attendanceService);
                $fixed++;
            }
        });

        $this->info(($dryRun ? 'Would fix' : 'Fixed').": {$fixed} / checked {$checked}");

        return self::SUCCESS;
    }
}
