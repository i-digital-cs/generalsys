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
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhPaymentMethod;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * HTTP-level launch UAT for GH-9.2 (cash + VALIDATION_ONLY; online hidden/forbidden).
 */
class Gh92HttpLaunchUatTest extends TestCase
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
            'general_house.payments.cash_enabled' => true,
            'general_house.payments.partial_payments_enabled' => true,
            'general_house.payments.online_initiation_enabled' => false,
            'general_house.payments.bank_transfer_enabled' => false,
            'general_house.payments.driver_collection_enabled' => false,
        ]);
    }

    public function test_manager_cash_partial_then_full_and_online_absent(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, unitPrice: '50.00', qty: 2); // 105.00

        $show = $this->actingAs($manager)->get(route('gh.invoices.show', $invoice));
        $show->assertOk();
        $show->assertSee(__('gh.payments.record_cash'), false);
        $show->assertDontSee(__('gh.payments.initiate_online'), false);

        $this->actingAs($manager)->post(route('gh.payments.cash', $invoice), [
            'amount' => '40.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame(GhInvoicePaymentStatus::PARTIAL, $invoice->fresh()->payment_status);

        $this->actingAs($manager)->post(route('gh.payments.cash', $invoice), [
            'amount' => '65.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame(GhInvoicePaymentStatus::PAID, $invoice->fresh()->payment_status);
        $this->assertSame('105.00', (string) $invoice->fresh()->total_payable);

        $this->actingAs($manager)->post(route('gh.payments.online', $invoice), [
            'amount' => '1.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertForbidden();

        $this->assertSame(0, GhPayment::query()->where('method', GhPaymentMethod::ONLINE)->count());
    }

    public function test_invoice_show_arabic_and_english(): void
    {
        $manager = User::factory()->manager()->create(['language_preference' => 'ar']);
        $invoice = $this->issueInvoice($manager, unitPrice: '40.00', qty: 1);

        $this->actingAs($manager)
            ->withSession(['locale' => 'ar'])
            ->get(route('gh.invoices.show', $invoice).'?lang=ar')
            ->assertOk();

        $manager->update(['language_preference' => 'en']);
        $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.invoices.show', $invoice).'?lang=en')
            ->assertOk()
            ->assertDontSee(__('gh.payments.initiate_online'), false);
    }

    private function issueInvoice(User $manager, string $unitPrice, int $qty): GhInvoice
    {
        $tz = config('general_house.timezone', 'Asia/Dubai');
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
            'tax_class' => GhTaxClass::STANDARD_RATED,
        ]);
        $order = GhOrder::factory()->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'status' => GhOrderStatus::WORKING,
            'timezone' => $tz,
            'picked_up_at' => Carbon::now('UTC'),
            'created_by' => $manager->id,
        ]);
        $line = GhOrderLine::factory()->create([
            'order_id' => $order->id,
            'offering_id' => $offering->id,
            'quantity' => $qty,
            'unit_price_estimate' => $unitPrice,
        ]);
        GhOrderReceivedLine::query()->create([
            'order_id' => $order->id,
            'order_line_id' => $line->id,
            'offering_id' => $offering->id,
            'quantity' => $qty,
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

        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(), $manager);
        $issued = app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
        $this->assertSame(GhInvoiceStatus::ISSUED, $issued->status);

        return $issued;
    }
}
