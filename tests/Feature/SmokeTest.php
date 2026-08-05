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
            ->assertSee('dir="rtl"', false)
            ->assertSee('rel="preload"', false)
            ->assertSee('cairo-400-arabic.woff2', false)
            ->assertSee('fonts/fonts.css', false);
    }

    public function test_english_login_preloads_latin_cairo(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/login')
            ->assertOk()
            ->assertSee('cairo-400-latin.woff2', false)
            ->assertDontSee('cairo-400-arabic.woff2', false);
    }

    public function test_cairo_font_faces_use_block_display(): void
    {
        $css = file_get_contents(public_path('fonts/fonts.css'));

        $this->assertNotFalse($css);
        $this->assertStringContainsString('font-family: \'Cairo\'', $css);
        $this->assertStringContainsString('font-display: block;', $css);
        $this->assertStringNotContainsString('font-display: swap;', $css);
        $this->assertStringNotContainsString('Tajawal', $css);
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
