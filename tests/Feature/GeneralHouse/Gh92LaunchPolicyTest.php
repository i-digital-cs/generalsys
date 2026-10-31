<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Invoices\GhInvoiceCalculationEngine;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhMinOrderOverrideType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GH-9.2 owner-approved launch: cash/partial only; VALIDATION_ONLY; surcharge off.
 */
class Gh92LaunchPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['orders', 'invoices', 'payments'] as $seq) {
            if (! DB::table('gh_sequences')->where('name', $seq)->exists()) {
                DB::table('gh_sequences')->insert([
                    'name' => $seq,
                    'next_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        config([
            'general_house.min_order.surcharge_enabled' => false,
            'general_house.min_order.default_policy' => 'VALIDATION_ONLY',
            'general_house.min_order_surcharge_tax_class' => null,
            'general_house.payments.cash_enabled' => true,
            'general_house.payments.partial_payments_enabled' => true,
            'general_house.payments.online_initiation_enabled' => false,
            'general_house.payments.bank_transfer_enabled' => false,
            'general_house.payments.driver_collection_enabled' => false,
            'general_house.payments.overpayment_allowed' => false,
        ]);
    }

    public function test_launch_flags_fail_closed(): void
    {
        $this->assertTrue(GhLaunchConfig::cashEnabled());
        $this->assertTrue(GhLaunchConfig::partialPaymentsEnabled());
        $this->assertFalse(GhLaunchConfig::onlineInitiationEnabled());
        $this->assertFalse(GhLaunchConfig::bankTransferEnabled());
        $this->assertFalse(GhLaunchConfig::driverCollectionEnabled());
        $this->assertFalse(GhLaunchConfig::minOrderSurchargeEnabled());
        $this->assertSame(GhMinOrderPolicy::VALIDATION_ONLY, GhLaunchConfig::defaultMinOrderPolicy());
    }

    public function test_validation_only_above_exact_and_below_minimum(): void
    {
        $engine = app(GhInvoiceCalculationEngine::class);
        $lines = [[
            'quantity' => 1,
            'unit_price' => '30.00',
            'tax_class' => GhTaxClass::STANDARD_RATED,
            'item_discount_type' => 'NONE',
            'item_discount_value' => 0,
        ]];

        $above = $engine->calculate($lines, [
            'min_order_amount' => '25.00',
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
        ]);
        $this->assertFalse($above['min_order']['blocks_issuance']);
        $this->assertSame('0.00', $above['min_order']['adjustment_amount']);

        $exact = $engine->calculate($lines, [
            'min_order_amount' => '30.00',
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
        ]);
        $this->assertFalse($exact['min_order']['blocks_issuance']);
        $this->assertSame('0.00', $exact['min_order']['adjustment_amount']);

        $below = $engine->calculate([[
            'quantity' => 1,
            'unit_price' => '10.00',
            'tax_class' => GhTaxClass::STANDARD_RATED,
            'item_discount_type' => 'NONE',
            'item_discount_value' => 0,
        ]], [
            'min_order_amount' => '25.00',
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
        ]);
        $this->assertTrue($below['min_order']['blocks_issuance']);
        $this->assertContains('min_order_validation', $below['min_order']['block_reasons']);
        $this->assertSame('0.00', $below['min_order']['adjustment_amount']);
    }

    public function test_surcharge_policy_does_not_create_adjustment_when_disabled(): void
    {
        $calc = app(GhInvoiceCalculationEngine::class)->calculate([[
            'quantity' => 1,
            'unit_price' => '10.00',
            'tax_class' => GhTaxClass::STANDARD_RATED,
            'item_discount_type' => 'NONE',
            'item_discount_value' => 0,
        ]], [
            'min_order_amount' => '30.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
            'min_order_tax_class' => GhTaxClass::STANDARD_RATED,
        ]);

        $this->assertSame(GhMinOrderPolicy::VALIDATION_ONLY, $calc['min_order']['policy']);
        $this->assertSame('0.00', $calc['min_order']['adjustment_amount']);
        $this->assertTrue($calc['min_order']['blocks_issuance']);
        $this->assertContains('min_order_validation', $calc['min_order']['block_reasons']);
    }

    public function test_waive_allows_issue_below_minimum_without_surcharge(): void
    {
        $manager = User::factory()->manager()->create();
        $ctx = $this->workingOrder(unitPrice: '10.00', minAmount: '50.00');
        $invoice = app(GhInvoiceService::class)->createDraftFromOrder($ctx['order'], $manager, [
            'min_order_override_type' => GhMinOrderOverrideType::WAIVE,
            'min_order_reason' => 'owner waive test',
        ]);
        $issued = app(GhInvoiceIssuanceService::class)->issue($invoice, $manager, (int) $invoice->draft_version);
        $this->assertSame(GhInvoiceStatus::ISSUED, $issued->status);
        $this->assertSame('0.00', (string) $issued->min_order_adjustment_amount);
    }

    public function test_legacy_surcharge_adjustment_with_unresolved_tax_fail_closed_when_enabled(): void
    {
        config(['general_house.min_order.surcharge_enabled' => true]);
        $calc = app(GhInvoiceCalculationEngine::class)->calculate([[
            'quantity' => 1,
            'unit_price' => '10.00',
            'tax_class' => GhTaxClass::STANDARD_RATED,
            'item_discount_type' => 'NONE',
            'item_discount_value' => 0,
        ]], [
            'min_order_amount' => '30.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
            'min_order_tax_class' => null,
        ]);
        $this->assertTrue(GhMoney::cmp($calc['min_order']['adjustment_amount'], '0') > 0);
        $this->assertContains('min_order_tax_unresolved', $calc['min_order']['block_reasons']);
    }

    public function test_new_zone_defaults_to_validation_only(): void
    {
        $zone = GhZone::factory()->create();
        $this->assertSame(GhMinOrderPolicy::VALIDATION_ONLY, $zone->min_order_policy);
    }

    /**
     * @return array{order: GhOrder}
     */
    private function workingOrder(string $unitPrice, string $minAmount): array
    {
        $tz = config('general_house.timezone', 'Asia/Dubai');
        $zone = GhZone::factory()->create([
            'min_order_amount' => $minAmount,
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create([
            'standard_price' => $unitPrice,
            'tax_class' => GhTaxClass::STANDARD_RATED,
        ]);
        $starts = Carbon::now($tz)->subDay()->utc();
        $order = GhOrder::factory()->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'status' => GhOrderStatus::WORKING,
            'pickup_starts_at' => $starts,
            'pickup_ends_at' => $starts->copy()->addHour(),
            'timezone' => $tz,
            'picked_up_at' => $starts,
        ]);
        $line = GhOrderLine::factory()->create([
            'order_id' => $order->id,
            'offering_id' => $offering->id,
            'quantity' => 1,
            'unit_price_estimate' => $unitPrice,
        ]);
        GhOrderReceivedLine::query()->create([
            'order_id' => $order->id,
            'order_line_id' => $line->id,
            'offering_id' => $offering->id,
            'quantity' => 1,
            'discrepancy_type' => GhReceivedDiscrepancy::MATCH,
            'sort_order' => 0,
            'product_name_ar' => 'p',
            'product_name_en' => 'p',
            'treatment_name_ar' => 't',
            'treatment_name_en' => 't',
            'unit_price_estimate' => $unitPrice,
            'currency' => 'AED',
            'price_source' => 'STANDARD',
        ]);

        return ['order' => $order->fresh(['customer', 'zone', 'receivedLines', 'lines'])];
    }
}
