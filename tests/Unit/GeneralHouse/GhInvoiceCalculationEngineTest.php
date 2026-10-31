<?php

namespace Tests\Unit\GeneralHouse;

use App\Services\GeneralHouse\Invoices\GhInvoiceCalculationEngine;
use App\Support\GeneralHouse\GhDiscountType;
use App\Support\GeneralHouse\GhMinOrderOverrideType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GhInvoiceCalculationEngineTest extends TestCase
{
    use RefreshDatabase;

    private GhInvoiceCalculationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(GhInvoiceCalculationEngine::class);
        config(['general_house.standard_vat_rate' => '5.00']);
        config(['general_house.min_order_surcharge_tax_class' => null]);
    }

    public function test_standard_line_vat_and_total(): void
    {
        $r = $this->engine->calculate([
            $this->line(qty: 2, price: '10.00'),
        ], $this->header(min: '0'));

        $this->assertSame('20.00', $r['lines_gross']);
        $this->assertSame('20.00', $r['lines_after_discounts']);
        $this->assertSame('1.00', $r['vat_amount']);
        $this->assertSame('21.00', $r['total_payable']);
        $this->assertFalse($r['blocks_issuance']);
    }

    public function test_item_and_invoice_discounts_before_vat(): void
    {
        $r = $this->engine->calculate([
            $this->line(qty: 1, price: '100.00', discType: GhDiscountType::PERCENT, discValue: '10'),
        ], $this->header(
            min: '0',
            invDiscType: GhDiscountType::FIXED,
            invDiscValue: '5'
        ));

        $this->assertSame('10.00', $r['item_discount_total']);
        $this->assertSame('5.00', $r['invoice_discount_amount']);
        $this->assertSame('85.00', $r['lines_after_discounts']);
        $this->assertSame('4.25', $r['vat_amount']);
        $this->assertSame('89.25', $r['total_payable']);
    }

    public function test_proportional_invoice_discount_allocation_sums_exactly(): void
    {
        $r = $this->engine->calculate([
            $this->line(qty: 1, price: '10.00'),
            $this->line(qty: 1, price: '20.00'),
            $this->line(qty: 1, price: '30.00'),
        ], $this->header(min: '0', invDiscType: GhDiscountType::FIXED, invDiscValue: '10'));

        $alloc = array_map(fn ($l) => $l['allocated_invoice_discount'], $r['lines']);
        $sum = '0';
        foreach ($alloc as $a) {
            $sum = GhMoney::add($sum, $a);
        }
        $this->assertSame('10.00', GhMoney::roundHalfUp($sum));
        $this->assertSame($r['total_payable'], $this->rebuildTotal($r));
    }

    public function test_mixed_tax_classes(): void
    {
        $r = $this->engine->calculate([
            $this->line(qty: 1, price: '100.00', tax: GhTaxClass::STANDARD_RATED),
            $this->line(qty: 1, price: '50.00', tax: GhTaxClass::EXEMPT),
            $this->line(qty: 1, price: '25.00', tax: GhTaxClass::ZERO_RATED),
        ], $this->header(min: '0'));

        $this->assertSame('5.00', $r['vat_amount']);
        $this->assertSame('100.00', $r['taxable_base']);
        $this->assertSame('180.00', $r['total_payable']); // 175 + 5 VAT
    }

    public function test_min_order_surcharge_blocks_when_tax_unresolved(): void
    {
        $r = $this->engine->calculate([
            $this->line(qty: 1, price: '10.00'),
        ], $this->header(min: '30.00', policy: GhMinOrderPolicy::SURCHARGE));

        $this->assertSame('20.00', $r['min_order']['adjustment_amount']);
        $this->assertTrue($r['blocks_issuance']);
        $this->assertContains('min_order_tax_unresolved', $r['block_reasons']);
    }

    public function test_min_order_surcharge_with_approved_tax(): void
    {
        $r = $this->engine->calculate([
            $this->line(qty: 1, price: '10.00'),
        ], $this->header(
            min: '30.00',
            policy: GhMinOrderPolicy::SURCHARGE,
            minTax: GhTaxClass::STANDARD_RATED
        ));

        $this->assertSame('20.00', $r['min_order']['adjustment_amount']);
        $this->assertFalse($r['blocks_issuance']);
        // lines 10 + vat 0.50 + surcharge 20 + surcharge vat 1.00
        $this->assertSame('1.50', $r['vat_amount']);
        $this->assertSame('31.50', $r['total_payable']);
    }

    public function test_min_order_validation_only_blocks_below_minimum(): void
    {
        $r = $this->engine->calculate([
            $this->line(qty: 1, price: '10.00'),
        ], $this->header(min: '30.00', policy: GhMinOrderPolicy::VALIDATION_ONLY));

        $this->assertSame('0.00', $r['min_order']['adjustment_amount']);
        $this->assertTrue($r['blocks_issuance']);
        $this->assertContains('min_order_validation', $r['block_reasons']);
    }

    public function test_min_order_waiver(): void
    {
        $r = $this->engine->calculate([
            $this->line(qty: 1, price: '10.00'),
        ], $this->header(
            min: '30.00',
            policy: GhMinOrderPolicy::SURCHARGE,
            override: GhMinOrderOverrideType::WAIVE
        ));

        $this->assertSame('0.00', $r['min_order']['adjustment_amount']);
        $this->assertFalse($r['blocks_issuance']);
        $this->assertSame('10.50', $r['total_payable']);
    }

    public function test_fixed_discount_cannot_exceed_line(): void
    {
        $this->expectException(ValidationException::class);
        $this->engine->calculate([
            $this->line(qty: 1, price: '10.00', discType: GhDiscountType::FIXED, discValue: '11'),
        ], $this->header(min: '0'));
    }

    public function test_fractional_vat_rounding_half_up(): void
    {
        // 33.33 * 5% = 1.6665 → 1.67
        $r = $this->engine->calculate([
            $this->line(qty: 1, price: '33.33'),
        ], $this->header(min: '0'));

        $this->assertSame('1.67', $r['vat_amount']);
        $this->assertSame('35.00', $r['total_payable']);
        $this->assertSame($r['total_payable'], $this->rebuildTotal($r));
    }

    public function test_quantity_must_be_positive(): void
    {
        $this->expectException(ValidationException::class);
        $this->engine->calculate([
            $this->line(qty: 0, price: '10.00'),
        ], $this->header(min: '0'));
    }

    /**
     * @return array<string, mixed>
     */
    private function line(
        int $qty,
        string $price,
        string $tax = GhTaxClass::STANDARD_RATED,
        string $discType = GhDiscountType::NONE,
        string $discValue = '0',
    ): array {
        return [
            'offering_id' => 1,
            'quantity' => $qty,
            'unit_price' => $price,
            'tax_class' => $tax,
            'item_discount_type' => $discType,
            'item_discount_value' => $discValue,
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function header(
        string $min,
        string $policy = GhMinOrderPolicy::SURCHARGE,
        string $override = GhMinOrderOverrideType::APPLY_DEFAULT,
        ?string $minTax = null,
        string $invDiscType = GhDiscountType::NONE,
        string $invDiscValue = '0',
    ): array {
        return [
            'min_order_amount' => $min,
            'min_order_policy' => $policy,
            'min_order_override_type' => $override,
            'min_order_tax_class' => $minTax,
            'invoice_discount_type' => $invDiscType,
            'invoice_discount_value' => $invDiscValue,
            'standard_vat_rate' => '5.00',
        ];
    }

    /** @param  array<string, mixed>  $r */
    private function rebuildTotal(array $r): string
    {
        $sum = '0';
        foreach ($r['lines'] as $line) {
            $sum = GhMoney::add($sum, $line['line_total']);
        }
        $minAdj = $r['min_order']['adjustment_amount'];
        $minTax = '0';
        foreach ($r['adjustments'] as $adj) {
            if ($adj['adjustment_type'] === 'MIN_ORDER_SURCHARGE') {
                $minTax = $adj['tax_amount'];
            }
        }

        return GhMoney::roundHalfUp(GhMoney::add(GhMoney::add($sum, $minAdj), $minTax));
    }
}
