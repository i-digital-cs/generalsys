<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\RestDay;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\HoursComparisonService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** T-7.3 — dual default shifts (FULL_TIME + ONLINE_HALF). */
class DualDefaultShiftsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'name_ar' => 'موظف',
            'name_en' => 'Dual Shift Emp',
            'email' => 'dual@hrpro.test',
            'password' => 'password123',
            'role' => User::ROLE_EMPLOYEE,
            'currency' => 'EGP',
            'language_preference' => 'ar',
            'team_leader_id' => null,
            'shift_types_default' => [
                Attendance::SHIFT_FULL_TIME,
                Attendance::SHIFT_ONLINE_HALF,
            ],
            'default_shifts' => [
                [
                    'shift_type' => Attendance::SHIFT_ONLINE_HALF,
                    'start' => '06:00',
                    'end' => '10:00',
                ],
                [
                    'shift_type' => Attendance::SHIFT_FULL_TIME,
                    'start' => '10:00',
                    'end' => '18:00',
                ],
            ],
            'work_start_time' => '06:00',
            'work_end_time' => '18:00',
            'status' => User::STATUS_ACTIVE,
            'base_salary' => 5000,
            'online_half_day_rate' => 100,
            'online_full_day_rate' => 200,
            'overtime_hourly_rate' => 50,
            'profile' => [
                'national_id' => '29501010101010',
                'birth_date' => '1995-01-01',
                'address' => 'Cairo',
                'phone' => '01000000000',
                'gender' => 'MALE',
                'religion' => null,
                'marital_status' => 'SINGLE',
            ],
            'contract' => [
                'hire_date' => '2024-01-01',
                'contract_start' => '2024-01-01',
                'contract_end' => '2026-01-01',
                'contract_type' => 'سنة',
            ],
            'qualifications' => [],
            'contacts' => [],
        ], $overrides);
    }

    public function test_employee_form_saves_both_default_shifts(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('admin.employees.store'), $this->employeePayload())
            ->assertRedirect();

        $user = User::query()->where('email', 'dual@hrpro.test')->firstOrFail();
        $this->assertSame(
            [Attendance::SHIFT_ONLINE_HALF, Attendance::SHIFT_FULL_TIME],
            $user->defaultShiftTypes(),
        );
        $this->assertSame(Attendance::SHIFT_ONLINE_HALF, $user->shift_type_default);
        $this->assertSame('06:00', $user->defaultWindowFor(Attendance::SHIFT_ONLINE_HALF)[0]);
        $this->assertSame('10:00', $user->defaultWindowFor(Attendance::SHIFT_FULL_TIME)[0]);
    }

    public function test_clock_allows_both_defaults_same_day(): void
    {
        $employee = User::factory()->employee()->create([
            'shift_types_default' => [Attendance::SHIFT_FULL_TIME, Attendance::SHIFT_ONLINE_HALF],
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'work_start_time' => '06:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);

        $half = $service->clockIn($employee, Attendance::SHIFT_ONLINE_HALF, Carbon::parse('2026-08-03 06:00:00'));
        $service->clockOut($employee, Carbon::parse('2026-08-03 10:00:00'));
        $this->assertSame(4.0, (float) $half->fresh()->actual_hours);

        $ft = $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $service->clockOut($employee, Carbon::parse('2026-08-03 18:00:00'));
        $this->assertSame(8.0, (float) $ft->fresh()->actual_hours);

        $allowed = $service->availableShiftTypes($employee, Carbon::parse('2026-08-03 19:00:00'));
        $this->assertNotContains(Attendance::SHIFT_FULL_TIME, $allowed);
        $this->assertNotContains(Attendance::SHIFT_ONLINE_HALF, $allowed);
    }

    public function test_monthly_breakdown_required_and_absent_per_shift(): void
    {
        $employee = User::factory()->employee()->create([
            'shift_types_default' => [Attendance::SHIFT_FULL_TIME, Attendance::SHIFT_ONLINE_HALF],
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        RestDay::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-07',
            'created_by' => $employee->id,
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $service->clockOut($employee, Carbon::parse('2026-08-03 18:00:00'));

        $breakdown = app(HoursComparisonService::class)
            ->monthlyDefaultShiftHours($employee, 8, 2026);

        $this->assertSame(30, $breakdown['work_days']);
        $this->assertSame(240.0, $breakdown['shifts'][Attendance::SHIFT_FULL_TIME]['required']);
        $this->assertSame(120.0, $breakdown['shifts'][Attendance::SHIFT_ONLINE_HALF]['required']);
        $this->assertSame(8.0, $breakdown['shifts'][Attendance::SHIFT_FULL_TIME]['actual']);
        $this->assertSame(0.0, $breakdown['shifts'][Attendance::SHIFT_ONLINE_HALF]['actual']);
        $this->assertSame(232.0, $breakdown['shifts'][Attendance::SHIFT_FULL_TIME]['absent']);
        $this->assertSame(120.0, $breakdown['shifts'][Attendance::SHIFT_ONLINE_HALF]['absent']);
    }

    public function test_online_full_legacy_normalizes_to_both(): void
    {
        $this->assertSame(
            [Attendance::SHIFT_FULL_TIME, Attendance::SHIFT_ONLINE_HALF],
            User::normalizeDefaultShiftTypes([], Attendance::SHIFT_ONLINE_FULL),
        );
    }
}
