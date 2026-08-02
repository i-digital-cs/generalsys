<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_updates_global_settings(): void
    {
        $manager = User::factory()->manager()->create();
        Setting::create(['key' => 'default_language', 'value' => 'ar']);
        Setting::create(['key' => 'default_currency', 'value' => 'EGP']);

        $this->actingAs($manager)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('default_language');

        $this->actingAs($manager)
            ->put(route('admin.settings.update'), [
                'default_language' => 'en',
                'default_currency' => 'AED',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertDatabaseHas('settings', ['key' => 'default_language', 'value' => 'en']);
        $this->assertDatabaseHas('settings', ['key' => 'default_currency', 'value' => 'AED']);
    }

    public function test_settings_validation_rejects_unknown_values(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->put(route('admin.settings.update'), [
                'default_language' => 'fr',
                'default_currency' => 'USD',
            ])
            ->assertSessionHasErrors(['default_language', 'default_currency']);
    }

    public function test_team_leader_gets_403_on_settings(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->get(route('admin.settings'))
            ->assertForbidden();
    }
}
