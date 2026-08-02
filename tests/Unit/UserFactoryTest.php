<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_a_manager(): void
    {
        $user = User::factory()->manager()->create();

        $this->assertTrue($user->isManager());
        $this->assertNull($user->team_leader_id);
    }

    public function test_factory_creates_a_team_leader(): void
    {
        $user = User::factory()->teamLeader()->create();

        $this->assertTrue($user->isTeamLeader());
    }

    public function test_factory_creates_an_employee(): void
    {
        $user = User::factory()->employee()->create();

        $this->assertTrue($user->isEmployee());
        $this->assertTrue($user->isActive());
    }

    public function test_employee_can_belong_to_a_team_leader(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $this->assertTrue($employee->teamLeader->is($leader));
        $this->assertTrue($leader->teamMembers->contains($employee));
    }
}
