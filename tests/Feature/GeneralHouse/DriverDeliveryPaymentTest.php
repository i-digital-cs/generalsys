<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhPayment;
use App\Models\GeneralHouse\GhPaymentEvent;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Services\GeneralHouse\Payments\GhPaymentService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhPaymentMethod;
use App\Support\GeneralHouse\GhPaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DriverDeliveryPaymentTest extends TestCase
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
            'general_house.min_order_surcharge_tax_class' => null,
            'general_house.min_order.surcharge_enabled' => false,
            'general_house.payments.cash_enabled' => true,
            'general_house.payments.online_initiation_enabled' => false,
            'general_house.payments.online_gateway_confirm_enabled' => false,
            'general_house.payments.bank_transfer_enabled' => false,
            'general_house.payments.partial_payments_enabled' => true,
            'general_house.payments.overpayment_allowed' => false,
            'general_house.payments.driver_collection_enabled' => false,
            'general_house.payments.driver_delivery_payment_enabled' => true,
            'general_house.standard_vat_rate' => '0.00',
        ]);
    }

    public function test_pickup_page_unchanged_no_payment_controls(): void
    {
        $driver = User::factory()->driver()->create();
        $pickup = $this->pickupOrder($driver);

        $this->actingAs($driver)
            ->get(route('gh.driver.pickup', $pickup))
            ->assertOk()
            ->assertSee(__('gh.driver.pickup_time'), false)
            ->assertSee(__('gh.driver.confirm_pickup_clothes'), false)
            ->assertDontSee(__('gh.driver.payment_method'), false)
            ->assertDontSee(__('gh.driver.confirm_delivery_and_pay'), false);
    }

    public function test_cash_confirm_completes_order_and_pays_invoice(): void
    {
        $driver = User::factory()->driver()->create();
        $ctx = $this->deliveryReadyOrder($driver, unitPrice: '100.00');

        $this->actingAs($driver)
            ->get(route('gh.driver.delivery', $ctx['order']))
            ->assertOk()
            ->assertSee(__('gh.driver.confirm_delivery_and_pay'), false)
            ->assertSee(__('gh.driver.method_CASH'), false)
            ->assertSee(__('gh.driver.method_ONLINE'), false)
            ->assertDontSee(__('gh.driver.cash_received'), false);

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-delivery', $ctx['order']), [
                'payment_method' => 'CASH',
                'notes' => 'handed over',
            ])
            ->assertRedirect();

        $this->assertSame(GhOrderStatus::COMPLETED, $ctx['order']->fresh()->status);
        $payment = GhPayment::query()->where('invoice_id', $ctx['invoice']->id)->sole();
        $this->assertSame(GhPaymentMethod::CASH, $payment->method);
        $this->assertSame(GhPaymentStatus::CONFIRMED, $payment->status);
        $this->assertSame($driver->id, $payment->collector_user_id);
        $this->assertSame($driver->id, $payment->confirmed_by);
        $this->assertSame('100.00', (string) $payment->amount);
        $this->assertSame(GhInvoicePaymentStatus::PAID, $ctx['invoice']->fresh()->payment_status);
        $this->assertSame('0.00', app(GhPaymentBalanceService::class)->forInvoice($ctx['invoice']->fresh())['remaining_due']);
    }

    public function test_online_confirm_is_operator_recorded_not_gateway(): void
    {
        $driver = User::factory()->driver()->create();
        $ctx = $this->deliveryReadyOrder($driver, unitPrice: '100.00');

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-delivery', $ctx['order']), [
                'payment_method' => 'ONLINE',
            ])
            ->assertRedirect();

        $payment = GhPayment::query()->where('invoice_id', $ctx['invoice']->id)->sole();
        $this->assertSame(GhPaymentMethod::ONLINE, $payment->method);
        $this->assertSame(GhPaymentStatus::CONFIRMED, $payment->status);
        $this->assertNull($payment->external_ref);
        $this->assertStringContainsString(
            __('gh.payments.online_operator_recorded_note'),
            (string) $payment->notes
        );

        $event = GhPaymentEvent::query()->where('payment_id', $payment->id)->where('event_type', 'CONFIRMED')->firstOrFail();
        $this->assertSame('OPERATOR_RECORDED', $event->payload['verification_source'] ?? null);
        $this->assertFalse((bool) ($event->payload['gateway_verified'] ?? true));
        $this->assertSame(GhInvoicePaymentStatus::PAID, $ctx['invoice']->fresh()->payment_status);
        $this->assertSame(GhOrderStatus::COMPLETED, $ctx['order']->fresh()->status);
    }

    public function test_retry_does_not_duplicate_payment(): void
    {
        $driver = User::factory()->driver()->create();
        $ctx = $this->deliveryReadyOrder($driver, unitPrice: '80.00');

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-delivery', $ctx['order']), ['payment_method' => 'CASH'])
            ->assertRedirect();

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-delivery', $ctx['order']), ['payment_method' => 'ONLINE'])
            ->assertRedirect();

        $this->assertSame(1, GhPayment::query()->where('invoice_id', $ctx['invoice']->id)->count());
        $this->assertSame(GhPaymentStatus::CONFIRMED, GhPayment::query()->where('invoice_id', $ctx['invoice']->id)->value('status'));
        $this->assertSame(GhOrderStatus::COMPLETED, $ctx['order']->fresh()->status);
    }

    public function test_partial_existing_payment_collects_only_remaining(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $ctx = $this->deliveryReadyOrder($driver, unitPrice: '100.00', manager: $manager);

        app(GhPaymentService::class)->recordConfirmedCash($ctx['invoice'], $manager, [
            'amount' => '40.00',
            'idempotency_key' => 'mgr-partial',
        ]);
        $this->assertSame(GhInvoicePaymentStatus::PARTIAL, $ctx['invoice']->fresh()->payment_status);

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-delivery', $ctx['order']), ['payment_method' => 'CASH'])
            ->assertRedirect();

        $driverPay = GhPayment::query()
            ->where('invoice_id', $ctx['invoice']->id)
            ->where('idempotency_key', 'gh-driver-delivery-pay-'.$ctx['invoice']->id)
            ->firstOrFail();
        $this->assertSame('60.00', (string) $driverPay->amount);
        $this->assertSame(GhInvoicePaymentStatus::PAID, $ctx['invoice']->fresh()->payment_status);
        $this->assertSame('0.00', app(GhPaymentBalanceService::class)->forInvoice($ctx['invoice']->fresh())['remaining_due']);
    }

    public function test_already_paid_skips_collection(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $ctx = $this->deliveryReadyOrder($driver, unitPrice: '50.00', manager: $manager);

        app(GhPaymentService::class)->recordConfirmedCash($ctx['invoice'], $manager, [
            'amount' => '50.00',
            'idempotency_key' => 'mgr-full',
        ]);

        $before = GhPayment::query()->where('invoice_id', $ctx['invoice']->id)->count();

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-delivery', $ctx['order']), [
                // payment_method not required when already paid
                'notes' => 'already paid',
            ])
            ->assertRedirect();

        $this->assertSame($before, GhPayment::query()->where('invoice_id', $ctx['invoice']->id)->count());
        $this->assertSame(GhOrderStatus::COMPLETED, $ctx['order']->fresh()->status);
        $this->assertSame(GhInvoicePaymentStatus::PAID, $ctx['invoice']->fresh()->payment_status);
    }

    public function test_driver_cannot_access_admin_payment_routes(): void
    {
        $driver = User::factory()->driver()->create();
        $ctx = $this->deliveryReadyOrder($driver, unitPrice: '30.00');

        $this->actingAs($driver)
            ->get(route('gh.payments.index'))
            ->assertForbidden();

        $this->actingAs($driver)
            ->post(route('gh.payments.cash', $ctx['invoice']), [
                'amount' => '30.00',
                'idempotency_key' => 'hack',
            ])
            ->assertForbidden();
    }

    private function pickupOrder(User $driver): GhOrder
    {
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);

        return GhOrder::factory()->create([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'pickup_starts_at' => now()->subHour(),
            'pickup_ends_at' => now()->addHour(),
            'created_by' => User::factory()->manager()->create()->id,
        ]);
    }

    /**
     * @return array{order: GhOrder, invoice: GhInvoice}
     */
    private function deliveryReadyOrder(User $driver, string $unitPrice, ?User $manager = null): array
    {
        $manager ??= User::factory()->manager()->create();
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create([
            'standard_price' => $unitPrice,
            'tax_class' => 'STANDARD_RATED',
        ]);
        $order = GhOrder::factory()->create([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => now()->subHour(),
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'created_by' => $manager->id,
        ]);

        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(['zone', 'customer']), $manager);
        $draft = app(GhInvoiceService::class)->updateDraft($draft, $manager, [
            'draft_version' => $draft->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'min_order_effective_amount' => '0.00',
            'min_order_reason' => 'driver pay test',
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
        ]);
        $invoice = app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
        $order->refresh();
        $this->assertSame(GhOrderStatus::DELIVERY, $order->status);

        return ['order' => $order, 'invoice' => $invoice];
    }
}
