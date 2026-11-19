<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhDriverCashBalance;
use App\Models\GeneralHouse\GhDriverCashEvent;
use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\GeneralHouse\GhOrder;
use App\Models\User;
use App\Services\GeneralHouse\Invoices\GhInvoiceListService;
use App\Services\GeneralHouse\Payments\GhDriverCashActivityService;
use App\Services\GeneralHouse\Payments\GhDriverCashDashboardService;
use App\Services\GeneralHouse\Payments\GhDriverCashSettlementService;
use App\Services\GeneralHouse\Payments\GhOrderFinancialSummaryService;
use App\Services\GeneralHouse\Reporting\GhDriverCashReportService;
use App\Support\GeneralHouse\GhCashSettlementStatus;
use App\Support\GeneralHouse\GhReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\GeneralHouse\Concerns\GhDriverCashFixtures;
use Tests\TestCase;

/** One scenario, every surface: balance table, dashboard, order/invoice panels, invoice list, report, ledger, audit. */
class DriverCashReconciliationTest extends TestCase
{
    use GhDriverCashFixtures;
    use RefreshDatabase;

    public function test_all_surfaces_agree_after_settle_reverse_and_legacy_allocation(): void
    {
        $this->setUpDriverCash();
        $svc = app(GhDriverCashSettlementService::class);
        $m1 = User::factory()->manager()->create();
        $m2 = User::factory()->manager()->create();
        $a = User::factory()->driver()->create();
        $b = User::factory()->driver()->create();

        $c1 = $this->collectCash($a, '120.00');
        $c2 = $this->collectCash($a, '250.50');
        $c3 = $this->collectCash($a, '80.00');
        $c4 = $this->collectCash($b, '60.00');
        $direct = $this->invoicedDeliveryOrder($a, '45.00');
        $legacy = $this->legacySettlement($a, $m1, '50.00');

        $svc->settle($a, $m1, $this->settlePayload('150.00', [$c1->id => '120.00', $c2->id => '30.00']));
        $s2 = $svc->settle($a, $m1, $this->settlePayload('100.00', [$c2->id => '100.00']));
        $svc->reverse($s2, $m1, ['reason' => 'Wrong envelope', 'idempotency_key' => 'rec-rev']);
        $svc->settle($a, $m2, $this->settlePayload('80.00', [$c3->id => '80.00']));
        $svc->allocateUnallocated($legacy, $m1, ['allocations' => [['collection_id' => $c2->id, 'amount' => '50.00']], 'idempotency_key' => 'rec-legacy']);

        $expected = [
            $c1->order_id => ['120.00', '120.00', '0.00', GhCashSettlementStatus::SETTLED],
            $c2->order_id => ['250.50', '80.00', '170.50', GhCashSettlementStatus::PARTIAL],
            $c3->order_id => ['80.00', '80.00', '0.00', GhCashSettlementStatus::SETTLED],
            $c4->order_id => ['60.00', '0.00', '60.00', GhCashSettlementStatus::NOT_SETTLED],
        ];

        $this->assertSame('170.50', (string) GhDriverCashBalance::query()->findOrFail($a->id)->outstanding);
        $this->assertSame('60.00', (string) GhDriverCashBalance::query()->findOrFail($b->id)->outstanding);
        $this->assertSame(0, GhDriverCashBalance::query()->where('outstanding', '<', 0)->count());

        $summary = app(GhDriverCashDashboardService::class)->summary($a);
        $this->assertTrue($summary['reconciled']);
        $this->assertSame(['450.50', '280.00', '100.00', '170.50', '280.00', '0.00', '170.50'], [
            $summary['collected'], $summary['settled'], $summary['reversed'], $summary['outstanding'],
            $summary['allocated'], $summary['unallocated'], $summary['collections_remaining'],
        ]);

        $financial = app(GhOrderFinancialSummaryService::class);
        $rows = app(GhInvoiceListService::class)->paginate([])->getCollection()->keyBy('order_id');
        foreach ($expected as $orderId => [$collected, $settled, $remaining, $status]) {
            $f = $financial->forOrder(GhOrder::query()->findOrFail($orderId));
            $this->assertSame([$collected, $settled, $remaining, $status], [
                $f['custody']['collected'], $f['custody']['settled'], $f['custody']['remaining'], $f['settlement_status'],
            ], "order panel {$orderId}");
            $row = $rows[$orderId];
            $this->assertSame([$collected, $settled, $remaining, $status], [
                number_format((float) $row->driver_cash_collected, 2, '.', ''), number_format((float) $row->driver_cash_settled, 2, '.', ''),
                (string) $row->driver_cash_outstanding, $row->cash_settlement_status,
            ], "invoice list {$orderId}");
        }
        $this->assertSame(GhCashSettlementStatus::NOT_APPLICABLE, $rows[$direct->id]->cash_settlement_status);
        $this->assertSame(GhCashSettlementStatus::NOT_APPLICABLE, $financial->forOrder($direct)['settlement_status']);

        $report = app(GhDriverCashReportService::class);
        $period = GhReportPeriod::fromInput(['preset' => GhReportPeriod::PRESET_LAST_30]);
        $totals = $report->totals($period, $a->id);
        $this->assertSame(['450.50', '380.00', '100.00', '170.50'], [$totals['collected'], $totals['settled'], $totals['reversed'], $totals['outstanding_now']]);
        $this->assertSame(['not_settled' => 0, 'partial' => 1, 'settled' => 2], $totals['counts']);
        $this->assertSame('230.50', $report->totals($period, null)['outstanding_now']);
        $open = $report->outstandingInvoicesForExport($a->id);
        $this->assertSame([$c2->id], $open->pluck('id')->all());
        $this->assertSame('170.50', $open->first()->remaining_amount);
        $managers = collect($report->byManager($period, $a->id))->keyBy(fn ($r) => $r['manager']->id);
        $this->assertSame([3, '300.00', 1, '100.00'], [$managers[$m1->id]['settled_count'], $managers[$m1->id]['settled'], $managers[$m1->id]['reversed_count'], $managers[$m1->id]['reversed']]);
        $this->assertSame([1, '80.00', 0], [$managers[$m2->id]['settled_count'], $managers[$m2->id]['settled'], $managers[$m2->id]['reversed_count']]);

        $ledger = app(GhDriverCashActivityService::class)->ledger($a, $period);
        $this->assertSame(['0.00', '170.50'], [$ledger['opening'], $ledger['closing']]);

        $events = GhDriverCashEvent::query()->where('driver_id', $a->id)->get()->groupBy('event_type');
        $this->assertCount(3, $events['COLLECTION_RECORDED']);
        $this->assertCount(3, $events['SETTLEMENT_RECORDED']);
        $this->assertSame([$m1->id], $events['SETTLEMENT_REVERSED']->pluck('actor_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$m1->id], $events['SETTLEMENT_ALLOCATED']->pluck('actor_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame($s2->settled_by, $s2->fresh('reversal')->reversal->reversed_by, 'same manager settled and reversed');
    }

    private function legacySettlement(User $driver, User $manager, string $amount): GhDriverCashSettlement
    {
        $settlement = GhDriverCashSettlement::query()->forceCreate([
            'reference' => 'GH-DCS-LEGACY-REC', 'driver_id' => $driver->id, 'amount' => $amount, 'currency' => 'AED',
            'settled_by' => $manager->id, 'settled_at' => now('UTC')->subHour(), 'idempotency_key' => 'legacy-rec',
        ]);
        $balance = GhDriverCashBalance::query()->findOrFail($driver->id);
        $balance->forceFill([
            'settled_total' => bcadd((string) $balance->settled_total, $amount, 2),
            'outstanding' => bcsub((string) $balance->outstanding, $amount, 2),
        ])->save();

        return $settlement;
    }
}
