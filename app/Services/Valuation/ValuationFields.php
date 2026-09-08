<?php

namespace App\Services\Valuation;

/**
 * Daily valuation payload keys (Excel Valuation 07-2026).
 */
final class ValuationFields
{
    /** Default per-driver daily orders target when none is set (legacy sheets). */
    public const DRIVER_ORDERS_TARGET = 25;

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
            'drivers' => [],
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

    /**
     * Drop fully blank driver rows. Each row is either an employee (user_id)
     * or a manual name — not both. Includes per-driver target, score (0–100) + notes.
     *
     * @return list<array{user_id: int|null, name: string, orders: float, target: float, score: float, notes: string}>
     */
    public static function normalizeDrivers(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $userId = (int) ($row['user_id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            $notes = trim((string) ($row['notes'] ?? ''));
            $ordersRaw = $row['orders'] ?? null;
            $orders = is_numeric($ordersRaw) ? (float) $ordersRaw : 0.0;
            $ordersEmpty = $ordersRaw === null || $ordersRaw === '';
            $scoreRaw = $row['score'] ?? null;
            $scoreEmpty = $scoreRaw === null || $scoreRaw === '';
            $score = is_numeric($scoreRaw) ? (float) $scoreRaw : 0.0;
            $targetRaw = $row['target'] ?? null;
            $target = is_numeric($targetRaw) ? (float) $targetRaw : (float) self::DRIVER_ORDERS_TARGET;

            if ($userId <= 0 && $name === '' && ($ordersEmpty || $orders <= 0.0) && $scoreEmpty && $notes === '') {
                continue;
            }

            $out[] = [
                'user_id' => $userId > 0 ? $userId : null,
                'name' => $userId > 0 ? '' : mb_substr($name, 0, 150),
                'orders' => round(max(0.0, $orders), 2),
                'target' => round(max(0.0, $target), 2),
                'score' => round(max(0.0, min(100.0, $score)), 2),
                'notes' => mb_substr($notes, 0, 5000),
            ];
        }

        return $out;
    }

    /**
     * @param  array{user_id?: int|null, name?: string}  $row
     */
    public static function driverDisplayName(array $row, ?callable $resolveUserName = null): string
    {
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId > 0 && $resolveUserName !== null) {
            $resolved = trim((string) $resolveUserName($userId));
            if ($resolved !== '') {
                return $resolved;
            }
        }

        $name = trim((string) ($row['name'] ?? ''));

        return $name !== '' ? $name : '—';
    }

    public static function driverOverTarget(float $orders, ?float $target = null): float
    {
        $threshold = $target ?? (float) self::DRIVER_ORDERS_TARGET;

        return round(max(0.0, $orders - max(0.0, $threshold)), 2);
    }

    public static function driverUnderTarget(float $orders, ?float $target = null): float
    {
        $threshold = $target ?? (float) self::DRIVER_ORDERS_TARGET;

        return round(max(0.0, max(0.0, $threshold) - $orders), 2);
    }
}
