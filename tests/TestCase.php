<?php

namespace Tests;

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
}
