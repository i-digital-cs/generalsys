<?php

namespace Tests\Feature\Employee;

use App\Models\RestDay;
use App\Models\User;
use App\Models\Vacation;
use App\Models\VacationBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vacation: ANNUAL/SICK = multi-day range + balance; EMERGENCY = rest-day swap.
 */
class VacationRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create([
            'language_preference' => 'en',
        ]);
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'language_preference' => 'en',
        ]);

        $this->seedBalance(30);
    }

    private function seedBalance(float $remaining, int $year = 2026): VacationBalance
    {
        return VacationBalance::factory()->create([
            'user_id' => $this->employee->id,
            'year' => $year,
            'accrued_days' => $remaining,
            'used_days' => 0,
            'pending_days' => 0,
            'remaining_days' => $remaining,
        ]);
    }

    private function restDay(string $date = '2026-08-07'): RestDay
    {
        return RestDay::factory()->create([
            'user_id' => $this->employee->id,
            'date' => $date,
            'created_by' => $this->leader->id,
        ]);
    }

    public function test_employee_can_submit_annual_date_range_without_rest_day(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->employee)
            ->post(route('employee.requests.vacations.store'), [
                'type' => Vacation::TYPE_ANNUAL,
                'start_date' => '2026-08-05',
                'end_date' => '2026-08-09',
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'vacations']))
            ->assertSessionHas('success');

        $vacation = Vacation::query()->where('user_id', $this->employee->id)->firstOrFail();
        $this->assertSame(Vacation::TYPE_ANNUAL, $vacation->type);
        $this->assertSame('2026-08-05', $vacation->start_date->toDateString());
        $this->assertSame('2026-08-09', $vacation->end_date->toDateString());
        $this->assertSame('5.0', $vacation->days_requested);
        $this->assertNull($vacation->replace_rest_day_id);

        $balance = VacationBalance::query()->where('user_id', $this->employee->id)->where('year', 2026)->first();
        $this->assertSame('5.0', $balance->pending_days);
        $this->assertSame('25.0', $balance->remaining_days);
    }

    public function test_employee_can_submit_sick_date_range(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.requests.vacations.store'), [
                'type' => Vacation::TYPE_SICK,
                'start_date' => '2026-08-05',
                'end_date' => '2026-08-06',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $vacation = Vacation::query()->where('user_id', $this->employee->id)->firstOrFail();
        $this->assertSame(Vacation::TYPE_SICK, $vacation->type);
        $this->assertSame('2.0', $vacation->days_requested);
        $this->assertNull($vacation->replace_rest_day_id);
    }

    public function test_insufficient_balance_blocks_annual_submit(): void
    {
        VacationBalance::query()->where('user_id', $this->employee->id)->update([
            'accrued_days' => 2,
            'remaining_days' => 2,
        ]);

        $this->actingAs($this->employee)
            ->from(route('employee.requests', ['tab' => 'vacations']))
            ->post(route('employee.requests.vacations.store'), [
                'type' => Vacation::TYPE_ANNUAL,
                'start_date' => '2026-08-05',
                'end_date' => '2026-08-09',
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'vacations']))
            ->assertSessionHasErrors('start_date');

        $this->assertSame(0, Vacation::query()->where('user_id', $this->employee->id)->count());
    }

    public function test_employee_sees_balance_and_rest_days_for_emergency(): void
    {
        app()->setLocale('en');
        $rest = $this->restDay('2026-08-07');

        $this->actingAs($this->employee)
            ->get(route('employee.requests', ['tab' => 'vacations']))
            ->assertOk()
            ->assertSee(__('vacations.credit_remaining'), false)
            ->assertSee(__('vacations.rest_pool_title'), false)
            ->assertSee('2026-08-07', false);

        $this->actingAs($this->employee)
            ->post(route('employee.requests.vacations.store'), [
                'type' => Vacation::TYPE_EMERGENCY,
                'start_date' => '2026-08-05',
                'replace_rest_day_id' => $rest->id,
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'vacations']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vacations', [
            'user_id' => $this->employee->id,
            'type' => Vacation::TYPE_EMERGENCY,
            'status' => Vacation::STATUS_PENDING,
            'team_leader_id' => $this->leader->id,
            'replace_rest_day_id' => $rest->id,
        ]);
    }

    public function test_cannot_submit_emergency_without_rest_day(): void
    {
        $this->actingAs($this->employee)
            ->from(route('employee.requests', ['tab' => 'vacations']))
            ->post(route('employee.requests.vacations.store'), [
                'type' => Vacation::TYPE_EMERGENCY,
                'start_date' => '2026-08-05',
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'vacations']))
            ->assertSessionHasErrors('replace_rest_day_id');
    }

    public function test_overlap_is_rejected(): void
    {
        $restA = $this->restDay('2026-08-07');

        Vacation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-05',
            'days_requested' => 1,
            'replace_rest_date' => '2026-08-07',
            'replace_rest_day_id' => $restA->id,
            'status' => Vacation::STATUS_PENDING,
        ]);

        $this->actingAs($this->employee)
            ->from(route('employee.requests', ['tab' => 'vacations']))
            ->post(route('employee.requests.vacations.store'), [
                'type' => Vacation::TYPE_ANNUAL,
                'start_date' => '2026-08-05',
                'end_date' => '2026-08-05',
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'vacations']))
            ->assertSessionHasErrors();
    }

    public function test_annual_range_overlap_is_rejected(): void
    {
        Vacation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'type' => Vacation::TYPE_ANNUAL,
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-07',
            'days_requested' => 3,
            'status' => Vacation::STATUS_PENDING,
        ]);

        $this->actingAs($this->employee)
            ->from(route('employee.requests', ['tab' => 'vacations']))
            ->post(route('employee.requests.vacations.store'), [
                'type' => Vacation::TYPE_ANNUAL,
                'start_date' => '2026-08-06',
                'end_date' => '2026-08-10',
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'vacations']))
            ->assertSessionHasErrors();
    }

    public function test_swap_overlap_is_rejected(): void
    {
        $restA = $this->restDay('2026-08-07');
        $restB = $this->restDay('2026-08-14');

        Vacation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-05',
            'days_requested' => 1,
            'replace_rest_date' => '2026-08-07',
            'replace_rest_day_id' => $restA->id,
            'status' => Vacation::STATUS_PENDING,
        ]);

        $this->actingAs($this->employee)
            ->from(route('employee.requests', ['tab' => 'vacations']))
            ->post(route('employee.requests.vacations.store'), [
                'type' => Vacation::TYPE_EMERGENCY,
                'start_date' => '2026-08-05',
                'replace_rest_day_id' => $restB->id,
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'vacations']))
            ->assertSessionHasErrors();
    }

    public function test_team_leader_can_submit_own_emergency_for_manager(): void
    {
        $manager = User::factory()->manager()->create();
        $rest = RestDay::factory()->create([
            'user_id' => $this->leader->id,
            'date' => '2026-08-21',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($this->leader)
            ->post(route('employee.requests.vacations.store'), [
                'type' => Vacation::TYPE_EMERGENCY,
                'start_date' => '2026-08-20',
                'replace_rest_day_id' => $rest->id,
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'vacations']));

        $this->assertDatabaseHas('vacations', [
            'user_id' => $this->leader->id,
            'team_leader_id' => null,
            'status' => Vacation::STATUS_PENDING,
        ]);
    }

    public function test_submit_notifies_team_leader_and_manager(): void
    {
        User::factory()->manager()->create();

        $this->actingAs($this->employee)
            ->post(route('employee.requests.vacations.store'), [
                'type' => Vacation::TYPE_ANNUAL,
                'start_date' => '2026-08-12',
                'end_date' => '2026-08-14',
            ])
            ->assertRedirect();

        $this->assertSame(1, $this->leader->fresh()->unreadNotifications()->count());
    }
}
