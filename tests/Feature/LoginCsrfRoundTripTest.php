<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginCsrfRoundTripTest extends TestCase
{
    public function test_login_page_ok_when_preprod_gate_disabled(): void
    {
        config(['preprod.access_key' => null]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_login_page_hidden_when_preprod_gate_enabled(): void
    {
        config(['preprod.access_key' => 'roundtrip-key']);

        $this->get('/login')->assertNotFound();
    }

    public function test_login_with_session_token_passes_csrf_gate(): void
    {
        config(['preprod.access_key' => null]);

        $this->get('/login')->assertOk();

        // PHPUnit skips CSRF verification; a 419 here would still be wrong plumbing.
        $response = $this->post('/login', [
            '_token' => session()->token(),
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertNotSame(419, $response->status());
    }

    public function test_login_with_preprod_cookie_passes_csrf(): void
    {
        config(['preprod.access_key' => 'roundtrip-key']);
        $hash = hash('sha256', 'roundtrip-key');

        $this->withCookie('preprod_access', $hash)
            ->get('/login')
            ->assertOk();

        $response = $this->withCookie('preprod_access', $hash)
            ->post('/login', [
                '_token' => session()->token(),
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);

        $this->assertNotSame(419, $response->status());
    }
}
