<?php

namespace App\Services\Valuation;

/**
 * Excel Valuation 07-2026 result formulas (same on every day sheet).
 *
 * Scores are 0–100 (Excel % / 100% = five stars). Stars = ROUND(percent/100*5).
 * التارجت العام is typed (B61); execution totals still SUM of B2B+B2C.
 */
class ValuationCalculator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function compute(array $input): array
    {
        $in = array_merge(ValuationFields::defaults(), $input);
        $salesAvg = $this->average([
            self::toPercent($in['sales_online']),
            self::toPercent($in['sales_customer_care']),
            self::toPercent($in['sales_social']),
            self::toPercent($in['sales_outdoor']),
            self::toPercent($in['sales_ornes']),
            self::toPercent($in['sales_google']),
        ]);

        return array_merge($this->targetBlock($in), [
            'sales_average' => $salesAvg,
            'stars' => $this->starMap($in, $salesAvg),
        ]);
    }

    public static function formatPercent(float $ratio): string
    {
        return number_format($ratio * 100, 2, '.', '').'%';
    }

    public static function toPercent(mixed $score): float
    {
        $value = is_numeric($score) ? (float) $score : 0.0;
        if ($value <= 1.0) {
            return $value * 100.0;
        }

        return $value;
    }

    public static function starsFromScore(mixed $score): int
    {
        $raw = self::toPercent($score) / 100 * 5;

        return (int) max(0, min(5, (int) floor($raw + 0.5)));
    }

    public static function starHtml(mixed $score): string
    {
        $n = self::starsFromScore($score);
        $html = '<span class="stars" dir="ltr">';
        for ($i = 1; $i <= 5; $i++) {
            $html .= '<span class="star'.($i <= $n ? ' on' : '').'">&#9733;</span>';
        }

        return $html.'</span>';
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    private function targetBlock(array $in): array
    {
        $tB2bC = $this->num($in['target_b2b_count']);
        $tB2cC = $this->num($in['target_b2c_count']);
        $aB2bC = $this->num($in['actual_b2b_count']);
        $aB2cC = $this->num($in['actual_b2c_count']);
        $tB2bA = $this->num($in['target_b2b_amount']);
        $tB2cA = $this->num($in['target_b2c_amount']);
        $aB2bA = $this->num($in['actual_b2b_amount']);
        $aB2cA = $this->num($in['actual_b2c_amount']);
        $tCount = $this->num($in['target_count_total']);
        $tAmount = $this->num($in['target_amount_total']);
        $kpiBase = $this->num($in['kpi_base']);
        $cashBase = $this->num($in['cashflow_base']);
        $aCount = $aB2bC + $aB2cC;
        $aAmount = $aB2bA + $aB2cA;
        $kpiRatio = $this->ratio($aAmount, $kpiBase);

        return [
            'target_count_total' => round($tCount, 2),
            'actual_count_total' => round($aCount, 2),
            'count_ratio' => $this->ratio($aCount, $tCount),
            'count_b2b_ratio' => $this->ratio($aB2bC, $tB2bC),
            'count_b2c_ratio' => $this->ratio($aB2cC, $tB2cC),
            'target_amount_total' => round($tAmount, 2),
            'actual_amount_total' => round($aAmount, 2),
            'amount_ratio' => $this->ratio($aAmount, $tAmount),
            'amount_b2b_ratio' => $this->ratio($aB2bA, $tB2bA),
            'amount_b2c_ratio' => $this->ratio($aB2cA, $tB2cA),
            'kpi_ratio' => $kpiRatio,
            'kpi_label' => $this->kpiLabel($kpiRatio),
            'cashflow_label' => $this->cashflowLabel($aAmount, $cashBase),
            'earnings_label' => $aAmount >= $cashBase ? 'high' : 'low',
        ];
    }

    /**
     * @param  list<mixed>  $values
     */
    private function average(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($values as $value) {
            $sum += $this->num($value);
        }

        return round($sum / count($values), 4);
    }

    private function ratio(float $num, float $den): float
    {
        if ($den == 0.0) {
            return 0.0;
        }

        return round($num / $den, 8);
    }

    private function kpiLabel(float $ratio): string
    {
        if ($ratio <= 0.70) {
            return 'loss';
        }
        if ($ratio <= 0.80) {
            return 'acceptable';
        }
        if ($ratio <= 1.00) {
            return 'gain';
        }

        return 'above';
    }

    private function cashflowLabel(float $actual, float $base): string
    {
        if ($actual < $base) {
            return 'negative';
        }
        if ($actual > $base) {
            return 'positive';
        }

        return 'safe';
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array<string, int>
     */
    private function starMap(array $in, float $salesAvg): array
    {
        $scores = [
            'laundry_king' => $in['laundry_king_score'],
            'laundry_mart' => $in['laundry_mart_score'],
            'customer_care_logistics' => $in['customer_care_logistics_score'],
            'sales' => $salesAvg,
            'drivers' => $in['drivers_score'],
            'marketing' => $in['marketing_score'],
            'graphic' => $in['graphic_score'],
            'social_requirements' => $in['social_requirements_score'],
            'hr_followup' => $in['hr_followup_score'],
            'software' => $in['software_score'],
            'accounting' => $in['accounting_score'],
            'top_management' => $in['top_management_score'],
            'quality' => $in['quality_score'],
            'customer_care' => $in['customer_care_score'],
        ];

        $out = [];
        foreach ($scores as $key => $score) {
            $out[$key] = self::starsFromScore($score);
        }

        return $out;
    }

    private function num(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }
}
