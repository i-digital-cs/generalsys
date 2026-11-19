<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhDriverCashBalance;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\User;
use App\Services\GeneralHouse\Corrections\GhCreditNoteService;
use App\Services\GeneralHouse\Invoices\GhInvoiceListService;
use App\Services\GeneralHouse\Payments\GhCashSettlementQueryService;
use App\Services\GeneralHouse\Payments\GhDriverCashActivityService;
use App\Services\GeneralHouse\Payments\GhDriverCashDashboardService;
use App\Services\GeneralHouse\Payments\GhDriverCashSettlementService;
use App\Support\GeneralHouse\GhCashSettlementStatus;
use App\Support\GeneralHouse\GhCreditNoteReason;
use App\Support\GeneralHouse\GhReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\GeneralHouse\Concerns\GhDriverCashFixtures;
use Tests\TestCase;

class DriverCashDashboardServiceTest extends TestCase
{
    use GhDriverCashFixtures;
    use RefreshDatabase;

    private User $manager;

    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDriverCash();
        $this->manager = User::factory()->manager()->create();
        $this->driver = User::factory()->driver()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_summary_totals_counts_and_reconciliation_match_ledger(): void
    {
        $a = $this->collectCash($this->driver, '100.00');
        $b = $this->collectCash($this->driver, '50.00');
        $this->collectCash($this->driver, '30.00');
        $settle = app(GhDriverCashSettlementService::class);
        $settle->settle($this->driver, $this->manager, $this->settlePayload('70.00', [$a->id => '20.00', $b->id => '50.00']));
        $s2 = $settle->settle($this->driver, $this->manager, $this->settlePayload('10.00', [$a->id => '10.00']));
        $settle->reverse($s2, $this->manager, ['reason' => 'wrong driver envelope', 'idempotency_key' => (string) Str::uuid()]);

        $summary = app(GhDriverCashDashboardService::class)->summary($this->driver);

        $this->assertSame('180.00', $summary['collected']);
        $this->assertSame('70.00', $summary['settled']);
        $this->assertSame('10.00', $summary['reversed']);
        $this->assertSame('110.00', $summary['outstanding']);
        $this->assertSame('110.00', $summary['balance_outstanding']);
        $this->assertSame('0.00', $summary['unallocated']);
        $this->assertTrue($summary['reconciled']);
        $this->assertSame(['not_settled' => 1, 'partial' => 1, 'settled' => 1], $summary['counts']);
        $this->assertNotNull($summary['last_collected_at']);

        $period = GhReportPeriod::fromInput(['preset' => GhReportPeriod::PRESET_LAST_30]);
        $ledger = app(GhDriverCashActivityService::class)->ledger($this->driver, $period);
        $this->assertSame('0.00', $ledger['opening']);
        $this->assertSame('110.00', $ledger['closing']);
        $this->assertSame(6, $ledger['entries']->total());
        $this->assertSame('110.00', $ledger['entries']->getCollection()->last()->balance_after);
    }

    public function test_tampered_balance_is_reported_as_mismatch(): void
    {
        $this->collectCash($this->driver, '100.00');
        GhDriverCashBalance::query()->whereKey($this->driver->id)->update(['outstanding' => '90.00']);

        $summary = app(GhDriverCashDashboardService::class)->summary($this->driver);

        $this->assertFalse($summary['reconciled']);
        $this->assertSame('100.00', $summary['outstanding']);
        $this->assertSame('90.00', $summary['balance_outstanding']);
    }

    public function test_as_of_balance_and_period_activity_are_distinct(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00', 'UTC'));
        $a = $this->collectCash($this->driver, '100.00');
        Carbon::setTestNow(Carbon::parse('2026-11-15 21:00:00', 'UTC'));
        app(GhDriverCashSettlementService::class)->settle($this->driver, $this->manager, $this->settlePayload('60.00', [$a->id => '60.00']));
        Carbon::setTestNow(Carbon::parse('2026-11-18 08:00:00', 'UTC'));

        $activity = app(GhDriverCashActivityService::class);
        $this->assertSame('100.00', $activity->outstandingAsOf($this->driver, '2026-11-15'));
        $this->assertSame('40.00', $activity->outstandingAsOf($this->driver, '2026-11-16'), '21:00 UTC is 01:00 Dubai next day');

        $period = GhReportPeriod::fromInput(['preset' => GhReportPeriod::PRESET_CUSTOM, 'from' => '2026-11-16', 'to' => '2026-11-18']);
        $inPeriod = app(GhDriverCashDashboardService::class)->periodActivity($this->driver, $period);
        $this->assertSame(['collected' => '0.00', 'settled' => '60.00', 'reversed' => '0.00'], $inPeriod);

        $ledger = $activity->ledger($this->driver, $period);
        $this->assertSame('100.00', $ledger['opening']);
        $this->assertSame('40.00', $ledger['closing']);
    }

