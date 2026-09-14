<?php

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use App\Services\Attendance\ShiftConfig;
use App\Services\Attendance\ShiftWindowValidator;
use Illuminate\Database\Migrations\Migration;

/**
 * Repair Doaa (and any employee) whose default ONLINE_FULL/FT window is longer than
 * planned hours (e.g. 09:00→01:00 = 16h vs ONLINE_FULL planned 12h).
 * Then refresh planned_hours + credited hours on recent closed attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        $windows = app(ShiftWindowValidator::class);
        $repairedUserIds = [];

        User::query()
            ->whereNotNull('shift_types_default')
            ->orderBy('id')
            ->each(function (User $user) use ($windows, &$repairedUserIds): void {
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
                    $profiles[$i]['expected_hours'] = $planned;
                    $changed = true;
                }

                if (! $changed) {
                    return;
                }

                $payload = [
                    'shift_types_default' => $profiles,
                    'shift_type_default' => $profiles[0]['type'],
                ];
                if (count($profiles) === 1) {
                    $payload['work_start_time'] = $profiles[0]['start'];
                    $payload['work_end_time'] = $profiles[0]['end'];
                } else {
                    $payload['work_start_time'] = collect($profiles)->min('start');
                    $payload['work_end_time'] = collect($profiles)->max('end');
                }

                $user->forceFill($payload)->save();
                $repairedUserIds[] = $user->id;
            });

        // Doaa only (live id 7): force ONLINE_FULL 09:00→21:00 (12h) when her overnight typo remains.
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

        if ($doaa !== null) {
            $profiles = $doaa->defaultShiftProfiles();
            $primary = $profiles[0] ?? null;
            $needsDoaaFix = $primary === null
                || ($primary['type'] ?? '') !== Attendance::SHIFT_ONLINE_FULL
                || ($primary['start'] ?? '') !== '09:00'
                || ($primary['end'] ?? '') !== '21:00'
                || (float) ($primary['expected_hours'] ?? 0) !== 12.0;

            if ($needsDoaaFix) {
                $fixed = [[
                    'type' => Attendance::SHIFT_ONLINE_FULL,
                    'start' => '09:00',
                    'end' => '21:00',
                    'expected_hours' => ShiftConfig::BASE_PLANNED_HOURS[Attendance::SHIFT_ONLINE_FULL],
                ]];
                $doaa->forceFill([
                    'shift_types_default' => $fixed,
                    'shift_type_default' => Attendance::SHIFT_ONLINE_FULL,
                    'work_start_time' => '09:00',
                    'work_end_time' => '21:00',
                ])->save();
                $repairedUserIds[] = $doaa->id;
            }
        }

        $repairedUserIds = array_values(array_unique($repairedUserIds));
        if ($repairedUserIds === []) {
            return;
        }

        $calculator = app(AttendanceTimeCalculator::class);
        $attendanceService = app(AttendanceService::class);
        $shifts = app(ShiftConfig::class);

        Attendance::query()
            ->whereIn('user_id', $repairedUserIds)
            ->whereDate('date', '>=', '2026-09-01')
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
