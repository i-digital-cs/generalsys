<?php

namespace Tests\Unit\GeneralHouse;

use App\Support\GeneralHouse\GhDateTimePresenter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GhDateTimePresenterTest extends TestCase
{
    public function test_formats_utc_instant_as_dubai_twelve_hour_english(): void
    {
        app()->setLocale('en');
        config(['general_house.timezone' => 'Asia/Dubai', 'app.timezone' => 'Africa/Cairo']);

        // 10:00 UTC = 14:00 Asia/Dubai
        $at = Carbon::parse('2026-11-19 10:00:00', 'UTC');

        $this->assertSame('Asia/Dubai', GhDateTimePresenter::timezone());
        $this->assertSame('2:00 PM', GhDateTimePresenter::format($at, GhDateTimePresenter::MODE_TIME));
        $this->assertStringContainsString('2:00 PM', (string) GhDateTimePresenter::format($at));
        $this->assertSame('2026-11-19', GhDateTimePresenter::format($at, GhDateTimePresenter::MODE_DATE));
        $this->assertSame('🇦🇪 UAE', GhDateTimePresenter::uaeBadge());
        $this->assertStringContainsString('Asia/Dubai', GhDateTimePresenter::selectionNotice());
        $this->assertStringContainsString('browser', strtolower(GhDateTimePresenter::selectionNotice()));
    }

    public function test_formats_arabic_meridiem(): void
    {
        app()->setLocale('ar');
        config(['general_house.timezone' => 'Asia/Dubai']);

        $morning = Carbon::parse('2026-11-19 06:00:00', 'UTC'); // 10:00 Dubai
        $evening = Carbon::parse('2026-11-19 10:00:00', 'UTC'); // 14:00 Dubai

        $this->assertSame('10:00 ص', GhDateTimePresenter::format($morning, GhDateTimePresenter::MODE_TIME));
        $this->assertSame('2:00 م', GhDateTimePresenter::format($evening, GhDateTimePresenter::MODE_TIME));
        $this->assertSame('🇦🇪 الإمارات', GhDateTimePresenter::uaeBadge());
    }

    public function test_does_not_mutate_app_timezone(): void
    {
        config(['app.timezone' => 'Africa/Cairo', 'general_house.timezone' => 'Asia/Dubai']);
        GhDateTimePresenter::format(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->assertSame('Africa/Cairo', (string) config('app.timezone'));
    }

    public function test_null_returns_null(): void
    {
        $this->assertNull(GhDateTimePresenter::format(null));
        $this->assertSame('—', GhDateTimePresenter::parts(null)['full']);
    }

    public function test_browser_cairo_wall_clock_string_is_interpreted_by_services_as_dubai_elsewhere(): void
    {
        app()->setLocale('en');
        // Presentation helper always projects to Dubai; naive input parsing stays in GhOrderService.
        config(['general_house.timezone' => 'Asia/Dubai', 'app.timezone' => 'Africa/Cairo']);
        $parts = GhDateTimePresenter::parts(Carbon::parse('2026-11-19 14:00:00', 'Asia/Dubai')->utc());
        $this->assertSame('2:00 PM', $parts['time']);
    }
}
