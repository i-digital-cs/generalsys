<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Default Cairo-ish coordinates for clock HTTP posts (T-7.2 GPS required).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function withClockGps(array $extra = []): array
    {
        return array_merge([
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'accuracy' => 15,
        ], $extra);
    }

    /** Keep clock HTTP tests inside the default FULL_TIME window (10:00–19:00). */
    protected function freezeClockToMorningShift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 10:15:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
