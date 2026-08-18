<?php

namespace Tests\Unit\Auth;

use App\Exceptions\ImpersonationException;
use App\Models\User;
use App\Services\Admin\SettingService;
use App\Services\Auth\ImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ImpersonationService $service;

    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ImpersonationService::class);
        $this->settings = app(SettingService::class);
    }

    public function test_disabled_by_default(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        $this->assertFalse($this->service->isEnabled());
        $this->assertFalse($this->service->actorMayStart($manager));
        $this->assertFalse($this->service->canStart($manager, $employee));
    }

    public function test_manager_can_start_when_enabled(): void
    {
        $this->settings->set(SettingService::KEY_IMPERSONATION_ENABLED, '1');
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $assistant = User::factory()->assistant()->create();

        $this->assertTrue($this->service->isEnabled());
        $this->assertTrue($this->service->canStart($manager, $employee));
        $this->assertFalse($this->service->canStart($manager, $manager));
        $this->assertFalse($this->service->actorMayStart($assistant));
        $this->assertFalse($this->service->canStart($assistant, $employee));
    }

    public function test_start_throws_when_disabled(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        $this->expectException(ImpersonationException::class);
        $this->service->start($manager, $employee);
    }
}
