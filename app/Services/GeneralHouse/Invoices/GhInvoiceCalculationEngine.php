<?php

namespace App\Services\GeneralHouse\Invoices;

use App\Support\GeneralHouse\GhDiscountType;
use App\Support\GeneralHouse\GhInvoiceAdjustmentType;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhMinOrderOverrideType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for GH invoice arithmetic.
 * Controllers / Blade / JS are consumers only.
 */
class GhInvoiceCalculationEngine
{
    /**
     * @param  list<array{
     *   offering_id?: int,
     *   quantity: int|string,
     *   unit_price: string|float|int,
     *   tax_class: string,
     *   item_discount_type?: string,
     *   item_discount_value?: string|float|int,
     *   sort_order?: int,
     *   received_line_id?: int|null,
     *   quantity_received?: int|null,
     *   price_source?: string,
     *   contract_id?: int|null,
     *   product_name_ar?: string,
     *   product_name_en?: string|null,
     *   treatment_name_ar?: string,
     *   treatment_name_en?: string|null,
     *   category_name_ar?: string|null,
     *   category_name_en?: string|null,
     *   category_id?: int|null,
     *   product_id?: int|null,
     *   treatment_id?: int|null,
     *   notes?: string|null,
     * }>  $lines
     * @param  array{
     *   invoice_discount_type?: string,
     *   invoice_discount_value?: string|float|int,
     *   min_order_amount?: string|float|int|null,
     *   min_order_policy?: string|null,
     *   min_order_override_type?: string,
     *   min_order_override_amount?: string|float|int|null,
     *   min_order_tax_class?: string|null,
     *   standard_vat_rate?: string|float|int|null,
     * }  $header
     * @return array<string, mixed>
     */
    public function calculate(array $lines, array $header = []): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => [__('gh.errors.invoice_lines_required')],
            ]);
        }

        $vatRate = GhMoney::of($header['standard_vat_rate'] ?? config('general_house.standard_vat_rate', '5.00'));
        $invoiceDiscountType = (string) ($header['invoice_discount_type'] ?? GhDiscountType::NONE);
        $invoiceDiscountValue = GhMoney::of($header['invoice_discount_value'] ?? '0');
        $minPolicy = (string) ($header['min_order_policy'] ?? GhLaunchConfig::defaultMinOrderPolicy());
        $minOverride = (string) ($header['min_order_override_type'] ?? GhMinOrderOverrideType::APPLY_DEFAULT);
        $zoneMin = $header['min_order_amount'] ?? null;
        $overrideAmount = $header['min_order_override_amount'] ?? null;
        $minTaxClass = $header['min_order_tax_class'] ?? config('general_house.min_order_surcharge_tax_class');

        $computedLines = [];
        $linesGross = '0';
        $itemDiscountTotal = '0';
        $eligiblePool = '0';

        foreach (array_values($lines) as $i => $raw) {
            $qty = (int) ($raw['quantity'] ?? 0);
            if ($qty < 1) {
                throw ValidationException::withMessages([
                    "lines.$i.quantity" => [__('gh.errors.invoice_qty_invalid')],
                ]);
            }

            $unit = GhMoney::of($raw['unit_price'] ?? '0');
            if (GhMoney::cmp($unit, '0') < 0) {
                throw ValidationException::withMessages([
                    "lines.$i.unit_price" => [__('gh.errors.invoice_unit_price_invalid')],
                ]);
            }

            $taxClass = (string) ($raw['tax_class'] ?? GhTaxClass::STANDARD_RATED);
            if (! in_array($taxClass, GhTaxClass::ALL, true)) {
                throw ValidationException::withMessages([
                    "lines.$i.tax_class" => [__('gh.errors.invoice_tax_class_invalid')],
                ]);
            }

            $gross = GhMoney::roundHalfUp(GhMoney::mul($unit, (string) $qty));
            $discType = (string) ($raw['item_discount_type'] ?? GhDiscountType::NONE);
            $discValue = GhMoney::of($raw['item_discount_value'] ?? '0');
            $itemDisc = $this->computeDiscount($gross, $discType, $discValue, "lines.$i");
            $afterItem = GhMoney::roundHalfUp(GhMoney::sub($gross, $itemDisc));
            if (GhMoney::cmp($afterItem, '0') < 0) {
                throw ValidationException::withMessages([
                    "lines.$i.item_discount_value" => [__('gh.errors.invoice_negative_line')],
                ]);
            }

            $lineRate = GhTaxClass::isVatApplicable($taxClass) ? $vatRate : '0.00';
            $qtyReceived = isset($raw['quantity_received']) ? (int) $raw['quantity_received'] : null;

            $computedLines[] = [
                'index' => $i,
                'offering_id' => (int) ($raw['offering_id'] ?? 0),
                'received_line_id' => $raw['received_line_id'] ?? null,
                'sort_order' => (int) ($raw['sort_order'] ?? $i),
                'quantity' => $qty,
                'quantity_received' => $qtyReceived,
                'qty_differs_from_received' => $qtyReceived !== null && $qtyReceived !== $qty,
                'unit_price' => $unit,
                'tax_class' => $taxClass,
                'tax_rate' => $lineRate,
                'item_discount_type' => $discType,
                'item_discount_value' => $discValue,
                'item_discount_amount' => $itemDisc,
                'line_gross' => $gross,
                'line_after_item_discount' => $afterItem,
                'allocated_invoice_discount' => '0.00',
                'taxable_base' => '0.00',
                'tax_amount' => '0.00',
                'line_total' => '0.00',
                'price_source' => (string) ($raw['price_source'] ?? 'STANDARD'),
                'contract_id' => $raw['contract_id'] ?? null,
                'product_name_ar' => (string) ($raw['product_name_ar'] ?? ''),
                'product_name_en' => $raw['product_name_en'] ?? null,
                'treatment_name_ar' => (string) ($raw['treatment_name_ar'] ?? ''),
                'treatment_name_en' => $raw['treatment_name_en'] ?? null,
                'category_name_ar' => $raw['category_name_ar'] ?? null,
                'category_name_en' => $raw['category_name_en'] ?? null,
                'category_id' => $raw['category_id'] ?? null,
                'product_id' => $raw['product_id'] ?? null,
                'treatment_id' => $raw['treatment_id'] ?? null,
                'notes' => $raw['notes'] ?? null,
            ];

            $linesGross = GhMoney::add($linesGross, $gross);
            $itemDiscountTotal = GhMoney::add($itemDiscountTotal, $itemDisc);
            $eligiblePool = GhMoney::add($eligiblePool, $afterItem);
        }

        $linesGross = GhMoney::roundHalfUp($linesGross);
        $itemDiscountTotal = GhMoney::roundHalfUp($itemDiscountTotal);
        $eligiblePool = GhMoney::roundHalfUp($eligiblePool);

        $invoiceDiscountAmount = $this->computeDiscount(
            $eligiblePool,
            $invoiceDiscountType,
            $invoiceDiscountValue,
            'invoice_discount_value'
        );
        if (GhMoney::cmp($invoiceDiscountAmount, $eligiblePool) > 0) {
            throw ValidationException::withMessages([
                'invoice_discount_value' => [__('gh.errors.invoice_discount_exceeds_base')],
            ]);
        }

        $allocations = $this->allocateProportionally(
            array_map(fn ($l) => $l['line_after_item_discount'], $computedLines),
            $invoiceDiscountAmount
        );

        $linesAfterDiscounts = '0';
        foreach ($computedLines as $i => &$line) {
            $alloc = $allocations[$i] ?? '0.00';
            $line['allocated_invoice_discount'] = $alloc;
            $base = GhMoney::roundHalfUp(GhMoney::sub($line['line_after_item_discount'], $alloc));
            if (GhMoney::cmp($base, '0') < 0) {
                $base = '0.00';
            }
            $line['taxable_base'] = $base;
            $tax = '0.00';
            if (GhTaxClass::isVatApplicable($line['tax_class'])) {
                $tax = GhMoney::roundHalfUp(GhMoney::div(GhMoney::mul($base, $line['tax_rate']), '100'));
            }
            $line['tax_amount'] = $tax;
            $line['line_total'] = GhMoney::roundHalfUp(GhMoney::add($base, $tax));
            $linesAfterDiscounts = GhMoney::add($linesAfterDiscounts, $base);
        }
        unset($line);

        $linesAfterDiscounts = GhMoney::roundHalfUp($linesAfterDiscounts);

        $minResult = $this->resolveMinimumOrder(
            $linesAfterDiscounts,
            $zoneMin !== null ? GhMoney::of($zoneMin) : null,
            $minPolicy,
            $minOverride,
            $overrideAmount !== null ? GhMoney::of($overrideAmount) : null,
            is_string($minTaxClass) ? $minTaxClass : null,
            $vatRate
        );

        $adjustments = [];
        if (GhMoney::cmp($invoiceDiscountAmount, '0') > 0) {
            $adjustments[] = [
                'adjustment_type' => GhInvoiceAdjustmentType::INVOICE_DISCOUNT,
                'amount' => $invoiceDiscountAmount,
                'tax_class' => null,
                'tax_rate' => null,
                'tax_amount' => '0.00',
                'label_en' => 'Invoice discount',
                'label_ar' => 'خصم الفاتورة',
            ];
        }

        $minAdj = $minResult['adjustment_amount'];
        $minTax = '0.00';
        if (GhMoney::cmp($minAdj, '0') > 0) {
            if ($minResult['tax_class'] !== null && GhTaxClass::isVatApplicable($minResult['tax_class'])) {
                $minTax = GhMoney::roundHalfUp(GhMoney::div(GhMoney::mul($minAdj, $vatRate), '100'));
            }
            $adjustments[] = [
                'adjustment_type' => GhInvoiceAdjustmentType::MIN_ORDER_SURCHARGE,
                'amount' => $minAdj,
                'tax_class' => $minResult['tax_class'],
                'tax_rate' => ($minResult['tax_class'] !== null && GhTaxClass::isVatApplicable($minResult['tax_class']))
                    ? $vatRate
                    : '0.00',
                'tax_amount' => $minTax,
                'label_en' => 'Minimum order adjustment',
                'label_ar' => 'تعديل الحد الأدنى للطلب',
            ];
        }

        $lineVat = '0';
        $taxableBase = '0';
        foreach ($computedLines as $line) {
            $lineVat = GhMoney::add($lineVat, $line['tax_amount']);
            if (GhTaxClass::isVatApplicable($line['tax_class'])) {
                $taxableBase = GhMoney::add($taxableBase, $line['taxable_base']);
            }
        }
        if ($minResult['tax_class'] !== null && GhTaxClass::isVatApplicable($minResult['tax_class'])) {
            $taxableBase = GhMoney::add($taxableBase, $minAdj);
        }

        $vatAmount = GhMoney::roundHalfUp(GhMoney::add($lineVat, $minTax));
        $taxableBase = GhMoney::roundHalfUp($taxableBase);
        $totalPayable = GhMoney::roundHalfUp(GhMoney::add(
            GhMoney::add($linesAfterDiscounts, $minAdj),
            $vatAmount
        ));

        // Invariant: sum(line_total) + min_adj + min_tax - (invoice disc already in bases) == total
        $sumLineTotals = '0';
        foreach ($computedLines as $line) {
            $sumLineTotals = GhMoney::add($sumLineTotals, $line['line_total']);
        }
        $rebuilt = GhMoney::roundHalfUp(GhMoney::add(GhMoney::add($sumLineTotals, $minAdj), $minTax));
        if (GhMoney::cmp($rebuilt, $totalPayable) !== 0) {
            // Absorb 0.01 remainder into VAT if needed (should not happen with half-up allocation).
            $diff = GhMoney::sub($totalPayable, $rebuilt);
            if (GhMoney::cmp(GhMoney::of(ltrim($diff, '-')), '0.01') <= 0) {
                $vatAmount = GhMoney::roundHalfUp(GhMoney::add($vatAmount, $diff));
                $totalPayable = GhMoney::roundHalfUp(GhMoney::add($rebuilt, $diff));
            } else {
                throw ValidationException::withMessages([
                    'total' => [__('gh.errors.invoice_total_invariant')],
                ]);
            }
        }

        return [
            'lines' => $computedLines,
            'adjustments' => $adjustments,
            'lines_gross' => $linesGross,
            'item_discount_total' => $itemDiscountTotal,
            'invoice_discount_type' => $invoiceDiscountType,
            'invoice_discount_value' => $invoiceDiscountValue,
            'invoice_discount_amount' => $invoiceDiscountAmount,
            'lines_after_discounts' => $linesAfterDiscounts,
            'min_order' => $minResult,
            'taxable_base' => $taxableBase,
            'vat_amount' => $vatAmount,
            'total_payable' => $totalPayable,
            'standard_vat_rate' => $vatRate,
            'blocks_issuance' => $minResult['blocks_issuance'],
            'block_reasons' => $minResult['block_reasons'],
        ];
    }

    private function computeDiscount(string $base, string $type, string $value, string $errorKey): string
    {
        if ($type === GhDiscountType::NONE || GhMoney::cmp($value, '0') === 0) {
            return '0.00';
        }
        if ($type === GhDiscountType::PERCENT) {
            if (GhMoney::cmp($value, '0') < 0 || GhMoney::cmp($value, '100') > 0) {
                throw ValidationException::withMessages([
                    $errorKey => [__('gh.errors.invoice_percent_discount_invalid')],
                ]);
            }

            return GhMoney::roundHalfUp(GhMoney::div(GhMoney::mul($base, $value), '100'));
        }
        if ($type === GhDiscountType::FIXED) {
            if (GhMoney::cmp($value, '0') < 0) {
                throw ValidationException::withMessages([
                    $errorKey => [__('gh.errors.invoice_fixed_discount_invalid')],
                ]);
            }
            if (GhMoney::cmp($value, $base) > 0) {
                throw ValidationException::withMessages([
                    $errorKey => [__('gh.errors.invoice_fixed_discount_exceeds')],
                ]);
            }

            return GhMoney::roundHalfUp($value);
        }

        throw ValidationException::withMessages([
            $errorKey => [__('gh.errors.invoice_discount_type_invalid')],
        ]);
    }

    /**
     * Largest-remainder allocation so sum(parts) == total exactly at 2 dp.
     *
     * @param  list<string>  $weights
     * @return list<string>
     */
    private function allocateProportionally(array $weights, string $total): array
    {
        $n = count($weights);
        $out = array_fill(0, $n, '0.00');
        if ($n === 0 || GhMoney::cmp($total, '0') === 0) {
            return $out;
        }

        $sumWeights = '0';
        foreach ($weights as $w) {
            $sumWeights = GhMoney::add($sumWeights, $w);
        }
        if (GhMoney::cmp($sumWeights, '0') <= 0) {
            return $out;
        }

        $floors = [];
        $remainders = [];
        $allocated = '0';
        foreach ($weights as $i => $w) {
            $exact = GhMoney::div(GhMoney::mul($total, $w), $sumWeights, 8);
            // Truncate to 2dp (largest remainder method).
            $floor = bcdiv(bcmul($exact, '100', 8), '100', 2);
            $floors[$i] = GhMoney::of($floor);
            $remainders[$i] = (float) GhMoney::sub($exact, $floors[$i], 8);
            $allocated = GhMoney::add($allocated, $floors[$i]);
        }

        $leftCents = (int) round((float) GhMoney::mul(GhMoney::sub($total, GhMoney::of($allocated)), '100'));
        arsort($remainders);
        $keys = array_keys($remainders);
        for ($c = 0; $c < $leftCents; $c++) {
            $idx = $keys[$c % max(1, count($keys))];
            $floors[$idx] = GhMoney::of(GhMoney::add($floors[$idx], '0.01'));
        }

        return array_values($floors);
    }

    /**
     * @return array{
     *   original_minimum: ?string,
     *   effective_minimum: ?string,
     *   policy: string,
     *   override_type: string,
     *   adjustment_amount: string,
     *   tax_class: ?string,
     *   blocks_issuance: bool,
     *   block_reasons: list<string>,
     * }
     */
    private function resolveMinimumOrder(
        string $serviceAfterDiscounts,
        ?string $zoneMin,
        string $policy,
        string $override,
        ?string $overrideAmount,
        ?string $minTaxClass,
        string $vatRate,
    ): array {
        $blockReasons = [];
        $original = $zoneMin;
        $effective = $zoneMin;

        if ($override === GhMinOrderOverrideType::WAIVE) {
            $effective = '0.00';
        } elseif ($override === GhMinOrderOverrideType::OVERRIDE_AMOUNT) {
            if ($overrideAmount === null) {
                throw ValidationException::withMessages([
                    'min_order_override_amount' => [__('gh.errors.min_order_override_amount_required')],
                ]);
            }
            $effective = $overrideAmount;
        }

        $adjustment = '0.00';
        $surchargeEnabled = GhLaunchConfig::minOrderSurchargeEnabled();
        $effectivePolicy = $policy;

        // GH-9.2 launch: surcharge disabled → SURCHARGE zones enforce like VALIDATION_ONLY (no adjustment).
        if ($policy === GhMinOrderPolicy::SURCHARGE && ! $surchargeEnabled) {
            $effectivePolicy = GhMinOrderPolicy::VALIDATION_ONLY;
        }

        if ($effective !== null && GhMoney::cmp($effective, '0') > 0) {
            if ($effectivePolicy === GhMinOrderPolicy::VALIDATION_ONLY) {
                if (GhMoney::cmp($serviceAfterDiscounts, $effective) < 0) {
                    $blockReasons[] = 'min_order_validation';
                }
            } elseif ($effectivePolicy === GhMinOrderPolicy::SURCHARGE) {
                if (GhMoney::cmp($serviceAfterDiscounts, $effective) < 0) {
                    $adjustment = GhMoney::roundHalfUp(GhMoney::sub($effective, $serviceAfterDiscounts));
                }
            }
        }

        $taxClass = null;
        if (GhMoney::cmp($adjustment, '0') > 0) {
            // Fail-closed: never issue a surcharge without an approved tax class (C-GH-TAX-MIN).
            if ($minTaxClass === null || $minTaxClass === '' || ! in_array($minTaxClass, GhTaxClass::ALL, true)) {
                $blockReasons[] = 'min_order_tax_unresolved';
                $taxClass = null;
            } else {
                $taxClass = $minTaxClass;
            }
        }

        return [
            'original_minimum' => $original,
            'effective_minimum' => $effective,
            'policy' => $effectivePolicy,
            'override_type' => $override,
            'adjustment_amount' => $adjustment,
            'tax_class' => $taxClass,
            'blocks_issuance' => $blockReasons !== [],
            'block_reasons' => $blockReasons,
        ];
    }
}
