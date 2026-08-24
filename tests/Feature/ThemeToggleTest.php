<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-9.13 — light/dark theme toggle (localStorage client-side; markup + boot script).
 */
class ThemeToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_includes_theme_boot_and_toggle(): void
    {
        $employee = User::factory()->employee()->create([
            'language_preference' => 'en',
        ]);

        $html = $this->actingAs($employee)
            ->withSession(['locale' => 'en'])
            ->get(route('portal.home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("localStorage.getItem('hr-pro-theme')", $html);
        $this->assertStringContainsString('data-theme-toggle', $html);
        $this->assertStringContainsString('data-theme-icon', $html);
        $this->assertStringContainsString(__('general.theme_to_dark', [], 'en'), $html);
        $this->assertStringContainsString(__('general.theme_dark', [], 'en'), $html);
    }

    public function test_login_layout_has_no_theme_toggle(): void
    {
        $html = $this->get('/login')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-theme-toggle', $html);
        $this->assertStringNotContainsString("localStorage.getItem('hr-pro-theme')", $html);
    }

    public function test_theme_toggle_renders_in_arabic(): void
    {
        $employee = User::factory()->employee()->create([
            'language_preference' => 'ar',
        ]);

        $this->actingAs($employee)
            ->withSession(['locale' => 'ar'])
            ->get(route('portal.home'))
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee(__('general.theme_to_dark', [], 'ar'), false)
            ->assertSee(__('general.theme_dark', [], 'ar'), false);
    }
}
