<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_approvals_can_filter_by_status_month_user_and_search(): void
    {
        $leader = User::factory()->teamLeader()->create([
            'language_preference' => 'en',
        ]);
        $alice = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'name_en' => 'Alice Filter',
            'name_ar' => 'أليس فلتر',
        ]);
        $bob = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'name_en' => 'Bob Other',
            'name_ar' => 'بوب آخر',
        ]);

        Permission::factory()->create([
            'user_id' => $alice->id,
            'team_leader_id' => $leader->id,
            'status' => Permission::STATUS_PENDING,
            'request_date' => '2026-10-05',
            'reason' => 'reason-pending-alice',
        ]);
        Permission::factory()->create([
            'user_id' => $alice->id,
            'team_leader_id' => $leader->id,
            'status' => Permission::STATUS_ACCEPTED,
            'request_date' => '2026-10-06',
            'decided_by' => $leader->id,
            'reason' => 'reason-accepted-alice',
        ]);
        Permission::factory()->create([
            'user_id' => $bob->id,
            'team_leader_id' => $leader->id,
            'status' => Permission::STATUS_REJECTED,
            'request_date' => '2026-09-15',
            'decided_by' => $leader->id,
            'reason' => 'reason-rejected-bob',
        ]);

        $this->actingAs($leader)
            ->get(route('team-leader.approvals', ['filter' => 'approved']))
            ->assertOk()
            ->assertSee('reason-accepted-alice', false)
            ->assertDontSee('reason-pending-alice', false)
            ->assertDontSee('reason-rejected-bob', false);

        $this->actingAs($leader)
            ->get(route('team-leader.approvals', ['filter' => 'rejected', 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('reason-rejected-bob', false)
            ->assertDontSee('reason-accepted-alice', false);

        $this->actingAs($leader)
            ->get(route('team-leader.approvals', [
                'filter' => 'all',
                'user_id' => $alice->id,
            ]))
            ->assertOk()
            ->assertSee('reason-pending-alice', false)
            ->assertSee('reason-accepted-alice', false)
            ->assertDontSee('reason-rejected-bob', false);

        $this->actingAs($leader)
            ->get(route('team-leader.approvals', [
                'filter' => 'all',
                'q' => 'Bob',
            ]))
            ->assertOk()
            ->assertSee('reason-rejected-bob', false)
            ->assertDontSee('reason-accepted-alice', false)
            ->assertDontSee('reason-pending-alice', false);
    }

    public function test_approvals_status_pills_include_approved_and_rejected(): void
    {
        $leader = User::factory()->teamLeader()->create(['language_preference' => 'en']);

        $this->actingAs($leader)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee(__('permissions.tl.filters.approved'), false)
            ->assertSee(__('permissions.tl.filters.rejected'), false)
            ->assertSee(__('permissions.tl.filter_month'), false);
    }
}
