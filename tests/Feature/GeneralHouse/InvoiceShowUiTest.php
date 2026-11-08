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
use App\Services\GeneralHouse\Payments\GhPaymentService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhPaymentMethod;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Laundry staff invoice show page: simple summary only (no finance controls).
 */
class InvoiceShowUiTest extends TestCase
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
            'general_house.standard_vat_rate' => '5.00',
            'general_house.payments.cash_enabled' => true,
            'general_house.payments.partial_payments_enabled' => true,
            'general_house.payments.online_initiation_enabled' => false,
            'general_house.payments.bank_transfer_enabled' => false,
            'general_house.payments.driver_collection_enabled' => false,
            'general_house.payments.driver_delivery_payment_enabled' => true,
        ]);
    }

    public function test_unpaid_issued_invoice_shows_delivery_hint_without_finance_controls(): void
    {
        $manager = User::factory()->manager()->create(['language_preference' => 'en']);
        $invoice = $this->issueInvoice($manager, '50.00', 2);

        $response = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee(__('gh.payment_status.UNPAID'), false);
        $response->assertSee(__('gh.invoices.payment_at_delivery_hint'), false);
        $response->assertSee(__('gh.invoices.print'), false);
        $response->assertSee(__('gh.invoices.view_order'), false);
        $response->assertDontSee(__('gh.payments.record_cash'), false);
        $response->assertDontSee(__('gh.credit_notes.create'), false);
        $response->assertDontSee(__('gh.refunds.create'), false);
    }

    public function test_cash_paid_invoice_shows_method_and_status(): void
    {
        $manager = User::factory()->manager()->create(['language_preference' => 'en']);
        $driver = User::factory()->driver()->create();
        $invoice = $this->issueInvoice($manager, '50.00', 2, $driver->id);

        app(GhPaymentService::class)->recordDriverDeliveryPayment($invoice, $driver, [
            'method' => GhPaymentMethod::CASH,
            'amount' => (string) $invoice->total_payable,
            'idempotency_key' => 'gh-driver-delivery-pay-'.$invoice->id,
        ]);

        $this->assertSame(GhInvoicePaymentStatus::PAID, $invoice->fresh()->payment_status);

        $response = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.invoices.show', $invoice->fresh()));

        $response->assertOk();
        $response->assertSee(__('gh.payment_status.PAID'), false);
        $response->assertSee(__('gh.payment_method.CASH'), false);
        $response->assertSee(__('gh.invoices.payment_date_label'), false);
        $response->assertDontSee(__('gh.invoices.payment_at_delivery_hint'), false);
        $response->assertDontSee(__('gh.payments.record_cash'), false);
    }

    public function test_online_paid_invoice_shows_online_method(): void
    {
        $manager = User::factory()->manager()->create(['language_preference' => 'en']);
        $driver = User::factory()->driver()->create();
        $invoice = $this->issueInvoice($manager, '40.00', 1, $driver->id);

        app(GhPaymentService::class)->recordDriverDeliveryPayment($invoice, $driver, [
            'method' => GhPaymentMethod::ONLINE,
            'amount' => (string) $invoice->total_payable,
            'idempotency_key' => 'gh-driver-delivery-pay-'.$invoice->id,
        ]);

        $response = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.invoices.show', $invoice->fresh()));

        $response->assertOk();
        $response->assertSee(__('gh.payment_status.PAID'), false);
        $response->assertSee(__('gh.payment_method.ONLINE'), false);
        $response->assertDontSee(__('gh.invoices.payment_at_delivery_hint'), false);
    }

    public function test_partial_payment_shows_status_remaining_and_method(): void
    {
        $manager = User::factory()->manager()->create(['language_preference' => 'en']);
        $invoice = $this->issueInvoice($manager, '50.00', 2);

        app(GhPaymentService::class)->recordConfirmedCash($invoice, $manager, [
            'amount' => '40.00',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.invoices.show', $invoice->fresh()));

        $response->assertOk();
        $response->assertSee(__('gh.payment_status.PARTIAL'), false);
        $response->assertSee(__('gh.payment_method.CASH'), false);
        $response->assertSee(__('gh.invoices.remaining_label'), false);
        $response->assertDontSee(__('gh.payments.record_cash'), false);
    }

    public function test_arabic_unpaid_hint_and_print_still_work(): void
    {
        $manager = User::factory()->manager()->create(['language_preference' => 'ar']);
        $invoice = $this->issueInvoice($manager, '30.00', 1);

        $this->actingAs($manager)
            ->withSession(['locale' => 'ar'])
            ->get(route('gh.invoices.show', $invoice).'?lang=ar')
            ->assertOk()
            ->assertSee('سيتم تحديد طريقة الدفع عند التسليم.', false)
            ->assertSee('غير مدفوعة', false);

        $this->actingAs($manager)
            ->get(route('gh.invoices.print', $invoice))
            ->assertOk()
            ->assertSee($invoice->fresh()->number, false);
    }

    private function issueInvoice(User $manager, string $unitPrice, int $qty, ?int $deliveryDriverId = null): GhInvoice
    {
        $tz = config('general_house.timezone', 'Asia/Dubai');
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
        ]);
        $customer = GhCustomer::factory()->create([
            'type' => GhCustomerType::B2C,
            'name_ar' => 'عميل تجريبي',
            'name_en' => 'Test Customer',
        ]);
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
