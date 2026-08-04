<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 5.3 — language toggle AR/EN flips dir + translated chrome.
 */
class LocaleToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_arabic_default_is_rtl_and_english_toggle_is_ltr(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false);

        $this->from('/login')->get('/locale/en')
            ->assertRedirect('/login')
            ->assertSessionHas('locale', 'en');

        $this->get('/login')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('lang="en"', false)
            ->assertSee(__('auth.login'), false);

        $this->from('/login')->get('/locale/ar')
            ->assertRedirect('/login');

        $this->get('/login')
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false);
    }

    public function test_authenticated_screens_respect_locale(): void
    {
        $employee = User::factory()->employee()->create([
            'language_preference' => 'en',
        ]);

        $this->actingAs($employee)
            ->withSession(['locale' => 'en'])
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee(__('clock.title', [], 'en'), false);

        $this->actingAs($employee)
            ->withSession(['locale' => 'ar'])
            ->get(route('employee.salary'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('salary.title', [], 'ar'), false);

        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('admin.payroll.index'))
            ->assertOk()
            ->assertSee(__('payroll.title', [], 'en'), false);

        $this->actingAs($manager)
            ->withSession(['locale' => 'ar'])
            ->get(route('admin.reports.annual'))
            ->assertOk()
            ->assertSee(__('reports.title', [], 'ar'), false);
    }
}
