<?php

namespace Tests\Unit\Valuation;

use App\Services\Valuation\ValuationCalculator;
use Tests\TestCase;

class ValuationCalculatorTest extends TestCase
{
    public function test_excel_1_july_2026_kpi_and_cashflow_labels(): void
    {
        $result = (new ValuationCalculator)->compute([
            'actual_b2b_count' => 17,
            'actual_b2c_count' => 0,
            'target_b2b_count' => 40,
            'target_b2c_count' => 5,
            'actual_b2b_amount' => 1274.55,
            'actual_b2c_amount' => 32,
            'target_b2b_amount' => 5000,
            'target_b2c_amount' => 7000,
            'kpi_base' => 2500,
            'cashflow_base' => 2460,
            'sales_online' => 0,
            'sales_customer_care' => 0,
            'sales_social' => 0,
            'sales_outdoor' => 0,
            'sales_ornes' => 0,
            'sales_google' => 0,
            'customer_care_logistics_score' => 0.9,
        ]);

        $this->assertSame(0.0, $result['sales_average']);
        $this->assertSame(45.0, $result['target_count_total']);
        $this->assertSame(17.0, $result['actual_count_total']);
        $this->assertEqualsWithDelta(17 / 45, $result['count_ratio'], 0.000001);
        $this->assertEqualsWithDelta(17 / 40, $result['count_b2b_ratio'], 0.000001);
        $this->assertSame(0.0, $result['count_b2c_ratio']);
        $this->assertEqualsWithDelta(1306.55, $result['actual_amount_total'], 0.001);
        $this->assertEqualsWithDelta(1306.55 / 12000, $result['amount_ratio'], 0.000001);
        $this->assertEqualsWithDelta(1274.55 / 5000, $result['amount_b2b_ratio'], 0.000001);
        $this->assertEqualsWithDelta(32 / 7000, $result['amount_b2c_ratio'], 0.000001);
        $this->assertEqualsWithDelta(1306.55 / 2500, $result['kpi_ratio'], 0.000001);
        $this->assertSame('37.78%', ValuationCalculator::formatPercent($result['count_ratio']));
        $this->assertSame('42.50%', ValuationCalculator::formatPercent($result['count_b2b_ratio']));
        $this->assertSame('10.89%', ValuationCalculator::formatPercent($result['amount_ratio']));
        $this->assertSame('25.49%', ValuationCalculator::formatPercent($result['amount_b2b_ratio']));
        $this->assertSame('0.46%', ValuationCalculator::formatPercent($result['amount_b2c_ratio']));
        $this->assertSame('52.26%', ValuationCalculator::formatPercent($result['kpi_ratio']));
        $this->assertSame('loss', $result['kpi_label']);
        $this->assertSame('negative', $result['cashflow_label']);
        $this->assertSame('low', $result['earnings_label']);
        $this->assertSame(5, $result['stars']['customer_care_logistics']);
    }

    public function test_scores_are_percent_and_stars_match_excel(): void
    {
        $this->assertSame(5, ValuationCalculator::starsFromScore(100));
        $this->assertSame(4, ValuationCalculator::starsFromScore(80));
        $this->assertSame(5, ValuationCalculator::starsFromScore(90));
        $this->assertSame(5, ValuationCalculator::starsFromScore(0.9));
        $this->assertSame(5, ValuationCalculator::starsFromScore(1));
        $this->assertSame(4, ValuationCalculator::starsFromScore(70));

        $independent = (new ValuationCalculator)->compute([
            'target_count_total' => 50,
            'actual_b2b_count' => 17,
            'actual_b2c_count' => 0,
            'target_b2b_count' => 40,
            'target_b2c_count' => 5,
            'drivers_score' => 80,
        ]);
        $this->assertSame(50.0, $independent['target_count_total']);
        $this->assertEqualsWithDelta(17 / 50, $independent['count_ratio'], 0.000001);
        $this->assertSame(4, $independent['stars']['drivers']);
        $this->assertStringContainsString('class="stars"', ValuationCalculator::starHtml(80));
        $html = ValuationCalculator::starHtml(80);
        $this->assertSame(4, substr_count($html, 'class="star on"'));
        $this->assertSame(1, substr_count($html, 'class="star"'));
    }

    public function test_kpi_bands_and_sales_average(): void
    {
        $calc = new ValuationCalculator;

        $this->assertSame('acceptable', $calc->compute(['actual_b2b_amount' => 1875, 'kpi_base' => 2500])['kpi_label']);
        $this->assertSame('gain', $calc->compute(['actual_b2b_amount' => 2500, 'kpi_base' => 2500])['kpi_label']);
        $this->assertSame('above', $calc->compute(['actual_b2b_amount' => 2600, 'kpi_base' => 2500])['kpi_label']);
        $this->assertSame('safe', $calc->compute(['actual_b2b_amount' => 2460, 'cashflow_base' => 2460])['cashflow_label']);
        $this->assertSame('positive', $calc->compute(['actual_b2b_amount' => 2500, 'cashflow_base' => 2460])['cashflow_label']);
        $this->assertSame('high', $calc->compute(['actual_b2b_amount' => 2460, 'cashflow_base' => 2460])['earnings_label']);

        $avg = $calc->compute([
            'sales_online' => 100,
            'sales_customer_care' => 50,
            'sales_social' => 50,
            'sales_outdoor' => 0,
            'sales_ornes' => 0,
            'sales_google' => 100,
        ]);
        $this->assertEqualsWithDelta(50.0, $avg['sales_average'], 0.0001);
        $this->assertSame(3, $avg['stars']['sales']);
    }
}
