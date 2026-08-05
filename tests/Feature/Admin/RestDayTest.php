<?php

namespace Tests\Feature\Admin;

use App\Models\RestDay;
use App\Models\User;
use App\Models\Vacation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Date-based rest days — Admin form + TL team page. */
class RestDayTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->leader = User::factory()->teamLeader()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'name_en' => 'Rest Team Member',
        ]);
    }

    public function test_admin_adds_rest_day_on_employee_edit(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.rest-days.store', $this->employee), [
                'date' => '2026-08-07',
            ])
            ->assertRedirect(route('admin.employees.edit', $this->employee));

        $this->assertTrue(
            RestDay::query()
                ->where('user_id', $this->employee->id)
                ->whereDate('date', '2026-08-07')
                ->exists()
        );
    }

    public function test_team_leader_can_add_and_remove_team_rest_day(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->leader)
            ->withSession(['locale' => 'en'])
            ->get(route('team-leader.rest-days'))
            ->assertOk()
            ->assertSee('Rest Team Member', false);

        $this->actingAs($this->leader)
            ->post(route('team-leader.rest-days.store', $this->employee), [
                'date' => '2026-08-14',
            ])
            ->assertRedirect(route('team-leader.rest-days'));

        $rest = RestDay::query()->where('user_id', $this->employee->id)->first();
        $this->assertNotNull($rest);

        $this->actingAs($this->leader)
            ->delete(route('team-leader.rest-days.destroy', [$this->employee, $rest]))
            ->assertRedirect(route('team-leader.rest-days'));

        $this->assertDatabaseMissing('rest_days', ['id' => $rest->id]);
    }

    public function test_team_leader_sees_reserved_badge_for_pending_vacation_swap(): void
    {
        app()->setLocale('en');

        $rest = RestDay::factory()->create([
            'user_id' => $this->employee->id,
            'date' => now()->addWeek()->toDateString(),
            'created_by' => $this->leader->id,
        ]);

        Vacation::factory()->create([
            'user_id' => $this->employee->id,
            'type' => Vacation::TYPE_EMERGENCY,
            'status' => Vacation::STATUS_PENDING,
            'replace_rest_day_id' => $rest->id,
            'team_leader_id' => $this->leader->id,
        ]);

        $this->actingAs($this->leader)
            ->withSession(['locale' => 'en'])
            ->get(route('team-leader.rest-days'))
            ->assertOk()
            ->assertSee('Pending vacation')
            ->assertDontSee(route('team-leader.rest-days.destroy', [$this->employee, $rest]), false);
    }

    public function test_team_leader_cannot_add_other_team_rest_day(): void
    {
        $other = User::factory()->employee()->create([
            'team_leader_id' => User::factory()->teamLeader()->create()->id,
        ]);

        $this->actingAs($this->leader)
            ->post(route('team-leader.rest-days.store', $other), [
                'date' => '2026-08-07',
            ])
            ->assertForbidden();
    }
}
