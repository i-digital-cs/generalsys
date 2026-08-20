<?php

namespace Tests\Feature\Admin;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\RestDay;
use App\Models\ShiftAssignment;
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
            'team_leader_ids' => [User::factory()->teamLeader()->create()->id],
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

    public function test_evening_hides_morning_shift_and_prefers_full_time(): void
    {
        $employee = User::factory()->employee()->create([
            'shift_types_default' => [
                [
                    'type' => Attendance::SHIFT_ONLINE_HALF,
                    'start' => '06:00',
                    'end' => '10:00',
                ],
                [
                    'type' => Attendance::SHIFT_FULL_TIME,
                    'start' => '10:00',
                    'end' => '18:00',
                ],
            ],
            'shift_type_default' => Attendance::SHIFT_ONLINE_HALF,
        ]);

        $service = app(AttendanceService::class);
        $at = Carbon::parse('2026-08-03 17:01:00');

        $available = $service->availableShiftTypes($employee, $at);
        $this->assertSame([Attendance::SHIFT_FULL_TIME], $available);
        $this->assertSame(Attendance::SHIFT_FULL_TIME, $service->preferredShiftType($employee, $at));

        $this->expectException(AttendanceException::class);
        $service->clockIn($employee, Attendance::SHIFT_ONLINE_HALF, $at);
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

    public function test_payroll_day_lines_show_exact_existing_and_absent_per_shift(): void
    {
        $employee = User::factory()->employee()->create([
            'shift_types_default' => [
                [
                    'type' => Attendance::SHIFT_ONLINE_HALF,
                    'start' => '06:00',
                    'end' => '10:00',
                ],
                [
                    'type' => Attendance::SHIFT_FULL_TIME,
                    'start' => '10:00',
                    'end' => '18:00',
                ],
            ],
            'shift_type_default' => Attendance::SHIFT_ONLINE_HALF,
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_ONLINE_HALF, Carbon::parse('2026-08-03 06:00:00'));
        $service->clockOut($employee, Carbon::parse('2026-08-03 10:00:00'));
        // Missed FULL_TIME that day.

        $month = app(HoursComparisonService::class)->monthForUser($employee, '2026-08');
        $day = collect($month['days'])->firstWhere('date', '2026-08-03');
        $this->assertNotNull($day);
        $byType = collect($day['shift_lines'])->keyBy('shift_type');

        $this->assertSame(4.0, (float) $byType[Attendance::SHIFT_ONLINE_HALF]['required']);
        $this->assertSame(4.0, (float) $byType[Attendance::SHIFT_ONLINE_HALF]['actual']);
        $this->assertSame(0.0, (float) $byType[Attendance::SHIFT_ONLINE_HALF]['absent']);

        $this->assertSame(8.0, (float) $byType[Attendance::SHIFT_FULL_TIME]['required']);
        $this->assertSame(0.0, (float) $byType[Attendance::SHIFT_FULL_TIME]['actual']);
        $this->assertSame(8.0, (float) $byType[Attendance::SHIFT_FULL_TIME]['absent']);

        $this->assertSame(12.0, (float) $day['planned_hours']);
        $this->assertSame(4.0, (float) $day['actual_hours']);
    }

    public function test_online_full_legacy_normalizes_to_itself(): void
    {
        $this->assertSame(
            [Attendance::SHIFT_ONLINE_FULL],
            User::normalizeDefaultShiftTypes([], Attendance::SHIFT_ONLINE_FULL),
        );
    }

    public function test_employee_form_saves_online_full_default(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($manager)
            ->post(route('admin.employees.store'), $this->employeePayload([
                'email' => 'onlinefull@hrpro.test',
                'team_leader_ids' => [$leader->id],
                'default_shifts' => [
                    [
                        'shift_type' => Attendance::SHIFT_ONLINE_FULL,
                        'start' => '10:00',
                        'end' => '22:00',
                    ],
                    ['shift_type' => '', 'start' => '', 'end' => ''],
                    ['shift_type' => '', 'start' => '', 'end' => ''],
                ],
            ]))
            ->assertRedirect();

        $employee = User::query()->where('email', 'onlinefull@hrpro.test')->firstOrFail();
        $this->assertSame([Attendance::SHIFT_ONLINE_FULL], $employee->defaultShiftTypes());
        $this->assertSame(['10:00', '22:00'], $employee->defaultWindowFor(Attendance::SHIFT_ONLINE_FULL));

        $this->freezeClockToMorningShift();
        Carbon::setTestNow(Carbon::parse('2026-08-03 10:30:00', config('app.timezone')));

        $service = app(AttendanceService::class);
        $this->assertSame(
            [Attendance::SHIFT_ONLINE_FULL],
            $service->availableShiftTypes($employee),
        );

        $row = $service->clockIn($employee, Attendance::SHIFT_ONLINE_FULL, Carbon::parse('2026-08-03 10:30:00'));
        $this->assertSame(Attendance::SHIFT_ONLINE_FULL, $row->shift_type);
        $this->assertEqualsWithDelta(12.0, (float) $row->planned_hours, 0.01);
    }

    public function test_employee_can_have_three_default_shifts(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($manager)
            ->post(route('admin.employees.store'), $this->employeePayload([
                'email' => 'threeshifts@hrpro.test',
                'team_leader_ids' => [$leader->id],
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
                    [
                        'shift_type' => Attendance::SHIFT_ONLINE_FULL,
                        'start' => '18:00',
                        'end' => '22:00',
                    ],
                ],
            ]))
            ->assertRedirect();

        $employee = User::query()->where('email', 'threeshifts@hrpro.test')->firstOrFail();
        $this->assertSame(
            [Attendance::SHIFT_ONLINE_HALF, Attendance::SHIFT_FULL_TIME, Attendance::SHIFT_ONLINE_FULL],
            $employee->defaultShiftTypes(),
        );

        $this->actingAs($manager)
            ->get(route('admin.employees.edit', $employee))
            ->assertOk()
            ->assertSee(__('employees.shifts.ONLINE_FULL'), false)
            ->assertSee('name="default_shifts[2][shift_type]"', false)
            ->assertSee('value="ONLINE_FULL"', false)
            ->assertSee(__('employees.fields.default_shift_tertiary'), false);
    }

    public function test_assigning_one_default_keeps_the_other_clockable(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '10:00', 'end' => '18:00'],
                ['type' => Attendance::SHIFT_ONLINE_HALF, 'start' => '18:00', 'end' => '22:00'],
            ],
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        ShiftAssignment::factory()->create([
            'user_id' => $employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => '2026-08-03',
            'shift_type' => Attendance::SHIFT_ONLINE_HALF,
            'expected_start_time' => '18:00',
            'expected_end_time' => '22:00',
            'expected_hours' => 4,
            'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);

        $service = app(AttendanceService::class);
        $morning = Carbon::parse('2026-08-03 10:08:00');

        $available = $service->availableShiftTypes($employee, $morning);
        $this->assertContains(Attendance::SHIFT_FULL_TIME, $available);
        $this->assertNotContains(Attendance::SHIFT_ONLINE_HALF, $available);
        $this->assertSame(Attendance::SHIFT_FULL_TIME, $service->preferredShiftType($employee, $morning));

        $ft = $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, $morning);
        $service->clockOut($employee, Carbon::parse('2026-08-03 18:00:00'));
        $this->assertSame(Attendance::SHIFT_FULL_TIME, $ft->shift_type);

        $evening = Carbon::parse('2026-08-03 18:05:00');
        $this->assertContains(Attendance::SHIFT_ONLINE_HALF, $service->availableShiftTypes($employee, $evening));
        $this->assertNotContains(Attendance::SHIFT_FULL_TIME, $service->availableShiftTypes($employee, $evening));
    }

    public function test_late_start_allowed_until_end_then_next_shift_opens(): void
    {
        $employee = User::factory()->employee()->create([
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '10:00', 'end' => '18:00'],
                ['type' => Attendance::SHIFT_ONLINE_HALF, 'start' => '18:00', 'end' => '22:00'],
            ],
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);
        $service = app(AttendanceService::class);

        $lateMorning = Carbon::parse('2026-08-03 17:00:00');
        $this->assertSame([Attendance::SHIFT_FULL_TIME], $service->availableShiftTypes($employee, $lateMorning));
        $lateRow = $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, $lateMorning);
        $this->assertSame(Attendance::STATUS_LATE, $lateRow->status);
        $service->clockOut($employee, Carbon::parse('2026-08-03 18:00:00'));

        $afterFullTime = Carbon::parse('2026-08-03 18:01:00');
        $this->assertSame([Attendance::SHIFT_ONLINE_HALF], $service->availableShiftTypes($employee, $afterFullTime));

        $evening = $service->clockIn($employee, Attendance::SHIFT_ONLINE_HALF, Carbon::parse('2026-08-03 20:35:00'));
        $this->assertSame(Attendance::SHIFT_ONLINE_HALF, $evening->shift_type);
        $service->clockOut($employee, Carbon::parse('2026-08-03 22:00:00'));

        $this->assertSame([], $service->availableShiftTypes($employee, Carbon::parse('2026-08-03 22:01:00')));
    }

    public function test_cannot_start_evening_shift_in_the_morning(): void
    {
        $employee = User::factory()->employee()->create([
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '10:00', 'end' => '18:00'],
                ['type' => Attendance::SHIFT_ONLINE_HALF, 'start' => '18:00', 'end' => '22:00'],
            ],
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);
        $service = app(AttendanceService::class);
        $morning = Carbon::parse('2026-08-03 10:08:00');

        $this->assertSame(
            [Attendance::SHIFT_ONLINE_HALF],
            $service->upcomingUnrecordedShiftTypes($employee, $morning),
        );

        $this->expectException(AttendanceException::class);
        $this->expectExceptionMessage('clock.errors.shift_not_started_yet');
        $service->clockIn($employee, Attendance::SHIFT_ONLINE_HALF, $morning);
    }

    public function test_cannot_start_finished_shift_but_next_stays_open(): void
    {
        $employee = User::factory()->employee()->create([
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '10:00', 'end' => '18:00'],
                ['type' => Attendance::SHIFT_ONLINE_HALF, 'start' => '18:00', 'end' => '22:00'],
            ],
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);
        $service = app(AttendanceService::class);
        $evening = Carbon::parse('2026-08-03 20:35:00');

        $this->assertSame([Attendance::SHIFT_ONLINE_HALF], $service->availableShiftTypes($employee, $evening));
        $this->assertSame([Attendance::SHIFT_FULL_TIME], $service->closedUnrecordedShiftTypes($employee, $evening));

        $this->expectException(AttendanceException::class);
        $this->expectExceptionMessage('clock.errors.shift_window_closed');
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, $evening);
    }

    public function test_partial_assignment_does_not_drop_other_default_from_hours(): void
    {
        $employee = User::factory()->employee()->create([
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '10:00', 'end' => '18:00'],
                ['type' => Attendance::SHIFT_ONLINE_HALF, 'start' => '18:00', 'end' => '22:00'],
            ],
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        ShiftAssignment::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-03',
            'shift_type' => Attendance::SHIFT_ONLINE_HALF,
            'expected_start_time' => '18:00',
            'expected_end_time' => '22:00',
            'expected_hours' => 4,
            'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);

        $month = app(HoursComparisonService::class)->monthForUser($employee, '2026-08');
        $day = collect($month['days'])->firstWhere('date', '2026-08-03');
        $this->assertNotNull($day);
        $this->assertSame(12.0, (float) $day['planned_hours']);

        $byType = collect($day['shift_lines'])->keyBy('shift_type');
        $this->assertSame(8.0, (float) $byType[Attendance::SHIFT_FULL_TIME]['required']);
        $this->assertSame(4.0, (float) $byType[Attendance::SHIFT_ONLINE_HALF]['required']);
    }
}
