<?php

namespace Tests\Unit\Attendance;

use App\Services\Attendance\ClockCtaColorService;
use Carbon\Carbon;
use Tests\TestCase;

class ClockCtaColorTest extends TestCase
{
    private ClockCtaColorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClockCtaColorService::class);
    }

    public function test_ten_am_start_windows(): void
    {
        $start = Carbon::parse('2026-08-24 10:00:00', config('app.timezone'));

        $this->assertSame('orange', $this->service->color($start, $start->copy()->setTime(9, 44)));
        $this->assertSame('yellow', $this->service->color($start, $start->copy()->setTime(9, 45)));
        $this->assertSame('yellow', $this->service->color($start, $start->copy()->setTime(9, 54)));
        $this->assertSame('green', $this->service->color($start, $start->copy()->setTime(9, 55)));
        $this->assertSame('green', $this->service->color($start, $start->copy()->setTime(10, 0)));
        $this->assertSame('green', $this->service->color($start, $start->copy()->setTime(10, 5)));
        $this->assertSame('yellow', $this->service->color($start, $start->copy()->setTime(10, 6)));
        $this->assertSame('yellow', $this->service->color($start, $start->copy()->setTime(10, 30)));
        $this->assertSame('orange', $this->service->color($start, $start->copy()->setTime(10, 31)));
    }

    public function test_six_pm_end_uses_the_same_windows(): void
    {
        $end = Carbon::parse('2026-08-24 18:00:00', config('app.timezone'));

        $this->assertSame('orange', $this->service->color($end, $end->copy()->setTime(17, 44)));
        $this->assertSame('green', $this->service->color($end, $end->copy()->setTime(17, 55)));
        $this->assertSame('yellow', $this->service->color($end, $end->copy()->setTime(18, 20)));
        $this->assertSame('orange', $this->service->color($end, $end->copy()->setTime(18, 31)));
    }

    public function test_missing_schedule_is_red(): void
    {
        $this->assertSame('red', $this->service->color(null, now()));
    }
}
