<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Attendance\AttendanceCalculationEngine;
use App\Services\Attendance\AttendanceHistoryService;
use App\Services\Attendance\CheckoutProvenance;
use App\Services\Attendance\HoursComparisonService;
use App\Services\Payroll\PayrollAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-screen parity: same fixture → same counted late/early across consumers.
 * TEST-only fixtures; does not rewrite historical punches or settled payroll.
 */
class CrossScreenAttendanceParityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{name: string, start: string, end: string, in: string, out: string, date: string}>
     */
    public static function namedFixtures(): array
    {
        return [
            'mohamed_reda_overnight' => [
                'name' => 'Mohamed Reda',
                'start' => '21:00',
                'end' => '09:00',
                'date' => '2026-10-18',
                'in' => '2026-10-18 21:10:00',
                'out' => '2026-10-19 09:00:00',
            ],
            'mohamed_amir_day' => [
                'name' => 'Mohamed Amir',
                'start' => '10:00',
                'end' => '22:00',
                'date' => '2026-10-18',
                'in' => '2026-10-18 10:20:00',
                'out' => '2026-10-18 22:00:00',
            ],
            'rahma_day' => [
                'name' => 'Rahma',
                'start' => '09:00',
                'end' => '17:00',
                'date' => '2026-10-18',
                'in' => '2026-10-18 09:05:00',
                'out' => '2026-10-18 16:50:00',
            ],
        ];
    }

    public function test_named_fixtures_parity_across_history_hours_payroll(): void
    {
        $manager = User::factory()->manager()->create();
        $engine = app(AttendanceCalculationEngine::class);
        $history = app(AttendanceHistoryService::class);
        $hours = app(HoursComparisonService::class);
        $payroll = app(PayrollAggregator::class);

        foreach (self::namedFixtures() as $key => $fx) {
            $employee = User::factory()->employee()->create([
                'name_en' => $fx['name'],
                'work_start_time' => $fx['start'],
                'work_end_time' => $fx['end'],
            ]);

            $row = Attendance::factory()->create([
                'user_id' => $employee->id,
                'date' => $fx['date'],
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'status' => Attendance::STATUS_PRESENT,
                'clock_in' => $fx['in'],
                'clock_out' => $fx['out'],
                'actual_hours' => 8,
                'raw_actual_hours' => 8,
                'planned_hours' => 12,
                'late_minutes' => 0,
                'early_departure_minutes' => 0,
            ]);
            $row->setRelation('user', $employee);
            $row->load(['segments', 'gaps']);

            $canonical = $engine->forAttendance($row);
            $expectedLate = $canonical->countedLateMinutes();
            $expectedEarly = $canonical->countedEarlyMinutes();

            $page = $history->paginate($manager, [
                'employee_id' => $employee->id,
                'from' => $fx['date'],
                'to' => $fx['date'],
                'kind' => 'all',
                'evidence' => 'all',
                'page' => 1,
                'path' => '/',
            ]);
            $histRow = $page->items()[0];
            $this->assertSame(
                $expectedLate,
                (int) $histRow['late_minutes'],
                "History late mismatch for {$key}",
            );
            $this->assertSame(
                $expectedEarly,
                (int) $histRow['early_departure_minutes'],
                "History early mismatch for {$key}",
            );

            $month = $hours->monthForUser($employee, '2026-10');
            $this->assertSame(
                $expectedLate,
                (int) $month['totals']['late_minutes'],
                "Hours late mismatch for {$key}",
            );
            $this->assertSame(
                $expectedEarly,
                (int) $month['totals']['early_minutes'],
                "Hours early mismatch for {$key}",
            );

            $agg = $payroll->aggregate($employee, 10, 2026);
            $this->assertSame(
                $expectedLate,
                (int) $agg['late_minutes_total'],
                "Payroll late mismatch for {$key}",
            );
        }
    }

    public function test_missing_checkout_is_open_not_zero_worked(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);
        $row = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-20 09:00:00',
            'clock_out' => null,
            'actual_hours' => 0,
            'raw_actual_hours' => 0,
            'planned_hours' => 8,
            'status' => Attendance::STATUS_PRESENT,
        ]);
        $row->load(['segments', 'gaps', 'user']);

        $result = app(AttendanceCalculationEngine::class)->forAttendance($row);
        $this->assertTrue($result->isOpen());
        $this->assertSame(0, $result->creditedMinutes());
        $this->assertSame(CheckoutProvenance::OPEN, $result->checkoutProvenance);
    }

    public function test_finalized_payroll_snapshot_not_mutated_by_live_engine(): void
    {
        $manager = User::factory()->manager()->create();
        $user = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-21',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-21 09:30:00',
            'clock_out' => '2026-10-21 17:00:00',
            'actual_hours' => 7.5,
            'raw_actual_hours' => 7.5,
            'planned_hours' => 8,
            'late_minutes' => 0,
        ]);

        $payroll = PayrollRecord::factory()->create([
            'user_id' => $user->id,
            'created_by' => $manager->id,
            'month' => 10,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'late_minutes_total' => 99,
        ]);

        $live = app(PayrollAggregator::class)->aggregate($user, 10, 2026);
        $this->assertSame(30, (int) $live['late_minutes_total']);
        $this->assertSame(99, (int) $payroll->fresh()->late_minutes_total);
    }

    public function test_month_aggregate_equals_sum_of_session_exact_late(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);
        $engine = app(AttendanceCalculationEngine::class);

        $a = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-05',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'status' => Attendance::STATUS_PRESENT,
            'clock_in' => '2026-10-05 09:20:00',
            'clock_out' => '2026-10-05 17:00:00',
            'actual_hours' => 7.67,
            'raw_actual_hours' => 7.67,
            'planned_hours' => 8,
            'late_minutes' => 0,
        ]);
        $b = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-06',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'status' => Attendance::STATUS_PRESENT,
            'clock_in' => '2026-10-06 09:10:00',
            'clock_out' => '2026-10-06 17:00:00',
            'actual_hours' => 7.83,
            'raw_actual_hours' => 7.83,
            'planned_hours' => 8,
            'late_minutes' => 0,
        ]);
        $a->setRelation('user', $user);
        $b->setRelation('user', $user);

        $sum = $engine->exactLateMinutes($a) + $engine->exactLateMinutes($b);
        $month = app(HoursComparisonService::class)->monthForUser($user, '2026-10');
        $this->assertSame($sum, (int) $month['totals']['late_minutes']);
    }
}
