<?php

namespace Tests\Feature\Admin;

use App\Jobs\GeneratePayrollJob;
use App\Models\Attendance;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Payroll\MonthCoverageException;
use App\Services\Payroll\PayrollGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Gate 4.6 / 4.10 / 4.11 — admin draft payroll generation.
 */
class PayrollGenerateTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_payroll_index(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('admin.payroll.index', ['month' => 6, 'year' => 2026]))
            ->assertOk()
            ->assertSee(__('payroll.title'), false)
            ->assertSee(__('payroll.generate'), false);
    }

    public function test_team_leader_gets_403_on_payroll(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->get(route('admin.payroll.index'))
            ->assertForbidden();
    }

    public function test_manager_generates_draft_and_approves(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create(['base_salary' => 4000]);
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'base_salary' => 5000,
        ]);

        $this->seedFullMonth($employee, 6, 2026);
        $this->seedFullMonth($leader, 6, 2026);

        $this->actingAs($manager)
            ->post(route('admin.payroll.generate'), [
                'month' => 6,
                'year' => 2026,
                'team_leader_id' => $leader->id,
            ])
            ->assertRedirect(route('admin.payroll.index', ['month' => 6, 'year' => 2026]))
            ->assertSessionHas('success');

        $draft = PayrollRecord::query()
            ->where('user_id', $employee->id)
            ->where('month', 6)
            ->where('year', 2026)
            ->first();

        $this->assertNotNull($draft);
        $this->assertSame(PayrollRecord::STATUS_DRAFT, $draft->status);
        $this->assertSame(5000.0, (float) $draft->base_salary);

        $this->actingAs($manager)
            ->put(route('admin.payroll.approve', $draft))
            ->assertRedirect(route('admin.payroll.show', $draft));

        $this->assertSame(PayrollRecord::STATUS_APPROVED, $draft->fresh()->status);
    }

    public function test_generate_creates_draft_when_coverage_incomplete(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['base_salary' => 5000]);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-06-01',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'planned_hours' => 8,
            'actual_hours' => 8,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.generate'), [
                'month' => 6,
                'year' => 2026,
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('error');

        $this->assertSame(1, PayrollRecord::query()->count());
        $this->assertSame(PayrollRecord::STATUS_DRAFT, PayrollRecord::query()->first()->status);
    }

    public function test_strict_coverage_blocks_payroll_when_enabled(): void
    {
        config(['payroll.require_full_month_coverage' => true]);

        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['base_salary' => 5000]);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-06-01',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'planned_hours' => 8,
            'actual_hours' => 8,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $this->expectException(MonthCoverageException::class);

        app(PayrollGenerator::class)->generateForUser($employee, 6, 2026, $manager->id);
    }

    public function test_generation_chunks_at_most_100_employees(): void
    {
        config(['payroll.generate_inline' => false]);
        Queue::fake();

        $manager = User::factory()->manager()->create();
        User::factory()->employee()->count(105)->create(['base_salary' => 3000]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.generate'), [
                'month' => 6,
                'year' => 2026,
            ])
            ->assertRedirect();

        Queue::assertPushed(GeneratePayrollJob::class, 2);

        foreach (Queue::pushed(GeneratePayrollJob::class) as $job) {
            $this->assertLessThanOrEqual(PayrollGenerator::CHUNK_SIZE, count($job->userIds));
        }
    }

    public function test_validation_rejects_invalid_month(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('admin.payroll.generate'), [
                'month' => 13,
                'year' => 2026,
            ])
            ->assertSessionHasErrors('month');
    }

    private function seedFullMonth(User $user, int $month, int $year): void
    {
        $days = Carbon::create($year, $month, 1)->daysInMonth;

        for ($day = 1; $day <= $days; $day++) {
            Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => Carbon::create($year, $month, $day)->toDateString(),
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'planned_hours' => 8,
                'actual_hours' => 8,
                'status' => Attendance::STATUS_PRESENT,
                'late_minutes' => 0,
            ]);
        }
    }
}
