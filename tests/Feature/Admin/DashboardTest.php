<?php

namespace Tests\Feature\Admin;

use App\Models\Contract;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_aggregate_stats(): void
    {
        $manager = User::factory()->manager()->create();
        User::factory()->employee()->count(3)->create();
        User::factory()->employee()->inactive()->create();

        $expiring = User::factory()->employee()->create();
        Contract::factory()->create([
            'user_id' => $expiring->id,
            'contract_end' => now()->addDays(30)->toDateString(),
        ]);
        Contract::factory()->create([
            'user_id' => User::factory()->employee()->create()->id,
            'contract_end' => now()->addDays(90)->toDateString(),
        ]);

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.calendar.title'), false)
            ->assertViewHas('stats', fn (array $stats) => $stats === [
                'total' => 6,
                'active' => 5,
                'inactive' => 1,
                'contracts_expiring' => 1,
            ]);
    }

    public function test_dashboard_is_empty_state_friendly(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 0 && $stats['contracts_expiring'] === 0)
            ->assertViewHas('expiringContracts', fn ($contracts) => $contracts->isEmpty());
    }

    public function test_manager_sees_expiring_contracts_list(): void
    {
        $manager = User::factory()->manager()->create();

        $expiringSoon = User::factory()->employee()->create(['name_en' => 'Soon Employee']);
        Contract::factory()->create([
            'user_id' => $expiringSoon->id,
            'contract_end' => now()->addDays(30)->toDateString(),
        ]);

        $expiringLater = User::factory()->employee()->create(['name_en' => 'Later Employee']);
        Contract::factory()->create([
            'user_id' => $expiringLater->id,
            'contract_end' => now()->addDays(90)->toDateString(),
        ]);

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('expiringContracts', fn ($contracts) => $contracts->count() === 1
                && $contracts->first()->user_id === $expiringSoon->id
                && $contracts->first()->relationLoaded('user'));
    }

    public function test_manager_dashboard_shows_team_leader_shifts_and_hides_self_service_nav(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create(['name_ar' => 'قائد الاختبار', 'name_en' => 'Test Leader']);

        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $leader->id,
            'assigned_by_tl_id' => $manager->id,
            'date' => now()->toDateString(),
            'expected_start_time' => '10:00',
            'expected_end_time' => '18:00',
            'expected_hours' => 8,
        ]);

        $html = $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.leader_shifts.title'), false)
            ->assertSee($leader->name_ar, false)
            ->assertSee(__('clock.shifts.FULL_TIME'), false)
            ->getContent();

        $this->assertStringNotContainsString('href="'.route('employee.clock').'"', $html);
        $this->assertStringNotContainsString('href="'.route('employee.report').'"', $html);
        $this->assertStringNotContainsString('href="'.route('employee.requests').'"', $html);
    }

    public function test_manager_dashboard_tables_use_shared_filter_toolbar(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create(['name_en' => 'Filter Leader']);
        $expiring = User::factory()->employee()->create(['name_en' => 'Expiring Person']);

        Contract::factory()->create([
            'user_id' => $expiring->id,
            'contract_end' => now()->addDays(20)->toDateString(),
        ]);

        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $leader->id,
            'assigned_by_tl_id' => $manager->id,
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-table-filter-root', false)
            ->assertSee('table-hr', false)
            ->assertSee('data-table-search', false)
            ->assertSee(__('dashboard.contracts_table.employee'), false)
            ->assertSee(__('general.search'), false);
    }

    public function test_only_the_current_menu_item_is_active(): void
    {
        $manager = User::factory()->manager()->create();

        foreach (['admin.dashboard', 'admin.employees.index'] as $routeName) {
            $content = $this->actingAs($manager)->get(route($routeName))
                ->assertOk()->getContent();

            // Exactly one sidebar link may carry the active classes — the old
            // "admin.*" group pattern lit up dashboard + settings together.
            $this->assertSame(1, substr_count($content, 'nav-link px-3 active'),
                "Expected exactly one active sidebar item on {$routeName}");
        }

        $settings = $this->actingAs($manager)->get(route('admin.settings'))
            ->assertOk()->getContent();
        $this->assertSame(0, substr_count($settings, 'nav-link px-3 active'),
            'Settings lives outside HR and has no sidebar.');
    }

    public function test_non_managers_get_403(): void
    {
        $this->actingAs(User::factory()->teamLeader()->create())
            ->get(route('admin.dashboard'))->assertForbidden();

        $this->actingAs(User::factory()->employee()->create())
            ->get(route('admin.dashboard'))->assertForbidden();
    }
}
