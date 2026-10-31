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
use App\Services\GeneralHouse\Reporting\GhCsvExportService;
use App\Services\GeneralHouse\Reporting\GhDashboardService;
use App\Services\GeneralHouse\Reporting\GhReportQueryService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhPaymentStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use App\Support\GeneralHouse\GhReportFilters;
use App\Support\GeneralHouse\GhReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardReportingTest extends TestCase
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
        config(['general_house.min_order_surcharge_tax_class' => null]);
    }

    public function test_manager_dashboard_and_driver_forbidden(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();

        $this->actingAs($manager)->get(route('gh.dashboard'))->assertOk()
            ->assertSee(__('gh.dashboard.title'), false);

        $this->actingAs($driver)->get(route('gh.dashboard'))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.reports.invoices'))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.reports.collections', ['export' => 1]))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.reports.outstanding'))->assertForbidden();
    }

    public function test_financial_kpis_exclude_pending_and_reconcile_balances(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'Asia/Dubai'));
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, '100.00', 2);
        $payments = app(GhPaymentService::class);
        $total = (string) $invoice->total_payable;

        $payments->recordConfirmedCash($invoice, $manager, [
            'amount' => '40.00',
            'occurred_at' => Carbon::now('UTC')->toDateTimeString(),
        ]);
        $payments->initiateOnline($invoice->fresh(), $manager, [
            'amount' => '20.00',
            'external_ref' => 'PEND-1',
        ]);

        $filters = GhReportFilters::fromValidated([
            'preset' => GhReportPeriod::PRESET_CUSTOM,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]);
        $dash = app(GhDashboardService::class)->build($filters);
        $balance = app(GhPaymentBalanceService::class)->forInvoice($invoice->fresh());

        $this->assertSame($total, $dash['financial']['issued_invoice_value']);
        $this->assertSame('40.00', $dash['financial']['confirmed_cash_collections']);
        $this->assertSame('40.00', $dash['financial']['total_confirmed_collections']);
        $this->assertSame($balance['remaining'], $dash['financial']['outstanding_receivables']);
        $this->assertSame(1, $dash['financial']['partially_paid_invoices']);
        $this->assertSame(0, $dash['financial']['unpaid_invoices']);
        $this->assertSame(GhInvoicePaymentStatus::PARTIAL, $balance['payment_status']);
        $this->assertSame('20.00', $balance['pending_total']);
        $this->assertTrue(
            (float) $balance['pending_total'] > 0
            && (float) $dash['financial']['total_confirmed_collections'] === 40.0
        );

        Carbon::setTestNow();
    }

    public function test_dubai_period_boundaries_and_operational_counts(): void
    {
        $tz = 'Asia/Dubai';
        // 2026-10-31 23:30 Dubai = 2026-10-31 19:30 UTC — still "today" Dubai.
        Carbon::setTestNow(Carbon::parse('2026-10-31 23:30:00', $tz));

        $manager = User::factory()->manager()->create();
        $todayOrder = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'created_by' => $manager->id]);
        // Force created_at into period via update (factory may set now).
        GhOrder::query()->whereKey($todayOrder->id)->update([
            'created_at' => Carbon::parse('2026-10-31 10:00:00', $tz)->utc(),
        ]);

        $yesterday = $this->makeOrder(['status' => GhOrderStatus::WORKING, 'created_by' => $manager->id]);
        GhOrder::query()->whereKey($yesterday->id)->update([
            'created_at' => Carbon::parse('2026-10-30 10:00:00', $tz)->utc(),
        ]);

        $filters = GhReportFilters::fromValidated(['preset' => GhReportPeriod::PRESET_TODAY]);
        $this->assertSame('2026-10-31', $filters->period->fromDate);
        $dash = app(GhDashboardService::class)->build($filters);

        $this->assertSame(1, $dash['operational']['total_orders']);
        $this->assertSame(1, $dash['operational']['awaiting_pickup']);
        $this->assertSame(1, $dash['operational']['working']);
        $this->assertNull($dash['operational']['overdue_deliveries']);

        Carbon::setTestNow();
    }

    public function test_csv_injection_protection_and_export_auth(): void
    {
        $csv = app(GhCsvExportService::class);
        $this->assertSame("'=1+1", $csv->sanitizeCell('=1+1'));
        $this->assertSame("'+cmd", $csv->sanitizeCell('+cmd'));
        $this->assertSame('safe', $csv->sanitizeCell('safe'));

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)
            ->get(route('gh.reports.invoices', ['preset' => 'last_30', 'export' => 1]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_arabic_dashboard_and_empty_dataset(): void
    {
        $manager = User::factory()->manager()->create();
        $this->withSession(['locale' => 'ar'])
            ->actingAs($manager)
            ->get(route('gh.dashboard', ['preset' => 'today']))
            ->assertOk()
            ->assertSee(__('gh.dashboard.title', [], 'ar'), false);
    }

    public function test_outstanding_report_and_query_service(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Dubai'));
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, '50.00', 1);
        app(GhPaymentService::class)->recordConfirmedCash($invoice, $manager, [
            'amount' => '10.00',
            'occurred_at' => Carbon::now('UTC')->toDateTimeString(),
        ]);

        $filters = GhReportFilters::fromValidated([
            'preset' => GhReportPeriod::PRESET_CUSTOM,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]);
        $svc = app(GhReportQueryService::class);
        $rows = $svc->outstandingForExport($filters);
        $this->assertCount(1, $rows);
        $balance = app(GhPaymentBalanceService::class)->forInvoice($invoice->fresh());
        $this->assertSame($balance['remaining'], $rows->first()['remaining']);
        $this->assertSame('10.00', $rows->first()['confirmed']);
        $this->assertSame($balance['confirmed_total'], $rows->first()['confirmed']);

        $this->actingAs($manager)->get(route('gh.reports.outstanding', [
            'preset' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31',
        ]))->assertOk();

        Carbon::setTestNow();
    }

    public function test_pending_payment_not_in_collections_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 12:00:00', 'Asia/Dubai'));
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, '80.00', 1);
        app(GhPaymentService::class)->initiateOnline($invoice, $manager, [
            'amount' => '80.00',
            'external_ref' => 'ONL-X',
        ]);
        $this->assertSame(GhPaymentStatus::PENDING, GhPayment::query()->first()->status);

        $filters = GhReportFilters::fromValidated([
            'preset' => GhReportPeriod::PRESET_CUSTOM,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]);
        $this->assertSame(0, app(GhReportQueryService::class)->collections($filters)->total());
        Carbon::setTestNow();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(array $overrides = []): GhOrder
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
        $manager = User::factory()->manager()->create();

        return GhOrder::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'pickup_starts_at' => Carbon::now($tz)->setTime(10, 0),
            'pickup_ends_at' => Carbon::now($tz)->setTime(12, 0),
            'created_by' => $manager->id,
        ], $overrides));
    }

    private function issueInvoice(User $manager, string $unitPrice, int $qty): GhInvoice
    {
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::now('UTC'),
            'created_by' => $manager->id,
        ]);
        $offering = GhProductOffering::factory()->create(['standard_price' => $unitPrice]);
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

        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(['zone', 'customer']), $manager);

        return app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
    }
}
