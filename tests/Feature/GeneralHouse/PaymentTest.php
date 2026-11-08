<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhPayment;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Services\GeneralHouse\Payments\GhPaymentService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhPaymentMethod;
use App\Support\GeneralHouse\GhPaymentStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentTest extends TestCase
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
        // GH-9.2 launch defaults (fail-closed online).
        config([
            'general_house.min_order_surcharge_tax_class' => null,
            'general_house.min_order.surcharge_enabled' => false,
            'general_house.standard_vat_rate' => '5.00',
            'general_house.payments.cash_enabled' => true,
            'general_house.payments.online_initiation_enabled' => false,
            'general_house.payments.online_gateway_confirm_enabled' => false,
            'general_house.payments.bank_transfer_enabled' => false,
            'general_house.payments.partial_payments_enabled' => true,
            'general_house.payments.overpayment_allowed' => false,
            'general_house.payments.driver_collection_enabled' => false,
            'general_house.payments.driver_delivery_payment_enabled' => false,
        ]);
    }

    public function test_issued_invoice_starts_unpaid_and_balance_ssot(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, unitPrice: '50.00', qty: 2); // 100 + 5% = 105

        $balance = app(GhPaymentBalanceService::class)->forInvoice($invoice);
        $this->assertSame(GhInvoicePaymentStatus::UNPAID, $balance['payment_status']);
        $this->assertSame('105.00', $balance['invoice_total']);
        $this->assertSame('105.00', $balance['remaining']);
        $this->assertSame('UNPAID', $invoice->fresh()->payment_status);
    }

    public function test_full_and_partial_cash_payments(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, unitPrice: '50.00', qty: 2);
        $svc = app(GhPaymentService::class);

        $p1 = $svc->recordConfirmedCash($invoice, $manager, [
            'amount' => '40.00',
            'idempotency_key' => 'cash-1',
        ]);
        $this->assertSame(GhPaymentStatus::CONFIRMED, $p1->status);
        $this->assertSame(GhPaymentMethod::CASH, $p1->method);
        $this->assertSame(GhInvoicePaymentStatus::PARTIAL, $invoice->fresh()->payment_status);

        $svc->recordConfirmedCash($invoice, $manager, [
            'amount' => '65.00',
            'idempotency_key' => 'cash-2',
        ]);
        $invoice->refresh();
        $this->assertSame(GhInvoicePaymentStatus::PAID, $invoice->payment_status);
        $balance = app(GhPaymentBalanceService::class)->forInvoice($invoice);
        $this->assertSame('0.00', $balance['remaining']);
        $this->assertSame('105.00', $balance['confirmed_total']);
    }

    public function test_overpayment_rejected_and_idempotent_retry(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, unitPrice: '50.00', qty: 2);
        $svc = app(GhPaymentService::class);

        $a = $svc->recordConfirmedCash($invoice, $manager, [
            'amount' => '20.00',
            'idempotency_key' => 'same-key',
        ]);
        $b = $svc->recordConfirmedCash($invoice, $manager, [
            'amount' => '20.00',
            'idempotency_key' => 'same-key',
        ]);
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, GhPayment::query()->where('invoice_id', $invoice->id)->count());

        $this->expectException(ValidationException::class);
        $svc->recordConfirmedCash($invoice, $manager, [
            'amount' => '999.00',
            'idempotency_key' => 'over',
        ]);
    }

    public function test_online_initiation_blocked_under_launch_config(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, unitPrice: '50.00', qty: 2);
        $svc = app(GhPaymentService::class);

        $this->expectException(ValidationException::class);
        $svc->initiateOnline($invoice, $manager, [
            'amount' => '105.00',
            'external_ref' => 'GW-1',
            'idempotency_key' => 'online-1',
        ]);
    }

    public function test_http_online_initiation_rejected_and_cannot_bypass(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, unitPrice: '50.00', qty: 2);

        // Policy deny (403) when online initiation is launch-disabled — before service runs.
        $this->actingAs($manager)
            ->post(route('gh.payments.online', $invoice), [
                'amount' => '10.00',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertForbidden();

        $this->assertSame(0, GhPayment::query()->where('invoice_id', $invoice->id)->where('method', GhPaymentMethod::ONLINE)->count());
    }

    public function test_legacy_pending_online_does_not_affect_balance_and_gateway_confirm_blocked(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, unitPrice: '50.00', qty: 2);

        $pending = GhPayment::query()->forceCreate([
            'invoice_id' => $invoice->id,
            'reference' => 'PAY-LEGACY-ONLINE',
            'method' => GhPaymentMethod::ONLINE,
            'status' => GhPaymentStatus::PENDING,
            'amount' => '105.00',
            'currency' => 'AED',
            'idempotency_key' => 'legacy-online-1',
            'recorded_by' => $manager->id,
            'occurred_at' => now('UTC'),
            'recorded_at' => now('UTC'),
        ]);

        $balance = app(GhPaymentBalanceService::class)->forInvoice($invoice->fresh());
        $this->assertSame('0.00', $balance['confirmed_total']);
        $this->assertSame('105.00', $balance['pending_total']);
        $this->assertSame(GhInvoicePaymentStatus::UNPAID, $balance['payment_status']);

        $this->expectException(ValidationException::class);
        app(GhPaymentService::class)->confirmOnlineGateway($pending, $manager);
    }

    public function test_driver_forbidden_and_bank_method_blocked(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $invoice = $this->issueInvoice($manager, unitPrice: '50.00', qty: 2);

        $this->actingAs($driver)
            ->post(route('gh.payments.cash', $invoice), [
                'amount' => '10.00',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertForbidden();

        $ref = new \ReflectionMethod(GhPaymentService::class, 'recordPayment');
        $ref->setAccessible(true);
        $this->expectException(ValidationException::class);
        $ref->invoke(app(GhPaymentService::class), $invoice, $manager, [
            'method' => GhPaymentMethod::BANK,
            'status' => GhPaymentStatus::PENDING,
            'amount' => '10.00',
            'confirm_immediately' => false,
            'idempotency_key' => 'bank-blocked',
        ]);
    }

    public function test_http_cash_collection_and_cancel_pending_online(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, unitPrice: '20.00', qty: 5); // 100 + 5 = 105

        $this->actingAs($manager)
            ->post(route('gh.payments.cash', $invoice), [
                'amount' => '50.00',
                'idempotency_key' => 'http-cash',
            ])
            ->assertRedirect(route('gh.invoices.show', $invoice));

        $this->assertSame(GhInvoicePaymentStatus::PARTIAL, $invoice->fresh()->payment_status);

        $pending = GhPayment::query()->forceCreate([
            'invoice_id' => $invoice->id,
            'reference' => 'PAY-PEND-CANCEL',
            'method' => GhPaymentMethod::ONLINE,
            'status' => GhPaymentStatus::PENDING,
            'amount' => '55.00',
            'currency' => 'AED',
            'idempotency_key' => 'http-online-legacy',
            'recorded_by' => $manager->id,
            'occurred_at' => now('UTC'),
            'recorded_at' => now('UTC'),
        ]);
        $this->actingAs($manager)
            ->post(route('gh.payments.cancel', $pending))
            ->assertRedirect();
        $this->assertSame(GhPaymentStatus::CANCELLED, $pending->fresh()->status);

        // Invoice line totals unchanged after payments.
        $this->assertSame('105.00', (string) $invoice->fresh()->total_payable);
        $this->assertSame(GhInvoiceStatus::ISSUED, $invoice->fresh()->status);
    }

    public function test_payment_does_not_complete_order_and_delivery_ok_while_unpaid(): void
    {
        $manager = User::factory()->manager()->create();
        $delivery = User::factory()->driver()->create();
        $invoice = $this->issueInvoice($manager, unitPrice: '50.00', qty: 2, deliveryDriverId: $delivery->id);
        $order = $invoice->order;

        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $this->assertSame(GhInvoicePaymentStatus::UNPAID, $invoice->fresh()->payment_status);

        $this->actingAs($delivery)->post(route('gh.orders.confirm-delivery', $order), [
            'notes' => 'Delivered unpaid OK',
            // Declarations disabled in this suite — payment fields not required.
        ])->assertRedirect();

        $this->assertSame(GhOrderStatus::COMPLETED, $order->fresh()->status);
        $this->assertSame(GhInvoicePaymentStatus::UNPAID, $invoice->fresh()->payment_status);
    }

    public function test_manager_payments_index_renders(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('gh.payments.index'))->assertOk();
    }

    private function issueInvoice(User $manager, string $unitPrice, int $qty, ?int $deliveryDriverId = null): GhInvoice
    {
        $tz = config('general_house.timezone', 'Asia/Dubai');
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
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
            'delivery_driver_id' => $deliveryDriverId,
            'created_by' => $manager->id,
        ]);

        $line = GhOrderLine::factory()->create([
            'order_id' => $order->id,
            'offering_id' => $offering->id,
            'quantity' => $qty,
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
            'quantity' => $qty,
            'discrepancy_type' => GhReceivedDiscrepancy::MATCH,
            'sort_order' => 0,
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
            'unit_price_estimate' => $unitPrice,
            'currency' => 'AED',
            'price_source' => 'STANDARD',
        ]);

        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order, $manager);

        return app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
    }
}
