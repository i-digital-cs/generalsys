<?php

namespace Tests\Feature;

use App\Models\OfficialHoliday;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_sees_quick_actions_and_upcoming_holidays(): void
    {
        $user = User::factory()->employee()->create();

        OfficialHoliday::create([
            'date' => now()->addDays(10)->toDateString(),
            'name_ar' => 'عيد قادم',
            'name_en' => 'Upcoming Holiday',
        ]);
        OfficialHoliday::create([
            'date' => now()->subDays(10)->toDateString(),
            'name_ar' => 'عيد سابق',
            'name_en' => 'Past Holiday',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('quickActions', fn (array $actions) => $actions !== []
                && array_key_exists('url', $actions[0])
                && array_key_exists('icon', $actions[0])
                && array_key_exists('key', $actions[0]))
            ->assertViewHas('upcomingHolidays', fn ($holidays) => $holidays->count() === 1
                && $holidays->first()->name_en === 'Upcoming Holiday');
    }

    public function test_quick_actions_only_include_registered_routes(): void
    {
        $user = User::factory()->teamLeader()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('quickActions', fn (array $actions) => collect($actions)
                ->every(fn (array $action) => $action['url'] !== '#'));
    }

    public function test_team_leader_sees_team_hours_board_scoped(): void
    {
        app()->setLocale('en');

        $leader = User::factory()->teamLeader()->create();
        $radwa = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'name_en' => 'Radwa',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $mona = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'name_en' => 'Mona',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $outsider = User::factory()->employee()->create(['name_en' => 'Outsider']);

        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $radwa->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => now()->toDateString(),
            'expected_start_time' => '10:00',
            'expected_end_time' => '18:00',
        ]);
        ShiftAssignment::factory()->onlineHalf()->create([
            'user_id' => $mona->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => now()->toDateString(),
            'expected_start_time' => '10:00',
            'expected_end_time' => '14:00',
        ]);
        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $outsider->id,
            'assigned_by_tl_id' => User::factory()->teamLeader()->create()->id,
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($leader)
            ->get(route('team-leader.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.hours.today_board'), false)
            ->assertSee(__('dashboard.hours.team_month'), false)
            ->assertSee('Radwa', false)
            ->assertSee('Mona', false)
            ->assertDontSee('Outsider', false);
    }
}
