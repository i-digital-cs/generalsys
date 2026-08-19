<?php

namespace App\Services\Valuation;

/**
 * Daily valuation payload keys (Excel Valuation 07-2026).
 */
final class ValuationFields
{
    /** @var list<string> */
    public const SCORE_KEYS = [
        'laundry_king_score',
        'laundry_mart_score',
        'customer_care_logistics_score',
        'sales_online',
        'sales_customer_care',
        'sales_social',
        'sales_outdoor',
        'sales_ornes',
        'sales_google',
        'drivers_score',
        'marketing_score',
        'graphic_score',
        'social_requirements_score',
        'hr_followup_score',
        'software_score',
        'accounting_score',
        'top_management_score',
        'quality_score',
        'customer_care_score',
        'provider_mart',
        'provider_kings',
        'provider_golden',
        'provider_rug',
        'work_photo',
        'work_video',
        'work_software',
        'ads_google',
        'ads_meta',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $out = [
            'market_status' => '',
            'orders_movement' => 0,
            'growth_percent' => 0,
            'recession_index' => 0,
            'sales_spread' => 0,
            'marketing_spread' => 0,
            'haroon_news' => '',
            'laundry_king_positives' => '',
            'laundry_king_negatives' => '',
            'laundry_mart_positives' => '',
            'laundry_mart_negatives' => '',
            'customer_care_logistics_notes' => '',
            'sales_notes' => '',
            'drivers_notes' => '',
            'marketing_notes' => '',
            'graphic_notes' => '',
            'social_requirements_notes' => '',
            'hr_followup_notes' => '',
            'software_notes' => '',
            'accounting_notes' => '',
            'top_management_notes' => '',
            'quality_notes' => '',
            'customer_care_notes' => '',
            'target_b2b_count' => 40,
            'target_b2c_count' => 5,
            'actual_b2b_count' => 0,
            'actual_b2c_count' => 0,
            'target_count_total' => 45,
            'target_b2b_amount' => 5000,
            'target_b2c_amount' => 7000,
            'actual_b2b_amount' => 0,
            'actual_b2c_amount' => 0,
            'target_amount_total' => 12000,
            'kpi_base' => 2500,
            'cashflow_base' => 2460,
        ];

        foreach (self::SCORE_KEYS as $key) {
            $out[$key] = 0;
        }

        return $out;
    }
}
