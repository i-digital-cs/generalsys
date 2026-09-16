<?php

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use App\Services\Attendance\ShiftConfig;
use Illuminate\Database\Migrations\Migration;

/**
 * Restore Doaa ONLINE_FULL default to intentional overnight 09:00→01:00 (16h).
 * Prior R9 repair had shortened mismatched windows to type base hours (12h / 4h).
 */
return new class extends Migration
{
    public function up(): void
    {
        $doaa = User::query()
            ->where(function ($q): void {
                $q->where('id', 7)
                    ->where(function ($inner): void {
                        $inner->whereRaw('UPPER(name_en) = ?', ['DOAA'])
                            ->orWhereRaw('LOWER(email) = ?', ['doaa@genral.net'])
                            ->orWhere('name_ar', 'دعاء');
                    });
            })
            ->first();

        if ($doaa === null) {
            return;
        }

        $fixed = [[
            'type' => Attendance::SHIFT_ONLINE_FULL,
            'start' => '09:00',
            'end' => '01:00',
            'expected_hours' => 16.0,
        ]];

        $doaa->forceFill([
            'shift_types_default' => $fixed,
            'shift_type_default' => Attendance::SHIFT_ONLINE_FULL,
            'work_start_time' => '09:00',
            'work_end_time' => '01:00',
        ])->save();

        $calculator = app(AttendanceTimeCalculator::class);
        $attendanceService = app(AttendanceService::class);
        $shifts = app(ShiftConfig::class);

        Attendance::query()
            ->where('user_id', $doaa->id)
            ->whereDate('date', '>=', '2026-09-01')
            ->where('shift_type', Attendance::SHIFT_ONLINE_FULL)
            ->whereNotNull('clock_out')
            ->with(['segments', 'user'])
            ->orderBy('id')
            ->each(function (Attendance $row) use ($calculator, $attendanceService, $shifts): void {
                if ($row->user === null) {
                    return;
                }

                $planned = $shifts->resolvePlannedHours($row->user, $row->shift_type);
                if ((float) $row->planned_hours !== $planned) {
                    $row->forceFill(['planned_hours' => $planned])->save();
                }

                $calculator->recalculate($row->fresh(['segments', 'user']), $attendanceService);
            });
    }

    public function down(): void
    {
        // Irreversible data repair.
    }
};
