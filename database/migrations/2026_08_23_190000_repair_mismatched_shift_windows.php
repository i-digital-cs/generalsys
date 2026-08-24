<?php

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use App\Services\Attendance\ShiftWindowValidator;
use Illuminate\Database\Migrations\Migration;

/**
 * R9 — repair default shift windows whose length exceeds planned hours + 1h slack
 * (e.g. FULL_TIME 09:00→01:00 = 16h vs planned 8h → end becomes 17:00).
 */
return new class extends Migration
{
    public function up(): void
    {
        $windows = app(ShiftWindowValidator::class);

        User::query()
            ->whereNotNull('shift_types_default')
            ->orderBy('id')
            ->each(function (User $user) use ($windows): void {
                $profiles = $user->defaultShiftProfiles();
                if ($profiles === []) {
                    return;
                }

                $changed = false;
                foreach ($profiles as $i => $profile) {
                    $type = (string) ($profile['type'] ?? '');
                    $start = (string) ($profile['start'] ?? '');
                    $end = (string) ($profile['end'] ?? '');
                    if ($type === '' || $start === '' || $end === '') {
                        continue;
                    }

                    if ($windows->isCompatible($type, $start, $end)) {
                        continue;
                    }

                    $planned = $windows->plannedHoursForType($type);
                    $profiles[$i]['end'] = $windows->suggestedEnd($start, $planned);
                    $changed = true;
                }

                if (! $changed) {
                    return;
                }

                $payload = ['shift_types_default' => $profiles];
                if (count($profiles) === 1) {
                    $payload['work_start_time'] = $profiles[0]['start'];
                    $payload['work_end_time'] = $profiles[0]['end'];
                } else {
                    $payload['work_start_time'] = collect($profiles)->min('start');
                    $payload['work_end_time'] = collect($profiles)->max('end');
                }

                $user->forceFill($payload)->save();
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
