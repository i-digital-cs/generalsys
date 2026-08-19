<?php

namespace Tests\Unit\Valuation;

use App\Models\User;
use App\Models\ValuationSheet;
use App\Services\Valuation\ValuationCalculator;
use App\Services\Valuation\ValuationFields;
use App\Services\Valuation\ValuationStatsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValuationStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', config('app.timezone')));
        app()->setLocale('en');
    }

    public function test_month_snapshot_sums_valuation_amounts(): void
    {
        $manager = User::factory()->manager()->create();
        $hamad = User::factory()->employee()->create();
        $calc = new ValuationCalculator;
        $input = array_merge(ValuationFields::defaults(), [
            'actual_b2b_amount' => 1274.55,
            'actual_b2c_amount' => 32,
            'target_amount_total' => 12000,
            'actual_b2b_count' => 17,
            'actual_b2c_count' => 0,
            'kpi_base' => 2500,
        ]);
        $result = $calc->compute($input);

        ValuationSheet::query()->create([
            'user_id' => $hamad->id,
            'module' => 'service',
            'date' => '2026-08-19',
            'status' => ValuationSheet::STATUS_SUBMITTED,
            'input' => $input,
            'result' => $result,
        ]);

        $stats = app(ValuationStatsService::class)->monthSnapshot($manager, 'service');

        $this->assertSame(1, $stats['sheets']);
        $this->assertEqualsWithDelta(1306.55, $stats['gross'], 0.01);
        $this->assertEqualsWithDelta(12000.0, $stats['target'], 0.01);
        $this->assertEqualsWithDelta(12000 - 1306.55, $stats['gap'], 0.01);
        $this->assertEqualsWithDelta(1274.55, $stats['b2b'], 0.01);
        $this->assertEqualsWithDelta(32.0, $stats['b2c'], 0.01);
        $this->assertEqualsWithDelta(17.0, $stats['b2b_count'], 0.01);
        $this->assertEqualsWithDelta(1306.55 / 2500, $stats['kpi_avg'], 0.0001);
    }

    public function test_non_manager_sees_only_own_sheets(): void
    {
        $hamad = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();
        $calc = new ValuationCalculator;

        foreach ([$hamad, $other] as $user) {
            $input = array_merge(ValuationFields::defaults(), ['actual_b2b_amount' => 100, 'target_amount_total' => 1000]);
            ValuationSheet::query()->create([
                'user_id' => $user->id,
                'module' => 'service',
                'date' => '2026-08-19',
                'status' => ValuationSheet::STATUS_SUBMITTED,
                'input' => $input,
                'result' => $calc->compute($input),
            ]);
        }

        $own = app(ValuationStatsService::class)->monthSnapshot($hamad, 'service');
        $this->assertSame(1, $own['sheets']);
        $this->assertEqualsWithDelta(100.0, $own['gross'], 0.01);
    }
}
