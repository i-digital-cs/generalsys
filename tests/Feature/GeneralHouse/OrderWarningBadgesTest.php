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
use App\Services\GeneralHouse\Orders\GhOrderBoardService;
use App\Services\GeneralHouse\Orders\GhOrderWarningService;
use App\Services\GeneralHouse\Payments\GhPaymentService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderBoardTab;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderWarningBadgesTest extends TestCase
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
            'general_house.standard_vat_rate' => '5.00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_missing_invoice_warning_for_admin_not_driver(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'pickup_driver_id' => $driver->id,
            'picked_up_at' => Carbon::parse('2026-11-15 10:00:00', $tz)->utc(),
            'pickup_starts_at' => Carbon::parse('2026-11-15 10:00:00', $tz)->utc(),
            'invoice_issued_at' => null,
        ]);

        $admin = app(GhOrderWarningService::class)->forOrder($order, GhOrderWarningService::AUDIENCE_ADMIN);
        $this->assertTrue(collect($admin)->contains('code', GhOrderWarningService::CODE_MISSING_INVOICE));

        $driverWarnings = app(GhOrderWarningService::class)->forOrder($order, GhOrderWarningService::AUDIENCE_DRIVER);
        $this->assertFalse(collect($driverWarnings)->contains('code', GhOrderWarningService::CODE_MISSING_INVOICE));

        $this->actingAs($manager)->get(route('gh.orders.show', $order))
            ->assertOk()
            ->assertSee(__('gh.warnings.missing_invoice'), false)
            ->assertSee('data-warning-code="missing_invoice"', false);
    }

    public function test_issued_invoice_clears_missing_invoice_warning(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager);
        $order = $invoice->order->fresh(['invoice']);

        $warnings = app(GhOrderWarningService::class)->forOrder($order, GhOrderWarningService::AUDIENCE_ADMIN);
        $this->assertFalse(collect($warnings)->contains('code', GhOrderWarningService::CODE_MISSING_INVOICE));
    }

    public function test_completed_unpaid_partial_and_paid(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, unitPrice: '50.00', qty: 2);
        $order = $invoice->order;
        $order->forceFill([
            'status' => GhOrderStatus::COMPLETED,
            'delivered_at' => now('UTC'),
        ])->save();

        $svc = app(GhOrderWarningService::class);
        $unpaid = $svc->forOrder($order->fresh(['invoice']), GhOrderWarningService::AUDIENCE_ADMIN);
        $this->assertTrue(collect($unpaid)->contains('code', GhOrderWarningService::CODE_COMPLETED_UNPAID));

        app(GhPaymentService::class)->recordConfirmedCash($invoice, $manager, [
            'amount' => '40.00',
            'idempotency_key' => 'warn-partial',
        ]);
        $partial = $svc->forOrder($order->fresh(['invoice']), GhOrderWarningService::AUDIENCE_ADMIN);
        $this->assertTrue(collect($partial)->contains('code', GhOrderWarningService::CODE_COMPLETED_PARTIAL));
        $this->assertFalse(collect($partial)->contains('code', GhOrderWarningService::CODE_COMPLETED_UNPAID));

        app(GhPaymentService::class)->recordConfirmedCash($invoice->fresh(), $manager, [
            'amount' => '65.00',
            'idempotency_key' => 'warn-paid',
        ]);
        $paid = $svc->forOrder($order->fresh(['invoice']), GhOrderWarningService::AUDIENCE_ADMIN);
        $this->assertFalse(collect($paid)->contains('code', GhOrderWarningService::CODE_COMPLETED_UNPAID));
        $this->assertFalse(collect($paid)->contains('code', GhOrderWarningService::CODE_COMPLETED_PARTIAL));
    }

    public function test_completed_without_invoice_is_unbilled_not_unpaid(): void
    {
        $order = $this->makeOrder([
            'status' => GhOrderStatus::COMPLETED,
            'delivered_at' => now('UTC'),
            'picked_up_at' => now('UTC')->subHour(),
            'invoice_issued_at' => null,
        ]);

        $warnings = app(GhOrderWarningService::class)->forOrder($order, GhOrderWarningService::AUDIENCE_ADMIN);
        $codes = collect($warnings)->pluck('code');
        $this->assertTrue($codes->contains(GhOrderWarningService::CODE_MISSING_INVOICE));
        $this->assertFalse($codes->contains(GhOrderWarningService::CODE_COMPLETED_UNPAID));
        $this->assertFalse($codes->contains(GhOrderWarningService::CODE_COMPLETED_PARTIAL));
    }

    public function test_overdue_pickup_and_future_not_flagged(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $svc = app(GhOrderWarningService::class);

        $overdue = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_starts_at' => Carbon::parse('2026-11-15 10:00:00', $tz)->utc(),
            'pickup_ends_at' => Carbon::parse('2026-11-15 11:00:00', $tz)->utc(),
            'picked_up_at' => null,
        ]);
        $this->assertTrue(collect($svc->forOrder($overdue))->contains('code', GhOrderWarningService::CODE_OVERDUE_PICKUP));

        $future = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_starts_at' => Carbon::parse('2026-11-15 14:00:00', $tz)->utc(),
            'pickup_ends_at' => Carbon::parse('2026-11-15 15:00:00', $tz)->utc(),
            'picked_up_at' => null,
        ]);
        $this->assertFalse(collect($svc->forOrder($future))->contains('code', GhOrderWarningService::CODE_OVERDUE_PICKUP));
    }

    public function test_overdue_delivery_requires_appointment(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $svc = app(GhOrderWarningService::class);

        $overdue = $this->makeOrder([
            'status' => GhOrderStatus::DELIVERY,
            'picked_up_at' => Carbon::parse('2026-11-15 08:00:00', $tz)->utc(),
            'scheduled_delivery_at' => Carbon::parse('2026-11-15 10:00:00', $tz)->utc(),
            'delivered_at' => null,
        ]);
        $this->assertTrue(collect($svc->forOrder($overdue))->contains('code', GhOrderWarningService::CODE_OVERDUE_DELIVERY));

        $noAppt = $this->makeOrder([
            'status' => GhOrderStatus::DELIVERY,
            'picked_up_at' => Carbon::parse('2026-11-15 08:00:00', $tz)->utc(),
            'scheduled_delivery_at' => null,
            'delivered_at' => null,
        ]);
        $this->assertFalse(collect($svc->forOrder($noAppt))->contains('code', GhOrderWarningService::CODE_OVERDUE_DELIVERY));

        $future = $this->makeOrder([
            'status' => GhOrderStatus::DELIVERY,
            'picked_up_at' => Carbon::parse('2026-11-15 08:00:00', $tz)->utc(),
            'scheduled_delivery_at' => Carbon::parse('2026-11-15 18:00:00', $tz)->utc(),
            'delivered_at' => null,
        ]);
        $this->assertFalse(collect($svc->forOrder($future))->contains('code', GhOrderWarningService::CODE_OVERDUE_DELIVERY));
    }

    public function test_cancelled_has_no_warnings(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $order = $this->makeOrder([
            'status' => GhOrderStatus::CANCELLED,
            'pickup_starts_at' => Carbon::parse('2026-11-15 08:00:00', $tz)->utc(),
            'scheduled_delivery_at' => Carbon::parse('2026-11-15 09:00:00', $tz)->utc(),
            'invoice_issued_at' => null,
        ]);

        $this->assertSame([], app(GhOrderWarningService::class)->forOrder($order));
    }

    public function test_multiple_warnings_and_board_card_payload(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_starts_at' => Carbon::parse('2026-11-15 09:00:00', $tz)->utc(),
            'scheduled_delivery_at' => Carbon::parse('2026-11-15 10:00:00', $tz)->utc(),
            'picked_up_at' => null,
            'invoice_issued_at' => null,
            'created_by' => $manager->id,
        ]);

        $warnings = app(GhOrderWarningService::class)->forOrder($order->fresh(['invoice']));
        $codes = collect($warnings)->pluck('code');
        $this->assertTrue($codes->contains(GhOrderWarningService::CODE_MISSING_INVOICE));
        $this->assertTrue($codes->contains(GhOrderWarningService::CODE_OVERDUE_PICKUP));
        $this->assertTrue($codes->contains(GhOrderWarningService::CODE_OVERDUE_DELIVERY));

        $board = app(GhOrderBoardService::class)->boardForAdmin([
            'tab' => GhOrderBoardTab::PICKUP,
            'page' => 1,
        ]);
        $card = collect($board['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $order->id);
        $this->assertNotNull($card);
        $this->assertNotEmpty($card['warnings']);
        $this->assertTrue($card['overdue']);

        $this->actingAs($manager)->get(route('gh.orders.index', ['tab' => 'pickup']))
            ->assertOk()
            ->assertSee(__('gh.warnings.missing_invoice'), false)
            ->assertSee(__('gh.warnings.overdue_pickup'), false);
    }

    public function test_driver_board_shows_operational_not_finance_warnings(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $driver->id,
            'pickup_starts_at' => Carbon::parse('2026-11-15 09:00:00', $tz)->utc(),
            'picked_up_at' => null,
            'invoice_issued_at' => null,
        ]);

        $board = app(GhOrderBoardService::class)->boardForDriver($driver, GhOrderBoardTab::PICKUP);
        $card = collect($board['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $order->id);
        $this->assertNotNull($card);
        $codes = collect($card['warnings'])->pluck('code');
        $this->assertTrue($codes->contains(GhOrderWarningService::CODE_OVERDUE_PICKUP));
        $this->assertFalse($codes->contains(GhOrderWarningService::CODE_MISSING_INVOICE));

        $this->actingAs($driver)->get(route('gh.driver.pickup', $order))
            ->assertOk()
            ->assertSee(__('gh.warnings.overdue_pickup'), false)
            ->assertDontSee(__('gh.warnings.missing_invoice'), false);
    }

    public function test_employee_forbidden_from_order_board(): void
    {
        $employee = User::factory()->employee()->create();
        $this->actingAs($employee)->get(route('gh.orders.index'))->assertForbidden();
    }

    public function test_arabic_warning_labels(): void
    {
        app()->setLocale('ar');
        $this->assertSame('يرجى إنشاء فاتورة', __('gh.warnings.missing_invoice'));
        $this->assertSame('مكتمل — غير مدفوع', __('gh.warnings.completed_unpaid'));
        $this->assertSame('متأخر عن الاستلام', __('gh.warnings.overdue_pickup'));
        $this->assertSame('متأخر عن التسليم', __('gh.warnings.overdue_delivery'));
        app()->setLocale('en');
    }

    private function makeOrder(array $overrides = []): GhOrder
    {
        $tz = config('general_house.timezone', 'Asia/Dubai');
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
        ]);
        $customer = GhCustomer::factory()->create([
            'type' => GhCustomerType::B2C,
            'status' => GhCatalogStatus::ACTIVE,
        ]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $manager = User::factory()->manager()->create();

        return GhOrder::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'status' => GhOrderStatus::PICKUP,
            'pickup_starts_at' => Carbon::now($tz)->setTime(10, 0)->utc(),
            'pickup_ends_at' => Carbon::now($tz)->setTime(12, 0)->utc(),
            'scheduled_delivery_at' => null,
            'created_by' => $manager->id,
        ], $overrides));
    }

    private function issueInvoice(User $manager, string $unitPrice = '50.00', int $qty = 2): GhInvoice
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
        $pickedUp = Carbon::now('UTC')->subHours(3);

        $order = GhOrder::factory()->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => $pickedUp,
            'scheduled_delivery_at' => $pickedUp,
            'pickup_starts_at' => Carbon::now($tz)->subDay()->setTime(10, 0)->utc(),
            'pickup_ends_at' => Carbon::now($tz)->subDay()->setTime(12, 0)->utc(),
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
