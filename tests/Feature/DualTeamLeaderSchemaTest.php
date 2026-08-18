<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * T-8.1 — employee_team_leaders pivot; users.team_leader_id dropped.
 */
class DualTeamLeaderSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pivot_exists_and_users_has_no_team_leader_id_column(): void
    {
        $this->assertTrue(Schema::hasTable('employee_team_leaders'));
        foreach (['user_id', 'team_leader_id', 'assigned_by', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('employee_team_leaders', $column), "Missing: {$column}");
        }
        $this->assertFalse(Schema::hasColumn('users', 'team_leader_id'));
    }

    public function test_factory_attaches_a_team_leader_via_the_pivot(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $this->assertDatabaseHas('employee_team_leaders', [
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
        ]);
        $this->assertSame($leader->id, $employee->team_leader_id);
        $this->assertTrue($employee->teamLeader->is($leader));
        $this->assertTrue($leader->teamMembers->contains($employee));
        $this->assertTrue(app(TeamMembershipService::class)->leads($leader, $employee));
    }

    public function test_sync_rejects_more_than_two_leaders(): void
    {
        $employee = User::factory()->employee()->create();
        $ids = User::factory()->teamLeader()->count(3)->create()->pluck('id')->all();

        $this->expectException(ValidationException::class);
        app(TeamMembershipService::class)->syncLeaders($employee, $ids);
    }

    public function test_sync_rejects_a_non_team_leader(): void
    {
        $employee = User::factory()->employee()->create();
        $manager = User::factory()->manager()->create();

        $this->expectException(ValidationException::class);
        app(TeamMembershipService::class)->syncLeaders($employee, [$manager->id]);
    }

    public function test_two_leaders_both_lead_and_see_the_employee(): void
    {
        $a = User::factory()->teamLeader()->create();
        $b = User::factory()->teamLeader()->create();
        $outsider = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create();

        $membership = app(TeamMembershipService::class);
        $membership->syncLeaders($employee, [$a->id, $b->id]);

        $this->assertSame([$a->id, $b->id], $membership->leaderIds($employee->fresh()));
        $this->assertTrue($membership->leads($a, $employee));
        $this->assertTrue($membership->leads($b, $employee));
        $this->assertFalse($membership->leads($outsider, $employee));
        $this->assertContains($employee->id, $membership->visibleEmployeeIds($a));
        $this->assertContains($employee->id, $membership->visibleEmployeeIds($b));
        $this->assertNotContains($employee->id, $membership->visibleEmployeeIds($outsider));
    }

    public function test_pivot_rejects_duplicate_pairs(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $this->expectException(QueryException::class);
        DB::table('employee_team_leaders')->insert([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
