<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhDriverCashBalance;
use App\Models\GeneralHouse\GhDriverCashEvent;
use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\GeneralHouse\GhDriverCashSettlementAllocation;
use App\Models\GeneralHouse\GhDriverCashSettlementReversal;
use App\Models\GeneralHouse\GhOrder;
use App\Models\User;
use App\Services\GeneralHouse\Payments\GhCashSettlementQueryService;
use App\Services\GeneralHouse\Payments\GhDriverCashCustodyService;
use App\Services\GeneralHouse\Payments\GhDriverCashSettlementService;
use App\Services\GeneralHouse\Payments\GhOrderFinancialSummaryService;
use App\Support\GeneralHouse\GhCashSettlementStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\GeneralHouse\Concerns\GhDriverCashFixtures;
use Tests\TestCase;

class DriverCashSettlementServiceTest extends TestCase
{
    use GhDriverCashFixtures;
    use RefreshDatabase;

    private GhDriverCashSettlementService $service;

    private User $manager;

    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDriverCash();
        $this->service = app(GhDriverCashSettlementService::class);
        $this->manager = User::factory()->manager()->create();
        $this->driver = User::factory()->driver()->create();
    }

    public function test_full_settlement_of_one_invoice_marks_it_settled(): void
    {
        $c = $this->collectCash($this->driver, '100.00');

        $settlement = $this->service->settle($this->driver, $this->manager, $this->settlePayload('100.00', [$c->id => '100.00']));

        $this->assertSame('100.00', (string) $settlement->amount);
        $this->assertSame(1, $settlement->allocations->count());
        $this->assertSame($c->id, $settlement->allocations->first()->collection_id);
        $this->assertSame('0.00', $this->outstanding());
        $this->assertSame(GhCashSettlementStatus::SETTLED, $this->orderStatus($c->order_id));
        $event = GhDriverCashEvent::query()->where('event_type', 'SETTLEMENT_RECORDED')->sole();
        $this->assertSame($this->manager->id, $event->actor_id);
        $this->assertSame('100.00', $event->payload['allocations'][0]['amount']);
    }

    public function test_partial_then_second_settlement_completes_invoice(): void
    {
        $c = $this->collectCash($this->driver, '100.00');

        $this->service->settle($this->driver, $this->manager, $this->settlePayload('40.00', [$c->id => '40.00']));
        $this->assertSame(GhCashSettlementStatus::PARTIAL, $this->orderStatus($c->order_id));
        $this->assertSame('60.00', $this->outstanding());

        $this->service->settle($this->driver, $this->manager, $this->settlePayload('60.00', [$c->id => '60.00']));
        $this->assertSame(GhCashSettlementStatus::SETTLED, $this->orderStatus($c->order_id));
        $this->assertSame('0.00', $this->outstanding());
        $this->assertSame(2, GhDriverCashSettlement::query()->count());
    }

    public function test_manager_chooses_invoices_no_fifo_and_one_settlement_covers_many(): void
    {
        $oldest = $this->collectCash($this->driver, '100.00');
        $b = $this->collectCash($this->driver, '50.00');
        $c = $this->collectCash($this->driver, '30.00');

        $this->service->settle($this->driver, $this->manager, $this->settlePayload('65.00', [$b->id => '50.00', $c->id => '15.00']));

        $this->assertSame(GhCashSettlementStatus::NOT_SETTLED, $this->orderStatus($oldest->order_id));
        $this->assertSame(GhCashSettlementStatus::SETTLED, $this->orderStatus($b->order_id));
        $this->assertSame(GhCashSettlementStatus::PARTIAL, $this->orderStatus($c->order_id));
        $this->assertSame('115.00', $this->outstanding());
        $this->assertSame(2, GhDriverCashSettlementAllocation::query()->count());
    }

    public function test_total_mismatch_is_rejected_and_writes_nothing(): void
    {
        $c = $this->collectCash($this->driver, '100.00');

        $this->assertValidationError('received_amount', fn () => $this->service->settle(
            $this->driver, $this->manager, $this->settlePayload('90.00', [$c->id => '80.00'])
        ));
        $this->assertNothingSettled('100.00');
    }

    public function test_allocation_above_collection_remaining_is_rejected(): void
    {
        $c = $this->collectCash($this->driver, '100.00');
        $this->collectCash($this->driver, '100.00');
        $this->service->settle($this->driver, $this->manager, $this->settlePayload('70.00', [$c->id => '70.00']));

        $this->assertValidationError('allocations.'.$c->id.'.amount', fn () => $this->service->settle(
            $this->driver, $this->manager, $this->settlePayload('40.00', [$c->id => '40.00'])
        ));
        $this->assertSame('130.00', $this->outstanding());
        $this->assertSame(1, GhDriverCashSettlement::query()->count());
    }

    public function test_fully_settled_collection_cannot_be_settled_again(): void
    {
        $c = $this->collectCash($this->driver, '100.00');
        $this->service->settle($this->driver, $this->manager, $this->settlePayload('100.00', [$c->id => '100.00']));

        $this->assertValidationError('received_amount', fn () => $this->service->settle(
            $this->driver, $this->manager, $this->settlePayload('10.00', [$c->id => '10.00'])
        ));
        $this->assertSame(1, GhDriverCashSettlement::query()->count());
        $this->assertSame('0.00', $this->outstanding());
    }

    public function test_collection_of_another_driver_is_rejected(): void
    {
        $other = User::factory()->driver()->create();
        $mine = $this->collectCash($this->driver, '100.00');
        $theirs = $this->collectCash($other, '50.00');

        $this->assertValidationError('allocations.'.$theirs->id.'.amount', fn () => $this->service->settle(
            $this->driver, $this->manager, $this->settlePayload('60.00', [$mine->id => '10.00', $theirs->id => '50.00'])
        ));
        $this->assertNothingSettled('100.00');
        $this->assertSame('50.00', app(GhDriverCashCustodyService::class)->outstandingFor($other));
    }

    public function test_duplicate_zero_and_missing_allocations_are_rejected(): void
    {
        $c = $this->collectCash($this->driver, '100.00');
        $base = ['received_amount' => '20.00', 'idempotency_key' => 'k-dup'];

        $this->assertValidationError('allocations.'.$c->id.'.amount', fn () => $this->service->settle($this->driver, $this->manager, $base + [
            'allocations' => [['collection_id' => $c->id, 'amount' => '10.00'], ['collection_id' => $c->id, 'amount' => '10.00']],
        ]));
        $this->assertValidationError('allocations.'.$c->id.'.amount', fn () => $this->service->settle($this->driver, $this->manager, $base + [
            'allocations' => [['collection_id' => $c->id, 'amount' => '0']],
        ]));
        $this->assertValidationError('allocations', fn () => $this->service->settle($this->driver, $this->manager, $base + ['allocations' => []]));
        $this->assertValidationError('received_amount', fn () => $this->service->settle($this->driver, $this->manager, [
            'received_amount' => '-5', 'idempotency_key' => 'k-neg', 'allocations' => [['collection_id' => $c->id, 'amount' => '5']],
        ]));
        $this->assertNothingSettled('100.00');
    }

    public function test_idempotent_retry_returns_same_settlement(): void
    {
        $c = $this->collectCash($this->driver, '100.00');
        $payload = $this->settlePayload('40.00', [$c->id => '40.00'], 'same-key');

        $first = $this->service->settle($this->driver, $this->manager, $payload);
        $second = $this->service->settle($this->driver, $this->manager, $payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, GhDriverCashSettlement::query()->count());
        $this->assertSame(1, GhDriverCashSettlementAllocation::query()->count());
        $this->assertSame('60.00', $this->outstanding());
    }

    public function test_settlement_time_is_stored_in_utc_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 21:30:00', 'UTC'));
        try {
            $c = $this->collectCash($this->driver, '100.00');
            $s = $this->service->settle($this->driver, $this->manager, $this->settlePayload('100.00', [$c->id => '100.00']));

            $raw = DB::table('gh_driver_cash_settlements')->where('id', $s->id)->value('settled_at');
            $this->assertSame('2026-11-18 21:30:00', Carbon::parse($raw)->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-19 01:30', $s->fresh()->settled_at->timezone('Asia/Dubai')->format('Y-m-d H:i'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reversal_restores_custody_keeps_original_and_is_single_use(): void
    {
        $c = $this->collectCash($this->driver, '100.00');
        $s = $this->service->settle($this->driver, $this->manager, $this->settlePayload('100.00', [$c->id => '100.00']));
        $reverser = User::factory()->manager()->create();

        $this->assertValidationError('reason', fn () => $this->service->reverse($s, $reverser, ['reason' => '  ', 'idempotency_key' => 'r1']));

        $reversal = $this->service->reverse($s, $reverser, ['reason' => 'Counted wrong envelope', 'idempotency_key' => 'r1']);
        $again = $this->service->reverse($s->fresh(), $reverser, ['reason' => 'retry', 'idempotency_key' => 'r1']);

        $this->assertSame($reversal->id, $again->id);
        $this->assertStringStartsWith('GH-DCR-', $reversal->reference);
        $this->assertSame($reverser->id, $reversal->reversed_by);
        $this->assertSame('100.00', $this->outstanding());
        $this->assertSame(GhCashSettlementStatus::NOT_SETTLED, $this->orderStatus($c->order_id));
        $this->assertNotNull(GhDriverCashSettlement::query()->find($s->id), 'original settlement must remain');
        $this->assertSame(1, GhDriverCashSettlementAllocation::query()->count(), 'allocations kept for audit');
        $this->assertSame(1, GhDriverCashEvent::query()->where('event_type', 'SETTLEMENT_REVERSED')->count());

        $this->assertValidationError('reason', fn () => $this->service->reverse($s->fresh(), $reverser, ['reason' => 'again', 'idempotency_key' => 'r2']));
        $this->assertSame(1, GhDriverCashSettlementReversal::query()->count());

        $this->service->settle($this->driver, $this->manager, $this->settlePayload('100.00', [$c->id => '100.00']));
        $this->assertSame(GhCashSettlementStatus::SETTLED, $this->orderStatus($c->order_id));
        $this->assertSame('0.00', $this->outstanding());
    }

    public function test_same_manager_reverses_own_settlement_in_one_step(): void
    {
        $c = $this->collectCash($this->driver, '100.00');
        $s = $this->service->settle($this->driver, $this->manager, $this->settlePayload('100.00', [$c->id => '100.00']));
        $this->assertTrue($this->service->mayReverse($s->fresh('reversal'), $this->manager));

        $reversal = $this->service->reverse($s, $this->manager, ['reason' => 'Recorded on the wrong driver', 'idempotency_key' => 'self-rev']);

        $this->assertSame($this->manager->id, $reversal->reversed_by);
        $this->assertSame($s->settled_by, $reversal->reversed_by);
        $this->assertSame('100.00', $this->outstanding(), 'effective immediately, no pending approval');
        $this->assertSame(GhCashSettlementStatus::NOT_SETTLED, $this->orderStatus($c->order_id));
        $this->assertFalse($this->service->mayReverse($s->fresh('reversal'), $this->manager));
    }

    public function test_driver_cannot_settle_or_reverse(): void
    {
        $c = $this->collectCash($this->driver, '100.00');

        $this->assertValidationError('settlement', fn () => $this->service->settle($this->driver, $this->driver, $this->settlePayload('10.00', [$c->id => '10.00'])));
        $s = $this->service->settle($this->driver, $this->manager, $this->settlePayload('10.00', [$c->id => '10.00']));
        $this->assertValidationError('settlement', fn () => $this->service->reverse($s, $this->driver, ['reason' => 'x', 'idempotency_key' => 'r']));
        $this->assertSame(0, GhDriverCashSettlementReversal::query()->count());
    }

    public function test_legacy_unallocated_settlement_can_be_allocated_manually_without_balance_change(): void
    {
        $a = $this->collectCash($this->driver, '100.00');
        $b = $this->collectCash($this->driver, '50.00');
        $legacy = $this->legacySettlement('60.00');
        $this->assertSame('90.00', $this->outstanding());
        $this->assertSame('60.00', $this->service->unallocatedFor($legacy));

        $this->assertValidationError('received_amount', fn () => $this->service->settle(
            $this->driver, $this->manager, $this->settlePayload('100.00', [$a->id => '100.00'])
        ));

        $payload = ['allocations' => [['collection_id' => $b->id, 'amount' => '50.00'], ['collection_id' => $a->id, 'amount' => '10.00']], 'idempotency_key' => 'legacy-1'];
        $this->service->allocateUnallocated($legacy, $this->manager, $payload);
        $this->service->allocateUnallocated($legacy->fresh(), $this->manager, $payload);

        $this->assertSame('0.00', $this->service->unallocatedFor($legacy->fresh()));
        $this->assertSame(2, GhDriverCashSettlementAllocation::query()->count());
        $this->assertSame('90.00', $this->outstanding(), 'allocation never changes the balance');
        $this->assertSame(GhCashSettlementStatus::SETTLED, $this->orderStatus($b->order_id));
        $this->assertSame(GhCashSettlementStatus::PARTIAL, $this->orderStatus($a->order_id));
        $this->assertSame(1, GhDriverCashEvent::query()->where('event_type', 'SETTLEMENT_ALLOCATED')->count());

        $this->assertValidationError('allocations', fn () => $this->service->allocateUnallocated($legacy->fresh(), $this->manager, [
            'allocations' => [['collection_id' => $a->id, 'amount' => '1.00']], 'idempotency_key' => 'legacy-2',
        ]));
    }

    public function test_reversed_legacy_settlement_cannot_be_allocated(): void
    {
        $a = $this->collectCash($this->driver, '100.00');
        $legacy = $this->legacySettlement('30.00');
        $this->service->reverse($legacy, $this->manager, ['reason' => 'duplicate entry', 'idempotency_key' => 'rv']);

        $this->assertValidationError('allocations', fn () => $this->service->allocateUnallocated($legacy->fresh(), $this->manager, [
            'allocations' => [['collection_id' => $a->id, 'amount' => '30.00']], 'idempotency_key' => 'al',
        ]));
        $this->assertSame('100.00', $this->outstanding());
    }

    public function test_online_payment_and_uninvoiced_cash_statuses(): void
    {
        $online = $this->invoicedDeliveryOrder($this->driver, '80.00');
        $this->actingAs($this->driver)->post(route('gh.orders.confirm-delivery', $online), ['payment_method' => 'ONLINE'])->assertRedirect();
        $this->assertSame(GhCashSettlementStatus::NOT_APPLICABLE, $this->orderStatus($online->id));

        $custodyOnly = $this->collectCash($this->driver, '25.00', $this->uninvoicedDeliveryOrder($this->driver));
        $this->assertNull($custodyOnly->payment_id);
        $this->assertSame(GhCashSettlementStatus::NOT_SETTLED, $this->orderStatus($custodyOnly->order_id));
        $this->assertSame('25.00', $this->outstanding());
    }

    private function legacySettlement(string $amount): GhDriverCashSettlement
    {
        $legacy = GhDriverCashSettlement::query()->forceCreate([
            'reference' => 'GH-DCS-LEGACY-'.$amount,
            'driver_id' => $this->driver->id,
            'amount' => $amount,
            'currency' => 'AED',
            'settled_by' => $this->manager->id,
            'settled_at' => now('UTC')->subDay(),
            'idempotency_key' => 'legacy-'.$amount,
        ]);
        $balance = GhDriverCashBalance::query()->findOrFail($this->driver->id);
        $balance->forceFill([
            'settled_total' => bcadd((string) $balance->settled_total, $amount, 2),
            'outstanding' => bcsub((string) $balance->outstanding, $amount, 2),
        ])->save();

        return $legacy;
    }

    private function outstanding(): string
    {
        return app(GhDriverCashCustodyService::class)->outstandingFor($this->driver);
    }

    private function orderStatus(int $orderId): string
    {
        $order = GhOrder::query()->findOrFail($orderId);

        return app(GhOrderFinancialSummaryService::class)->forOrder($order)['settlement_status'];
    }

    private function assertNothingSettled(string $expectedOutstanding): void
    {
        $this->assertSame(0, GhDriverCashSettlement::query()->count());
        $this->assertSame(0, GhDriverCashSettlementAllocation::query()->count());
        $this->assertSame($expectedOutstanding, $this->outstanding());
    }

    private function assertValidationError(string $key, callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected ValidationException on '.$key);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), 'errors: '.json_encode($e->errors()));
        }
    }

    public function test_query_service_is_consistent_with_summary(): void
    {
        $c = $this->collectCash($this->driver, '100.00');
        $this->service->settle($this->driver, $this->manager, $this->settlePayload('30.00', [$c->id => '30.00']));

        $row = DB::table('gh_orders')->where('gh_orders.id', $c->order_id)
            ->selectSub(app(GhCashSettlementQueryService::class)->collectedForOrder('gh_orders.id'), 'collected')
            ->selectSub(app(GhCashSettlementQueryService::class)->settledForOrder('gh_orders.id'), 'settled')
            ->first();
        $this->assertSame('100.00', number_format((float) $row->collected, 2, '.', ''));
        $this->assertSame('30.00', number_format((float) $row->settled, 2, '.', ''));
    }
}
