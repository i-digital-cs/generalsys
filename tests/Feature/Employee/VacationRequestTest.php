<?php

namespace Tests\Feature\Employee;

use App\Models\RestDay;
use App\Models\User;
use App\Models\Vacation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vacation = swap one rest day for one day off (product lock 2026-08-03).
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
    }

    private function restDay(string $date = '2026-08-07'): RestDay
    {
        return RestDay::factory()->create([
            'user_id' => $this->employee->id,
            'date' => $date,
            'created_by' => $this->leader->id,
        ]);
    }

    public function test_employee_sees_rest_days_and_can_submit_swap(): void
    {
        app()->setLocale('en');
        $rest = $this->restDay('2026-08-07');

        $this->actingAs($this->employee)
            ->get(route('employee.requests', ['tab' => 'vacations']))
            ->assertOk()
            ->assertSee(__('vacations.rest_pool_title'), false)
            ->assertSee('2026-08-07', false)
            ->assertSee(__('vacations.fields.replace_rest_day'), false);

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

        $vacation = Vacation::query()->where('user_id', $this->employee->id)->first();
        $this->assertSame('2026-08-05', $vacation->start_date->toDateString());
        $this->assertSame('2026-08-05', $vacation->end_date->toDateString());
        $this->assertSame('2026-08-07', $vacation->replace_rest_date->toDateString());
    }

    public function test_cannot_submit_without_rest_day(): void
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
                'type' => Vacation::TYPE_ANNUAL,
                'start_date' => '2026-08-05',
                'replace_rest_day_id' => $restB->id,
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'vacations']))
            ->assertSessionHasErrors();
    }

    public function test_team_leader_can_submit_own_vacation_for_manager(): void
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

        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());
        $this->assertSame('vacation_submitted', $manager->notifications()->first()->data['kind']);
    }

    public function test_submit_notifies_team_leader_and_manager(): void
    {
        $manager = User::factory()->manager()->create();
        $rest = $this->restDay('2026-08-14');

        $this->actingAs($this->employee)
            ->post(route('employee.requests.vacations.store'), [
                'type' => Vacation::TYPE_ANNUAL,
                'start_date' => '2026-08-12',
                'replace_rest_day_id' => $rest->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, $this->leader->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());
    }
}
