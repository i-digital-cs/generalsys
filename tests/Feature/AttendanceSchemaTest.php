<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Permission;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Vacation;
use App\Models\VacationBalance;
use App\Models\VacationCreditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * T-2.1 — Phase 2 attendance/requests schema is present and writable.
 */
class AttendanceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase2_tables_exist(): void
    {
        foreach ([
            'attendance',
            'attendance_photos',
            'permissions',
            'vacations',
            'vacation_balances',
            'vacation_credit_logs',
            'shift_assignments',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasTable('attendance_daily_scores'));
        $this->assertFalse(Schema::hasColumn('attendance', 'performance_score'));

        foreach ([
            'clock_in_lat',
            'clock_in_lng',
            'clock_in_accuracy_m',
            'clock_in_distance_m',
            'clock_in_outside_geofence',
            'clock_out_lat',
            'clock_out_lng',
            'clock_out_accuracy_m',
            'clock_out_distance_m',
            'clock_out_outside_geofence',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('attendance', $column), "Missing column: {$column}");
        }

        $this->assertTrue(Schema::hasColumn('permissions', 'decided_by'));
        $this->assertTrue(Schema::hasColumn('vacations', 'decided_by'));
    }

    public function test_attendance_unique_per_user_date_shift(): void
    {
        $employee = User::factory()->employee()->create();

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-02',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
        ]);

        $this->expectException(QueryException::class);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-02',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
        ]);
    }

    public function test_factories_persist_related_phase2_rows(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $attendance = Attendance::factory()->create([
            'user_id' => $employee->id,
            'clock_in' => '2026-08-02 22:00:00',
            'clock_out' => '2026-08-03 06:00:00', // overnight — real next-day datetime
            'planned_hours' => 8,
        ]);

        $permission = Permission::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
        ]);

        $vacation = Vacation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
        ]);

        $balance = VacationBalance::factory()->create([
            'user_id' => $employee->id,
            'year' => 2026,
            'last_updated_by' => $leader->id,
        ]);

        $log = VacationCreditLog::factory()->create([
            'vacation_balance_id' => $balance->id,
            'added_by' => $leader->id,
            'amount_days' => 3,
        ]);

        $shift = ShiftAssignment::factory()->create([
            'user_id' => $employee->id,
            'assigned_by_tl_id' => $leader->id,
        ]);

        $this->assertTrue($employee->attendanceRecords()->whereKey($attendance)->exists());
        $this->assertTrue($employee->permissions()->whereKey($permission)->exists());
        $this->assertTrue($employee->vacations()->whereKey($vacation)->exists());
        $this->assertTrue($employee->vacationBalances()->whereKey($balance)->exists());
        $this->assertTrue($balance->creditLogs()->whereKey($log)->exists());
        $this->assertTrue($employee->shiftAssignments()->whereKey($shift)->exists());
        $this->assertSame('2026-08-03 06:00:00', $attendance->fresh()->clock_out->format('Y-m-d H:i:s'));
        $this->assertSame('18:00', $shift->fresh()->expected_start_time);
    }

    public function test_vacation_balance_unique_per_user_year(): void
    {
        $employee = User::factory()->employee()->create();

        VacationBalance::factory()->create(['user_id' => $employee->id, 'year' => 2026]);

        $this->expectException(QueryException::class);

        VacationBalance::factory()->create(['user_id' => $employee->id, 'year' => 2026]);
    }
}
