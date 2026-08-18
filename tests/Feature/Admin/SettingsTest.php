<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\Payroll\PayrollCalculationInput;
use App\Services\Payroll\PayrollCalculator;
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
                'multipliers' => [
                    'friday_full_time' => 1,
                    'friday_online' => 0.5,
                    'day_off_full_time' => 2,
                    'day_off_online' => 1.5,
                    'overtime' => 1.5,
                    'official_holiday_work' => 1,
                    'extra_pay' => 1,
                ],
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertDatabaseHas('settings', ['key' => 'default_language', 'value' => 'en']);
        $this->assertDatabaseHas('settings', ['key' => 'default_currency', 'value' => 'AED']);
    }

    public function test_manager_can_enable_impersonation(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('impersonation_enabled');

        $this->actingAs($manager)
            ->put(route('admin.settings.update'), $this->settingsPayload([
                'impersonation_enabled' => '1',
            ]))
            ->assertRedirect(route('admin.settings'));

        $this->assertDatabaseHas('settings', [
            'key' => 'impersonation_enabled',
            'value' => '1',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'default_language' => 'ar',
            'default_currency' => 'EGP',
            'multipliers' => [
                'friday_full_time' => 1,
                'friday_online' => 0.5,
                'day_off_full_time' => 2,
                'day_off_online' => 1.5,
                'overtime' => 1.5,
                'official_holiday_work' => 1,
                'extra_pay' => 1,
            ],
        ], $overrides);
    }

    public function test_manager_can_change_overtime_multiplier(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->put(route('admin.settings.update'), [
                'default_language' => 'ar',
                'default_currency' => 'EGP',
                'multipliers' => [
                    'friday_full_time' => 1,
                    'friday_online' => 0.5,
                    'day_off_full_time' => 2,
                    'day_off_online' => 1.5,
                    'overtime' => 1.0,
                    'official_holiday_work' => 1,
                    'extra_pay' => 1,
                ],
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertDatabaseHas('settings', [
            'key' => 'payroll.multiplier.overtime',
            'value' => '1.0000',
        ]);

        $result = app(PayrollCalculator::class)->calculate(
            new PayrollCalculationInput(
                baseSalary: 5000,
                overtimeHours: 8,
            )
        );

        // (5000/30/8) × 8 × 1.0 ≈ 166.67
        $this->assertEqualsWithDelta(166.67, $result->overtimePay, 0.01);
    }

    public function test_settings_validation_rejects_unknown_values(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->put(route('admin.settings.update'), [
                'default_language' => 'fr',
                'default_currency' => 'USD',
                'multipliers' => [
                    'friday_full_time' => 1,
                    'friday_online' => 0.5,
                    'day_off_full_time' => 2,
                    'day_off_online' => 1.5,
                    'overtime' => 1.5,
                    'official_holiday_work' => 1,
                    'extra_pay' => 1,
                ],
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
