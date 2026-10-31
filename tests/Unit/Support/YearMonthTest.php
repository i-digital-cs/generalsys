<?php

namespace Tests\Unit\Support;

use App\Support\YearMonth;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YearMonthTest extends TestCase
{
    #[Test]
    public function start_does_not_overflow_on_day_31(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 15:00:00', 'UTC'));

        $this->assertSame('2026-09-01', YearMonth::start('2026-09')->toDateString());
        $this->assertSame('2026-02-01', YearMonth::start('2026-02')->toDateString());

        Carbon::setTestNow();
    }
}
