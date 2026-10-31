<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhMinOrderOverrideType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['orders', 'invoices'] as $seq) {
            if (! DB::table('gh_sequences')->where('name', $seq)->exists()) {
                DB::table('gh_sequences')->insert([
                    'name' => $seq,
                    'next_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        config(['general_house.min_order_surcharge_tax_class' => null]);
    }

    public function test_draft_uses_received_quantity_not_requested(): void
    {
        $manager = User::factory()->manager()->create();
        $ctx = $this->workingOrderWithReceived(requested: 10, received: 12, unitPrice: '10.00');

        $this->actingAs($manager)
            ->post(route('gh.invoices.create-from-order', $ctx['order']))
            ->assertRedirect();

        $invoice = GhInvoice::query()->where('order_id', $ctx['order']->id)->firstOrFail();
        $this->assertSame(GhInvoiceStatus::DRAFT, $invoice->status);
        $this->assertSame(12, (int) $invoice->lines()->first()->quantity);
        $this->assertSame(12, (int) $invoice->lines()->first()->quantity_received);
        $this->assertSame(10, (int) $ctx['line']->fresh()->quantity);
        $this->assertSame(12, (int) GhOrderReceivedLine::query()->where('order_id', $ctx['order']->id)->value('quantity'));
    }

    public function test_driver_cannot_create_or_issue_invoice(): void
    {
        $driver = User::factory()->driver()->create();
        $ctx = $this->workingOrderWithReceived(requested: 5, received: 5, unitPrice: '20.00');

        $this->actingAs($driver)
            ->post(route('gh.invoices.create-from-order', $ctx['order']))
            ->assertForbidden();

        $invoice = app(GhInvoiceService::class)->createDraftFromOrder($ctx['order'], User::factory()->manager()->create(), [
            'min_order_override_type' => GhMinOrderOverrideType::WAIVE,
            'min_order_reason' => 'test waive',
        ]);

        $this->actingAs($driver)
            ->post(route('gh.invoices.issue', $invoice), ['draft_version' => $invoice->draft_version])
            ->assertForbidden();
    }

    public function test_issue_moves_working_to_delivery_unpaid_and_immutable(): void
    {
        $manager = User::factory()->manager()->create();
        $ctx = $this->workingOrderWithReceived(requested: 5, received: 5, unitPrice: '20.00', minAmount: '0');
        $svc = app(GhInvoiceService::class);
        $invoice = $svc->createDraftFromOrder($ctx['order'], $manager);

        $this->actingAs($manager)
            ->post(route('gh.invoices.issue', $invoice), ['draft_version' => $invoice->draft_version])
            ->assertRedirect();

        $invoice->refresh();
        $order = $ctx['order']->fresh();
        $this->assertSame(GhInvoiceStatus::ISSUED, $invoice->status);
        $this->assertSame('UNPAID', $invoice->payment_status);
        $this->assertNotNull($invoice->number);
        $this->assertStringStartsWith('GH-INV-', $invoice->number);
        $this->assertSame(GhOrderStatus::DELIVERY, $order->status);
        $this->assertNotNull($order->invoice_issued_at);
        $this->assertSame(
            $invoice->issued_at->utc()->format('Y-m-d H:i:s'),
            $order->invoice_issued_at->utc()->format('Y-m-d H:i:s')
        );

        $this->actingAs($manager)
            ->put(route('gh.invoices.update', $invoice), [
                'draft_version' => $invoice->draft_version,
                'invoice_discount_type' => 'NONE',
                'invoice_discount_value' => 0,
                'min_order_override_type' => GhMinOrderOverrideType::APPLY_DEFAULT,
                'lines' => [[
                    'offering_id' => $invoice->lines()->first()->offering_id,
                    'quantity' => 99,
                ]],
            ])
            ->assertForbidden();
    }

    public function test_duplicate_primary_invoice_blocked_and_issue_idempotent(): void
    {
        $manager = User::factory()->manager()->create();
        $ctx = $this->workingOrderWithReceived(requested: 3, received: 3, unitPrice: '50.00', minAmount: '0');
        $svc = app(GhInvoiceService::class);
        $invoice = $svc->createDraftFromOrder($ctx['order'], $manager);

        $this->expectException(ValidationException::class);
        $svc->createDraftFromOrder($ctx['order'], $manager);
    }

    public function test_issue_idempotent_and_unique_number(): void
    {
        $manager = User::factory()->manager()->create();
        $ctx = $this->workingOrderWithReceived(requested: 3, received: 3, unitPrice: '50.00', minAmount: '0');
        $invoice = app(GhInvoiceService::class)->createDraftFromOrder($ctx['order'], $manager);
        $issuance = app(GhInvoiceIssuanceService::class);

        $a = $issuance->issue($invoice, $manager, (int) $invoice->draft_version);
        $b = $issuance->issue($invoice, $manager, (int) $invoice->draft_version);
        $this->assertSame($a->id, $b->id);
        $this->assertSame($a->number, $b->number);
        $this->assertSame(1, GhInvoice::query()->where('order_id', $ctx['order']->id)->count());
    }

    public function test_surcharge_without_tax_class_blocks_issue(): void
    {
        config(['general_house.min_order.surcharge_enabled' => true]);
        $manager = User::factory()->manager()->create();
        $ctx = $this->workingOrderWithReceived(requested: 1, received: 1, unitPrice: '10.00', minAmount: '30.00', policy: GhMinOrderPolicy::SURCHARGE);
        $invoice = app(GhInvoiceService::class)->createDraftFromOrder($ctx['order'], $manager);

        $this->expectException(ValidationException::class);
        app(GhInvoiceIssuanceService::class)->issue($invoice, $manager, (int) $invoice->draft_version);
    }

    public function test_surcharge_with_tax_class_allows_issue(): void
    {
        config(['general_house.min_order.surcharge_enabled' => true]);
        $manager = User::factory()->manager()->create();
        $ctx = $this->workingOrderWithReceived(requested: 1, received: 1, unitPrice: '10.00', minAmount: '30.00', policy: GhMinOrderPolicy::SURCHARGE);
        $invoice = app(GhInvoiceService::class)->createDraftFromOrder($ctx['order'], $manager, [
            'min_order_tax_class' => GhTaxClass::STANDARD_RATED,
        ]);

        // Recalc with tax class via update
        $invoice = app(GhInvoiceService::class)->updateDraft($invoice, $manager, [
            'draft_version' => $invoice->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => 0,
            'min_order_override_type' => GhMinOrderOverrideType::APPLY_DEFAULT,
            'min_order_tax_class' => GhTaxClass::STANDARD_RATED,
            'lines' => [[
                'offering_id' => $invoice->lines()->first()->offering_id,
                'received_line_id' => $invoice->lines()->first()->received_line_id,
                'quantity' => 1,
                'quantity_received' => 1,
                'item_discount_type' => 'NONE',
                'item_discount_value' => 0,
            ]],
        ]);

        $issued = app(GhInvoiceIssuanceService::class)->issue($invoice, $manager, (int) $invoice->draft_version);
        $this->assertSame(GhInvoiceStatus::ISSUED, $issued->status);
        $this->assertSame('UNPAID', $issued->payment_status);
        $this->assertTrue((float) $issued->min_order_adjustment_amount > 0);
        $this->assertSame(GhOrderStatus::DELIVERY, $ctx['order']->fresh()->status);
    }

    public function test_stale_draft_version_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $ctx = $this->workingOrderWithReceived(requested: 2, received: 2, unitPrice: '40.00', minAmount: '0');
        $invoice = app(GhInvoiceService::class)->createDraftFromOrder($ctx['order'], $manager);

        $this->expectException(ValidationException::class);
        app(GhInvoiceService::class)->updateDraft($invoice, $manager, [
            'draft_version' => 999,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => 0,
            'min_order_override_type' => GhMinOrderOverrideType::APPLY_DEFAULT,
            'lines' => [[
                'offering_id' => $invoice->lines()->first()->offering_id,
                'quantity' => 2,
            ]],
        ]);
    }

    public function test_manager_can_open_builder(): void
    {
        $manager = User::factory()->manager()->create();
        $ctx = $this->workingOrderWithReceived(requested: 2, received: 2, unitPrice: '40.00', minAmount: '0');
        $invoice = app(GhInvoiceService::class)->createDraftFromOrder($ctx['order'], $manager);

        $this->actingAs($manager)
            ->get(route('gh.invoices.builder', $invoice))
            ->assertOk()
            ->assertSee(__('gh.invoices.builder'), false);
    }

    /**
     * @return array{order: GhOrder, line: GhOrderLine, offering: GhProductOffering}
     */
    private function workingOrderWithReceived(
        int $requested,
        int $received,
        string $unitPrice,
        string $minAmount = '0.00',
        string $policy = GhMinOrderPolicy::VALIDATION_ONLY,
    ): array {
        $tz = config('general_house.timezone', 'Asia/Dubai');
        $zone = GhZone::factory()->create([
            'min_order_amount' => $minAmount,
            'min_order_policy' => $policy,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $manager = User::factory()->manager()->create();
        $offering = GhProductOffering::factory()->create(['standard_price' => $unitPrice]);

        $order = GhOrder::factory()->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::now('UTC'),
            'pickup_starts_at' => Carbon::now($tz)->subDay()->setTime(10, 0),
            'pickup_ends_at' => Carbon::now($tz)->subDay()->setTime(12, 0),
            'created_by' => $manager->id,
        ]);

        $line = GhOrderLine::factory()->create([
            'order_id' => $order->id,
            'offering_id' => $offering->id,
            'quantity' => $requested,
            'unit_price_estimate' => $unitPrice,
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
        ]);

        GhOrderReceivedLine::query()->create([
            'order_id' => $order->id,
            'order_line_id' => $line->id,
            'offering_id' => $offering->id,
            'quantity' => $received,
            'discrepancy_type' => $received === $requested
                ? GhReceivedDiscrepancy::MATCH
                : ($received > $requested ? GhReceivedDiscrepancy::OVER : GhReceivedDiscrepancy::SHORT),
            'sort_order' => 0,
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
            'unit_price_estimate' => $unitPrice,
            'currency' => 'AED',
            'price_source' => 'STANDARD',
        ]);

        return ['order' => $order, 'line' => $line, 'offering' => $offering];
    }
}
