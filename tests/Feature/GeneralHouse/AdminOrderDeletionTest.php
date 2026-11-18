<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhMinOrderOverride;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderEvent;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhPayment;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Corrections\GhCreditNoteService;
use App\Services\GeneralHouse\Corrections\GhDebitAdjustmentService;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Orders\GhDriverWorkspaceService;
use App\Services\GeneralHouse\Orders\GhOrderBoardService;
use App\Services\GeneralHouse\Orders\GhOrderDeletionService;
use App\Services\GeneralHouse\Orders\GhOrderEventService;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Services\GeneralHouse\Payments\GhPaymentService;
use App\Support\GeneralHouse\GhCreditNoteReason;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhDebitAdjustmentLineType;
use App\Support\GeneralHouse\GhDebitAdjustmentReason;
use App\Support\GeneralHouse\GhMinOrderOverrideType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderBoardTab;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminOrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Dubai';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['orders', 'invoices', 'payments', 'credit_notes', 'debit_adjustments', 'driver_cash_collections'] as $seq) {
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
            'general_house.standard_vat_rate' => '5.00',
            'general_house.payments.cash_enabled' => true,
            'general_house.payments.partial_payments_enabled' => true,
            'general_house.corrections.credit_notes_enabled' => true,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00', self::TZ));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_manager_soft_deletes_later_order_with_full_audit(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder(['status' => GhOrderStatus::LATER]);
        $line = GhOrderLine::factory()->create(['order_id' => $order->id]);

        $this->actingAs($manager)->delete(route('gh.orders.destroy', $order), [
            'delete_reason' => '  Duplicate booking  ',
        ])->assertRedirect(route('gh.orders.index'));

        $this->assertNull(GhOrder::query()->find($order->id));
        $row = GhOrder::withTrashed()->findOrFail($order->id);
        $this->assertSame(GhOrderStatus::LATER, $row->status);
        $this->assertSame((int) $manager->id, (int) $row->deleted_by);
        $this->assertSame('Duplicate booking', $row->delete_reason);
        // Stored in UTC (10:00 Dubai = 06:00 UTC), not app.timezone.
        $this->assertSame('2026-11-18 06:00:00', $row->getRawOriginal('deleted_at'));

        $event = GhOrderEvent::query()->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::ORDER_DELETED)->firstOrFail();
        $this->assertSame((int) $manager->id, (int) $event->actor_id);
        $this->assertSame(GhOrderStatus::LATER, $event->from_status);
        $this->assertSame('Duplicate booking', $event->payload['reason']);
        $this->assertSame($order->reference, $event->payload['reference']);

        // History, lines and the reference are preserved (nothing hard-deleted).
        $this->assertTrue(GhOrderEvent::query()->where('order_id', $order->id)->count() >= 1);
        $this->assertDatabaseHas('gh_order_lines', ['id' => $line->id, 'order_id' => $order->id]);
        $this->assertDatabaseHas('gh_orders', ['id' => $order->id, 'reference' => $order->reference]);
    }

    public function test_pickup_order_without_confirmed_pickup_can_be_deleted(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'picked_up_at' => null]);

        $this->actingAs($manager)->delete(route('gh.orders.destroy', $order), ['delete_reason' => 'Customer cancelled by phone'])
            ->assertRedirect(route('gh.orders.index'));

        $this->assertTrue(GhOrder::withTrashed()->findOrFail($order->id)->trashed());
    }

    public function test_only_manager_may_delete(): void
    {
        $order = $this->makeOrder(['status' => GhOrderStatus::LATER]);

        foreach ([
            User::factory()->driver()->create(),
            User::factory()->employee()->create(),
            User::factory()->teamLeader()->create(),
            User::factory()->accountant()->create(),
            User::factory()->assistant()->create(),
        ] as $user) {
            $this->actingAs($user)->delete(route('gh.orders.destroy', $order), ['delete_reason' => 'x'])->assertForbidden();
        }

        auth()->logout();
        $this->delete(route('gh.orders.destroy', $order), ['delete_reason' => 'x'])->assertRedirect();

        $this->assertFalse($order->fresh()->trashed());
        $this->assertSame(0, GhOrderEvent::query()->where('event_type', GhOrderEventType::ORDER_DELETED)->count());
    }

    public function test_service_rejects_non_manager_directly(): void
    {
        $order = $this->makeOrder(['status' => GhOrderStatus::LATER]);
        $this->expectException(ValidationException::class);
        app(GhOrderDeletionService::class)->delete($order, User::factory()->driver()->create(), 'bypass');
    }

    public function test_non_eligible_statuses_are_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $picked = Carbon::now()->subHour();

        $cases = [
            GhOrderStatus::WORKING => ['picked_up_at' => $picked],
            GhOrderStatus::DELIVERY => ['picked_up_at' => $picked],
            GhOrderStatus::COMPLETED => ['picked_up_at' => $picked, 'delivered_at' => Carbon::now()],
            GhOrderStatus::CANCELLED => ['cancelled_at' => Carbon::now()],
        ];
        foreach ($cases as $status => $extra) {
            $order = $this->makeOrder(['status' => $status] + $extra);

            $this->actingAs($manager)->withSession(['locale' => 'en'])
                ->delete(route('gh.orders.destroy', $order), ['delete_reason' => 'try'])
                ->assertSessionHasErrors('order');

            $this->assertFalse($order->fresh()->trashed(), $status);
        }
        $this->assertSame(0, GhOrderEvent::query()->where('event_type', GhOrderEventType::ORDER_DELETED)->count());
    }

    public function test_confirmed_physical_pickup_blocks_deletion_even_in_pickup_or_later(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();

        foreach ([GhOrderStatus::PICKUP, GhOrderStatus::LATER] as $status) {
            $order = $this->makeOrder([
                'status' => $status,
                'picked_up_at' => Carbon::now()->subHours(3),
                'pickup_confirmed_by' => $driver->id,
            ]);

            $this->actingAs($manager)->withSession(['locale' => 'en'])
                ->delete(route('gh.orders.destroy', $order), ['delete_reason' => 'try'])
                ->assertSessionHasErrors(['order' => __('gh.errors.order_delete_pickup_confirmed', [], 'en')]);

            $this->assertFalse($order->fresh()->trashed(), $status);
        }
    }

    public function test_reason_is_mandatory(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder(['status' => GhOrderStatus::LATER]);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $required = __('gh.errors.order_delete_reason_required');
            $this->actingAs($manager)->withSession(['locale' => $locale])
                ->delete(route('gh.orders.destroy', $order), [])
                ->assertSessionHasErrors(['delete_reason' => $required]);
            $this->actingAs($manager)->withSession(['locale' => $locale])
                ->delete(route('gh.orders.destroy', $order), ['delete_reason' => '   '])
                ->assertSessionHasErrors(['delete_reason' => $required]);
            $this->actingAs($manager)->withSession(['locale' => $locale])
                ->delete(route('gh.orders.destroy', $order), ['delete_reason' => str_repeat('a', 501)])
                ->assertSessionHasErrors(['delete_reason' => __('gh.errors.order_delete_reason_too_long')]);
        }
        app()->setLocale('en');

        $this->assertFalse($order->fresh()->trashed());
    }

    public function test_financial_records_block_deletion_with_bilingual_message_and_stay_untouched(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager);
        $order = $invoice->order;
        app(GhPaymentService::class)->recordConfirmedCash($invoice, $manager, ['amount' => '40.00', 'idempotency_key' => 'del-pay']);
        app(GhCreditNoteService::class)->createDraft($invoice->fresh(['lines']), $manager, [
            'reason_code' => GhCreditNoteReason::QUANTITY_ERROR,
            'reason_text' => 'Guard test',
            'idempotency_key' => 'del-cn',
            'lines' => [['invoice_line_id' => (int) $invoice->lines->first()->id, 'quantity_credited' => 1]],
        ]);
        // Legacy/inconsistent state: logistics says PICKUP, but finance already exists.
        $order->forceFill(['status' => GhOrderStatus::PICKUP, 'picked_up_at' => null])->save();

        $before = $this->financialSnapshot();
        $this->assertNotEmpty($before['invoices']);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $expected = __('gh.errors.order_delete_financial', ['records' => implode(__('gh.orders.delete_list_separator'), [
                __('gh.orders.delete_financial.issued_invoice'),
                __('gh.orders.delete_financial.payment'),
                __('gh.orders.delete_financial.credit_note'),
            ])]);

            $this->actingAs($manager)->withSession(['locale' => $locale])
                ->delete(route('gh.orders.destroy', $order), ['delete_reason' => 'try'])
                ->assertSessionHasErrors(['order' => $expected]);
        }
        app()->setLocale('en');

        $this->assertFalse($order->fresh()->trashed());
        $this->assertSame($before, $this->financialSnapshot());
        $this->assertSame(0, GhOrderEvent::query()->where('event_type', GhOrderEventType::ORDER_DELETED)->count());
        $this->assertSame(['issued_invoice', 'payment', 'credit_note'], app(GhOrderDeletionService::class)->financialRecords($order));
    }

    public function test_draft_invoice_also_blocks_deletion(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->makeWorkingOrderWithReceivedLine();
        app(GhInvoiceService::class)->createDraftFromOrder($order, $manager);
        $order->forceFill(['status' => GhOrderStatus::PICKUP, 'picked_up_at' => null])->save();

        $this->actingAs($manager)->withSession(['locale' => 'en'])
            ->delete(route('gh.orders.destroy', $order), ['delete_reason' => 'try'])
            ->assertSessionHasErrors('order');

        $this->assertFalse($order->fresh()->trashed());
        $this->assertSame(['invoice'], app(GhOrderDeletionService::class)->financialRecords($order));
    }

    public function test_debit_adjustment_blocks_deletion(): void
    {
        config(['general_house.corrections.debit_adjustments_enabled' => true]);
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager);
        app(GhDebitAdjustmentService::class)->createAndIssue($invoice, $manager, [
            'reason_code' => GhDebitAdjustmentReason::QUANTITY_CORRECTION,
            'reason_text' => 'Guard test',
            'idempotency_key' => 'del-da',
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::QUANTITY_INCREASE,
                'invoice_line_id' => (int) $invoice->lines->first()->id,
                'quantity' => 1,
                'unit_price' => '50.00',
            ]],
        ]);

        $this->assertDeletionBlockedByFinance($invoice->order, $manager, ['issued_invoice', 'debit_adjustment'], 'gh_debit_adjustments');
    }

    public function test_driver_cash_collection_blocks_deletion_without_any_invoice(): void
    {
        config([
            'general_house.payments.driver_delivery_payment_enabled' => true,
            'general_house.payments.overpayment_allowed' => false,
        ]);
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'picked_up_at' => Carbon::now()->subHours(3),
            'pickup_confirmed_by' => $driver->id,
            'scheduled_delivery_at' => Carbon::now()->subHours(3),
        ]);
        app(GhOrderStateService::class)->promoteOneToDelivery($order->fresh(), $manager, 'manual');
        $this->actingAs($driver)->post(route('gh.orders.confirm-delivery', $order), [
            'payment_method' => 'CASH',
            'cash_collected_amount' => '75.50',
        ])->assertRedirect();
        $this->assertSame(1, GhDriverCashCollection::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, GhInvoice::query()->where('order_id', $order->id)->count());

        $this->assertDeletionBlockedByFinance($order, $manager, ['driver_cash'], 'gh_driver_cash_collections');
    }

    public function test_min_order_override_blocks_deletion(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->makeWorkingOrderWithReceivedLine();
        app(GhInvoiceService::class)->createDraftFromOrder($order, $manager, [
            'min_order_override_type' => GhMinOrderOverrideType::WAIVE,
            'min_order_reason' => 'Guard test waive',
        ]);
        $this->assertSame(1, GhMinOrderOverride::query()->where('order_id', $order->id)->count());

        $this->assertDeletionBlockedByFinance($order, $manager, ['invoice', 'min_order_override'], 'gh_min_order_overrides');
    }

    public function test_second_delete_from_a_stale_copy_is_rejected_and_audited_once(): void
    {
        $first = User::factory()->manager()->create();
        $second = User::factory()->manager()->create();
        $order = $this->makeOrder(['status' => GhOrderStatus::LATER]);
        $copyA = GhOrder::query()->findOrFail($order->id);
        $copyB = GhOrder::query()->findOrFail($order->id);
        $deletions = app(GhOrderDeletionService::class);

        $deletions->delete($copyA, $first, 'First admin');
        try {
            $deletions->delete($copyB, $second, 'Second admin');
            $this->fail('A second delete of the same order must be rejected.');
        } catch (ValidationException $e) {
            $this->assertSame([__('gh.errors.order_delete_already_deleted')], $e->errors()['order']);
        }

        $row = GhOrder::withTrashed()->findOrFail($order->id);
        $this->assertSame((int) $first->id, (int) $row->deleted_by);
        $this->assertSame('First admin', $row->delete_reason);
        $this->assertSame(1, GhOrderEvent::query()->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::ORDER_DELETED)->count());
    }

    public function test_guards_are_rechecked_against_the_current_row_not_the_callers_copy(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $deletions = app(GhOrderDeletionService::class);

        $pickedUp = $this->makeOrder(['status' => GhOrderStatus::PICKUP]);
        $stale = GhOrder::query()->findOrFail($pickedUp->id);
        $pickedUp->forceFill([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::now(),
            'pickup_confirmed_by' => $driver->id,
        ])->save();
        $this->assertTrue($deletions->isDeletable($manager, $stale), 'the stale copy still looks deletable');
        $this->assertDeleteRejected(
            fn () => $deletions->delete($stale, $manager, 'race'),
            __('gh.errors.order_delete_status_blocked', ['status' => __('gh.order_status.'.GhOrderStatus::WORKING)]),
        );

        $invoiced = $this->makeWorkingOrderWithReceivedLine();
        $invoiced->forceFill(['status' => GhOrderStatus::PICKUP, 'picked_up_at' => null])->save();
        $stale = GhOrder::query()->findOrFail($invoiced->id);
        $invoiced->forceFill(['status' => GhOrderStatus::WORKING, 'picked_up_at' => Carbon::now()])->save();
        app(GhInvoiceService::class)->createDraftFromOrder($invoiced->fresh(), $manager);
        $invoiced->forceFill(['status' => GhOrderStatus::PICKUP, 'picked_up_at' => null])->save();
        $this->assertDeleteRejected(
            fn () => $deletions->delete($stale, $manager, 'race'),
            __('gh.errors.order_delete_financial', ['records' => __('gh.orders.delete_financial.invoice')]),
        );

        $this->assertSame(0, GhOrder::onlyTrashed()->count());
        $this->assertSame(0, GhOrderEvent::query()->where('event_type', GhOrderEventType::ORDER_DELETED)->count());
    }

    public function test_lock_guards_stamp_and_audit_run_in_one_transaction(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder(['status' => GhOrderStatus::LATER]);
        $outerLevel = DB::transactionLevel();
        $levels = [];
        DB::listen(function ($query) use (&$levels): void {
            $levels[] = [$query->sql, DB::transactionLevel()];
        });

        app(GhOrderDeletionService::class)->delete($order, $manager, 'Mistake');

        $inside = fn (string $needle) => collect($levels)
            ->filter(fn ($q) => str_contains($q[0], $needle))
            ->every(fn ($q) => $q[1] > $outerLevel);
        foreach (['from "gh_orders"', 'from "gh_invoices"', 'from "gh_driver_cash_collections"', 'update "gh_orders"', 'insert into "gh_order_events"'] as $needle) {
            $this->assertTrue(collect($levels)->contains(fn ($q) => str_contains($q[0], $needle)), $needle);
            $this->assertTrue($inside($needle), $needle.' ran outside the deletion transaction');
        }
    }

    public function test_failed_audit_write_rolls_the_deletion_back(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder(['status' => GhOrderStatus::LATER]);
        $this->mock(GhOrderEventService::class, function ($mock): void {
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('audit store unavailable'));
        });

        try {
            app(GhOrderDeletionService::class)->delete($order, $manager, 'Mistake');
            $this->fail('The audit failure must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit store unavailable', $e->getMessage());
        }

        $row = GhOrder::query()->findOrFail($order->id);
        $this->assertNull($row->deleted_at);
        $this->assertNull($row->deleted_by);
        $this->assertNull($row->delete_reason);
    }

    public function test_operational_services_refuse_a_stale_model_of_a_deleted_order(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $driver->id]);
        $stale = GhOrder::query()->findOrFail($order->id);
        app(GhOrderDeletionService::class)->delete($order, $manager, 'Mistake');
        $eventsBefore = GhOrderEvent::query()->where('order_id', $order->id)->count();
        $states = app(GhOrderStateService::class);

        $attempts = [
            'confirm pickup' => fn () => $states->confirmPickup($stale, $driver, []),
            'cancel' => fn () => $states->cancel($stale, $manager, 'late cancel'),
            'failed pickup' => fn () => $states->recordFailedPickup($stale, $driver, ['reason' => 'x']),
            'invoice draft' => fn () => app(GhInvoiceService::class)->createDraftFromOrder($stale, $manager),
        ];
        foreach ($attempts as $label => $attempt) {
            try {
                $attempt();
                $this->fail($label.' processed a deleted order');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame($eventsBefore, GhOrderEvent::query()->where('order_id', $order->id)->count());
        $this->assertSame(GhOrderStatus::PICKUP, GhOrder::withTrashed()->findOrFail($order->id)->status);
        $this->assertSame(0, GhInvoice::query()->count());
    }

    public function test_every_order_endpoint_returns_404_for_a_deleted_order(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $order = $this->makeWorkingOrderWithReceivedLine();
        $order->forceFill(['status' => GhOrderStatus::PICKUP, 'picked_up_at' => null, 'pickup_driver_id' => $driver->id])->save();
        $receivedLine = GhOrderReceivedLine::query()->where('order_id', $order->id)->firstOrFail();
        app(GhOrderDeletionService::class)->delete($order, $manager, 'Mistake');
        $eventsBefore = GhOrderEvent::query()->where('order_id', $order->id)->count();

        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_contains($r->uri(), '{order}'));
        $this->assertGreaterThanOrEqual(20, $routes->count());
        foreach ($routes as $route) {
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $url = route($route->getName(), ['order' => $order->id, 'receivedLine' => $receivedLine->id]);
            foreach ([$manager, $driver] as $user) {
                $this->actingAs($user)->call($method, $url, ['delete_reason' => 'x', 'reason' => 'x'])
                    ->assertNotFound();
            }
        }

        $row = GhOrder::withTrashed()->findOrFail($order->id);
        $this->assertSame(GhOrderStatus::PICKUP, $row->status);
        $this->assertSame($eventsBefore, GhOrderEvent::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, GhInvoice::query()->where('order_id', $order->id)->count());
    }

    public function test_delete_action_is_visible_only_when_eligible(): void
    {
        $manager = User::factory()->manager()->create();
        $eligible = $this->makeOrder(['status' => GhOrderStatus::LATER]);
        $working = $this->makeOrder(['status' => GhOrderStatus::WORKING, 'picked_up_at' => Carbon::now()->subHour()]);
        $pickedPickup = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'picked_up_at' => Carbon::now()->subHour()]);

        foreach (['en' => 'Delete order', 'ar' => 'حذف الطلب'] as $locale => $label) {
            $html = $this->actingAs($manager)->withSession(['locale' => $locale])
                ->get(route('gh.orders.show', $eligible))->assertOk()->getContent();
            $this->assertStringContainsString($label, $html);
            $this->assertStringContainsString('id="gh-delete-order-modal"', $html);
            $this->assertStringContainsString('name="delete_reason"', $html);
            $this->assertStringContainsString('required', substr($html, strpos($html, 'name="delete_reason"'), 120));
        }

        foreach ([$working, $pickedPickup] as $order) {
            $this->actingAs($manager)->withSession(['locale' => 'en'])
                ->get(route('gh.orders.show', $order))->assertOk()
                ->assertDontSee('gh-delete-order-modal', false);
        }
    }

    public function test_deleted_order_is_excluded_from_lists_workspaces_and_direct_access(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $later = $this->makeOrder([
            'status' => GhOrderStatus::LATER,
            'pickup_driver_id' => $driver->id,
            'pickup_starts_at' => Carbon::now()->addDays(3),
            'pickup_ends_at' => Carbon::now()->addDays(3)->addHours(2),
        ]);
        $pickup = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $driver->id]);
        $keeper = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $driver->id]);

        $workspace = app(GhDriverWorkspaceService::class)->dashboard($driver);
        $this->assertContains($pickup->id, $workspace['actionable_pickups']->pluck('id')->all());
        $this->assertContains($later->id, $workspace['upcoming_pickups']->pluck('id')->all());

        foreach ([$later, $pickup] as $order) {
            $this->actingAs($manager)->delete(route('gh.orders.destroy', $order), ['delete_reason' => 'Mistake'])->assertRedirect();
        }

        $workspace = app(GhDriverWorkspaceService::class)->dashboard($driver);
        $this->assertSame([$keeper->id], $workspace['actionable_pickups']->pluck('id')->all());
        $this->assertSame([], $workspace['upcoming_pickups']->pluck('id')->all());

        $board = app(GhOrderBoardService::class);
        foreach ([GhOrderBoardTab::ALL_ORDERS, GhOrderBoardTab::LATER, GhOrderBoardTab::PICKUP] as $tab) {
            $ids = collect($board->boardForAdmin(['tab' => $tab, 'page' => 1])['groups'])
                ->flatMap(fn ($g) => $g['orders'])->pluck('id')->all();
            $this->assertNotContains($later->id, $ids, $tab);
            $this->assertNotContains($pickup->id, $ids, $tab);
        }
        $driverIds = collect($board->boardForDriver($driver, GhOrderBoardTab::PICKUP)['groups'])
            ->flatMap(fn ($g) => $g['orders'])->pluck('id')->all();
        $this->assertSame([$keeper->id], $driverIds);

        // Drop the "order deleted" flash (it names the reference) so only the list itself is asserted.
        $this->flushSession();
        $this->actingAs($manager)->get(route('gh.orders.index', ['tab' => 'all']))->assertOk()
            ->assertDontSee($pickup->reference, false)->assertSee($keeper->reference, false);
        $this->actingAs($manager)->get(route('gh.orders.show', $pickup))->assertNotFound();
        $this->actingAs($driver)->get(route('gh.driver.pickup', $pickup))->assertNotFound();
        // Second delete of the same order is a no-op 404 and writes no extra audit row.
        $this->actingAs($manager)->delete(route('gh.orders.destroy', $pickup), ['delete_reason' => 'again'])->assertNotFound();
        $this->assertSame(2, GhOrderEvent::query()->where('event_type', GhOrderEventType::ORDER_DELETED)->count());
    }

    public function test_scheduler_ignores_deleted_orders_and_survives_a_mid_run_deletion(): void
    {
        $manager = User::factory()->manager()->create();
        $due = $this->makeOrder(['status' => GhOrderStatus::LATER]);
        $deleted = $this->makeOrder(['status' => GhOrderStatus::LATER]);
        // Both are due (pickup date = today in Dubai); same-day LATER rows exist after reschedule/hold edge cases.
        foreach ([$due, $deleted] as $order) {
            $order->forceFill([
                'pickup_starts_at' => Carbon::parse('2026-11-18 18:00:00', self::TZ)->utc(),
                'pickup_ends_at' => Carbon::parse('2026-11-18 20:00:00', self::TZ)->utc(),
            ])->save();
        }
        $stale = GhOrder::query()->findOrFail($deleted->id);

        $this->actingAs($manager)->delete(route('gh.orders.destroy', $deleted), ['delete_reason' => 'Mistake'])->assertRedirect();

        $states = app(GhOrderStateService::class);
        $this->assertFalse($states->promoteOneToPickup($stale), 'stale model of a deleted order is skipped, not a crash');
        $this->assertSame(1, $states->promoteEligibleLaterOrders());
        $this->artisan('gh:promote-pickup-eligible')->assertSuccessful();

        $this->assertSame(GhOrderStatus::PICKUP, $due->fresh()->status);
        $trashed = GhOrder::withTrashed()->findOrFail($deleted->id);
        $this->assertSame(GhOrderStatus::LATER, $trashed->status);
        $this->assertSame(0, GhOrderEvent::query()->where('order_id', $deleted->id)
            ->where('event_type', GhOrderEventType::PICKUP_PROMOTED)->count());
    }

    /** @param array<string, mixed> $overrides */
    private function makeOrder(array $overrides = []): GhOrder
    {
        $zone = GhZone::factory()->create(['min_order_amount' => '0.00', 'min_order_policy' => GhMinOrderPolicy::SURCHARGE]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create(['customer_id' => $customer->id, 'zone_id' => $zone->id]);

        return GhOrder::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => self::TZ,
            'status' => GhOrderStatus::PICKUP,
            'pickup_starts_at' => Carbon::now()->addHours(2),
            'pickup_ends_at' => Carbon::now()->addHours(4),
            'scheduled_delivery_at' => null,
            'created_by' => User::factory()->manager()->create()->id,
        ], $overrides));
    }

    private function makeWorkingOrderWithReceivedLine(string $unitPrice = '50.00', int $qty = 2): GhOrder
    {
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::now()->subHours(3),
            'scheduled_delivery_at' => Carbon::now()->subHours(3),
            'pickup_starts_at' => Carbon::now()->subDay(),
            'pickup_ends_at' => Carbon::now()->subDay()->addHours(2),
        ]);
        $offering = GhProductOffering::factory()->create(['standard_price' => $unitPrice]);
        $names = ['product_name_ar' => 'منتج', 'product_name_en' => 'Product', 'treatment_name_ar' => 'كي', 'treatment_name_en' => 'Iron'];
        $line = GhOrderLine::factory()->create([
            'order_id' => $order->id,
            'offering_id' => $offering->id,
            'quantity' => $qty,
            'unit_price_estimate' => $unitPrice,
        ] + $names);
        GhOrderReceivedLine::query()->create([
            'order_id' => $order->id,
            'order_line_id' => $line->id,
            'offering_id' => $offering->id,
            'quantity' => $qty,
            'discrepancy_type' => GhReceivedDiscrepancy::MATCH,
            'sort_order' => 0,
            'unit_price_estimate' => $unitPrice,
            'currency' => 'AED',
            'price_source' => 'STANDARD',
        ] + $names);

        return $order;
    }

    private function issueInvoice(User $manager): GhInvoice
    {
        $order = $this->makeWorkingOrderWithReceivedLine();
        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order, $manager);

        return app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
    }

    /**
     * Forces a legacy/inconsistent PICKUP state so only the financial guard can block.
     *
     * @param  list<string>  $expectedKeys
     */
    private function assertDeletionBlockedByFinance(GhOrder $order, User $manager, array $expectedKeys, string $table): void
    {
        $order->forceFill([
            'status' => GhOrderStatus::PICKUP,
            'picked_up_at' => null,
            'pickup_confirmed_by' => null,
            'delivered_at' => null,
        ])->save();
        $deletions = app(GhOrderDeletionService::class);
        $this->assertSame($expectedKeys, $deletions->financialRecords($order));
        $snapshot = fn () => DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $before = $snapshot();
        $this->assertNotEmpty($before);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $expected = __('gh.errors.order_delete_financial', ['records' => implode(
                __('gh.orders.delete_list_separator'),
                array_map(fn (string $key) => __('gh.orders.delete_financial.'.$key), $expectedKeys),
            )]);
            $this->actingAs($manager)->withSession(['locale' => $locale])
                ->delete(route('gh.orders.destroy', $order), ['delete_reason' => 'try'])
                ->assertSessionHasErrors(['order' => $expected]);
            $this->assertDeleteRejected(fn () => $deletions->delete($order->fresh(), $manager, 'direct'), $expected);
        }
        app()->setLocale('en');

        $this->assertFalse($order->fresh()->trashed());
        $this->assertSame($before, $snapshot());
        $this->assertSame(0, GhOrderEvent::query()->where('event_type', GhOrderEventType::ORDER_DELETED)->count());
    }

    private function assertDeleteRejected(callable $attempt, string $message): void
    {
        try {
            $attempt();
            $this->fail('Deletion should have been rejected: '.$message);
        } catch (ValidationException $e) {
            $this->assertSame([$message], $e->errors()['order']);
        }
    }

    /** @return array<string, mixed> */
    private function financialSnapshot(): array
    {
        return [
            'invoices' => GhInvoice::query()->orderBy('id')->get()->toArray(),
            'payments' => GhPayment::query()->orderBy('id')->get()->toArray(),
            'credit_notes' => DB::table('gh_credit_notes')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'refunds' => DB::table('gh_refunds')->count(),
        ];
    }
}