    public function test_ledger_running_balance_continues_across_pages(): void
    {
        foreach (range(1, 3) as $i) {
            $this->collectCash($this->driver, '10.00');
        }
        $period = GhReportPeriod::fromInput(['preset' => GhReportPeriod::PRESET_LAST_7]);
        $this->app['request']->query->set('ledger_page', 2);

        $ledger = app(GhDriverCashActivityService::class)->ledger($this->driver, $period, perPage: 2);

        $this->assertSame(2, $ledger['entries']->currentPage());
        $this->assertSame('30.00', $ledger['entries']->getCollection()->first()->balance_after);
    }

    public function test_invoice_list_columns_and_settlement_status_filters(): void
    {
        $partial = $this->collectCash($this->driver, '100.00');
        $settled = $this->collectCash($this->driver, '40.00');
        $untouched = $this->collectCash($this->driver, '25.00');
        app(GhDriverCashSettlementService::class)->settle($this->driver, $this->manager, $this->settlePayload('70.00', [$partial->id => '30.00', $settled->id => '40.00']));

        $direct = $this->invoicedDeliveryOrder(User::factory()->driver()->create(), '55.00');
        $this->actingAs($this->manager)->post(route('gh.payments.cash', $this->invoiceFor($direct)), [
            'amount' => '55.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $list = app(GhInvoiceListService::class);
        $ids = fn (array $f) => $list->query($f)->pluck('gh_invoices.order_id')->sort()->values()->all();

        $this->assertSame([$untouched->order_id], $ids(['settlement_status' => GhCashSettlementStatus::NOT_SETTLED]));
        $this->assertSame([$partial->order_id], $ids(['settlement_status' => GhCashSettlementStatus::PARTIAL]));
        $this->assertSame([$settled->order_id], $ids(['settlement_status' => GhCashSettlementStatus::SETTLED]));
        $this->assertSame([$direct->id], $ids(['settlement_status' => GhCashSettlementStatus::NOT_APPLICABLE]));
        $this->assertCount(3, $ids(['driver_id' => $this->driver->id]));

        $row = $list->query([])->where('gh_invoices.order_id', $partial->order_id)->sole();
        $this->assertSame('100.00', number_format((float) $row->driver_cash_collected, 2, '.', ''));
        $this->assertSame('30.00', number_format((float) $row->driver_cash_settled, 2, '.', ''));
        $this->assertSame('100.00', number_format((float) $row->payments_sum_amount, 2, '.', ''));
        $this->assertSame(['CASH'], $row->payments->pluck('method')->all());
    }

    public function test_invoice_list_query_count_is_bounded(): void
    {
        foreach (range(1, 4) as $i) {
            $this->collectCash($this->driver, '10.00');
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $page = app(GhInvoiceListService::class)->paginate([]);
        $page->each(fn ($row) => $row->order?->reference);
        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));
        $this->assertSame(4, GhDriverCashCollection::query()->count());
    }

    public function test_issued_credit_note_is_listed_but_does_not_change_custody(): void
    {
        DB::table('gh_sequences')->insertOrIgnore(['name' => 'credit_notes', 'next_value' => 1, 'created_at' => now(), 'updated_at' => now()]);
        config(['general_house.corrections.credit_notes_enabled' => true]);
        $collection = $this->collectCash($this->driver, '100.00');
        $invoice = $this->invoiceFor($collection->order)->fresh(['lines']);
        $before = $this->app->make(GhDriverCashDashboardService::class)->summary($this->driver);

        $notes = app(GhCreditNoteService::class);
        $note = $notes->createDraft($invoice, $this->manager, [
            'reason_code' => GhCreditNoteReason::QUANTITY_ERROR,
            'reason_text' => 'Customer returned one item',
            'idempotency_key' => 'dc-cn-1',
            'lines' => [['invoice_line_id' => (int) $invoice->lines->first()->id, 'quantity_credited' => 1]],
        ]);
        $note = $notes->submitForApproval($note, $this->manager);
        $note = $notes->approve($note, User::factory()->manager()->create());
        $notes->issue($note, $this->manager);

        $after = $this->app->make(GhDriverCashDashboardService::class)->summary($this->driver);
        $this->assertSame($before['outstanding'], $after['outstanding']);
        $this->assertSame('100.00', $after['outstanding']);
        $this->assertTrue($after['reconciled']);
        $this->assertSame(GhCashSettlementStatus::NOT_SETTLED, app(GhCashSettlementQueryService::class)
            ->annotateCollections(GhDriverCashCollection::query()->get())->first()->settlement_status);
        $adjustments = app(GhDriverCashActivityService::class)
            ->adjustments($this->driver, GhReportPeriod::fromInput(['preset' => GhReportPeriod::PRESET_LAST_30]));
        $this->assertCount(1, $adjustments['credit_notes']);
    }
}
