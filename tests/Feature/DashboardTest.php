<?php

namespace Tests\Feature;

use App\Models\OfficialHoliday;
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

        // TEAM_LEADER menu routes are Phase-2 placeholders (not registered yet),
        // so the list must filter them out instead of linking to '#'.
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('quickActions', fn (array $actions) => collect($actions)
                ->every(fn (array $action) => $action['url'] !== '#'));
    }
}
