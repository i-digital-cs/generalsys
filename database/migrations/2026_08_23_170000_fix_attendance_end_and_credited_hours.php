<?php

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-7.9 follow-up — clear sticky credited_hours=0; repair FT 09:00→01:00 typos to 09:00→17:00.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendance')
            ->where('credited_hours', 0)
            ->where('overtime_tl_recommendation', 'NONE')
            ->update(['credited_hours' => null]);

        User::query()
            ->whereNotNull('shift_types_default')
            ->orderBy('id')
            ->each(function (User $user): void {
                $profiles = $user->defaultShiftProfiles();
                $changed = false;

                foreach ($profiles as $i => $profile) {
                    if (($profile['type'] ?? null) !== Attendance::SHIFT_FULL_TIME) {
                        continue;
                    }
                    if (($profile['start'] ?? null) !== '09:00' || ($profile['end'] ?? null) !== '01:00') {
                        continue;
                    }
                    $profiles[$i]['end'] = '17:00';
                    $changed = true;
                }

                if (! $changed) {
                    return;
                }

                $user->forceFill([
                    'shift_types_default' => $profiles,
                    'work_end_time' => $user->work_end_time === '01:00' || (string) $user->work_end_time === '01:00:00'
                        ? '17:00'
                        : $user->work_end_time,
                ])->save();
            });

        $calculator = app(AttendanceTimeCalculator::class);
        $attendance = app(AttendanceService::class);

        Attendance::query()
            ->whereDate('date', '>=', now()->subDays(14)->toDateString())
            ->whereNotNull('clock_out')
            ->with(['segments', 'user'])
            ->orderBy('id')
            ->each(function (Attendance $row) use ($calculator, $attendance): void {
                if ($row->user === null) {
                    return;
                }
                $calculator->recalculate($row, $attendance);
            });
    }

    public function down(): void
    {
        // Irreversible data repair.
    }
};
