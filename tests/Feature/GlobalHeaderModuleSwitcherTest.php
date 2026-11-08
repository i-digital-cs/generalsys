<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalHeaderModuleSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_two_row_global_module_switcher_markup(): void
    {
        $manager = User::factory()->manager()->create();

        $html = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-hp-app-header', $html);
        $this->assertStringContainsString('data-hp-module-switcher', $html);
        $this->assertStringContainsString('hp-app-header__chrome', $html);
        $this->assertStringContainsString('hp-module-tab--active', $html);
        $this->assertStringContainsString(__('nav.gnrl_operations_test'), $html);
        $this->assertStringContainsString(__('portal.hr'), $html);
        $this->assertStringContainsString(__('portal.settings'), $html);
        $this->assertStringContainsString(route('portal.hr'), $html);
        $this->assertStringContainsString(route('admin.settings'), $html);
        // Single switcher instance — not duplicated for mobile/desktop.
        $this->assertSame(1, substr_count($html, 'data-hp-module-switcher'));
    }

    public function test_employee_module_switcher_omits_settings(): void
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE']);

        $html = $this->actingAs($employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-hp-module-switcher', $html);
        $this->assertStringContainsString(__('portal.hr'), $html);
        $this->assertStringNotContainsString(route('admin.settings'), $html);
    }
}
