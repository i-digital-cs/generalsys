<?php

namespace App\Services\Valuation;

use App\Support\AppModule;

/**
 * Service vs Mohra (product) valuation labels — same payload keys and formulas.
 * Mohra names follow docs/valuation mohra store.xlsx.
 */
final class ValuationCopy
{
    /** @var list<string> */
    private const PRODUCT_HIDDEN_DEPTS = [
        'social_requirements',
        'software',
        'top_management',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(string $module): array
    {
        $out = ValuationFields::defaults();

        if ($module !== AppModule::PRODUCT) {
            return $out;
        }

        $out['target_b2b_count'] = 15;
        $out['target_b2c_count'] = 20;
        $out['target_count_total'] = 35;
        $out['target_b2b_amount'] = 0;
        $out['target_b2c_amount'] = 0;
        $out['target_amount_total'] = 0;
        $out['kpi_base'] = 0;
        $out['drivers'] = [];

        return $out;
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public static function line(string $module, string $key, array $replace = []): string
    {
        if ($module === AppModule::PRODUCT) {
            $overlay = 'valuation.mohra.'.$key;
            if (trans()->has($overlay)) {
                return __($overlay, $replace);
            }
        }

        return __('valuation.'.$key, $replace);
    }

    public static function showHaroonNewsInMarket(string $module): bool
    {
        return $module !== AppModule::PRODUCT;
    }

    public static function showOperationalNotesAfterSuppliers(string $module): bool
    {
        return $module === AppModule::PRODUCT;
    }

    public static function showOperationFlowHeader(string $module): bool
    {
        return $module === AppModule::PRODUCT;
    }

    public static function showLogisticsBlock(string $module): bool
    {
        return $module !== AppModule::PRODUCT;
    }

    public static function showDynamicDrivers(string $module): bool
    {
        return $module !== AppModule::PRODUCT;
    }

    public static function showProviders(string $module): bool
    {
        return $module !== AppModule::PRODUCT;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function departmentBlocks(string $module): array
    {
        $all = [
            ['marketing', 'marketing_score', 'marketing_notes'],
            ['graphic', 'graphic_score', 'graphic_notes'],
            ['social_requirements', 'social_requirements_score', 'social_requirements_notes'],
            ['hr_followup', 'hr_followup_score', 'hr_followup_notes'],
            ['software', 'software_score', 'software_notes'],
            ['accounting', 'accounting_score', 'accounting_notes'],
            ['top_management', 'top_management_score', 'top_management_notes'],
            ['quality', 'quality_score', 'quality_notes'],
            ['customer_care', 'customer_care_score', 'customer_care_notes'],
        ];

        if ($module !== AppModule::PRODUCT) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            fn (array $row): bool => ! in_array($row[0], self::PRODUCT_HIDDEN_DEPTS, true),
        ));
    }

    /**
     * @return list<string>
     */
    public static function salesChannelKeys(): array
    {
        return [
            'sales_online',
            'sales_customer_care',
            'sales_social',
            'sales_outdoor',
            'sales_ornes',
            'sales_google',
        ];
    }
}
