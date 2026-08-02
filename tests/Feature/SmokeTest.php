<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_returns_200(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    public function test_locale_route_stores_session_and_redirects_back(): void
    {
        $this->from('/login')->get('/locale/en')
            ->assertRedirect('/login')
            ->assertSessionHas('locale', 'en');

        $this->get('/login')->assertSee('dir="ltr"', false);
    }

    public function test_locale_route_rejects_unknown_locale(): void
    {
        $this->get('/locale/fr')->assertNotFound();
    }
}
