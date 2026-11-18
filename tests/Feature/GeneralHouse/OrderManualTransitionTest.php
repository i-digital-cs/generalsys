<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderEvent;
use App\Models\GeneralHouse\GhPayment;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderManualTransitionService;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhPaymentMethod;
use App\Support\GeneralHouse\GhPaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderManualTransitionTest extends TestCase
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

    public function test_valid_forward_later_to_pickup_when_eligible(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::LATER,
            'pickup_driver_id' => $driver->id,
            'pickup_starts_at' => Carbon::parse('2026-11-15 20:00:00', $tz)->utc(),
            'pickup_ends_at' => Carbon::parse('2026-11-15 21:00:00', $tz)->utc(),
        ]);

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::PICKUP,
            'reason' => 'Eligible window open',
        ])->assertRedirect(route('gh.orders.show', $order));

        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
        $this->assertManualEvent($order->id, GhOrderStatus::LATER, GhOrderStatus::PICKUP, 'MANUAL');
    }

    public function test_early_later_to_pickup_requires_reason_assignment_and_audits(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::LATER,
            'pickup_driver_id' => null,
            'pickup_starts_at' => Carbon::parse('2026-11-20 10:00:00', $tz)->utc(),
            'pickup_ends_at' => Carbon::parse('2026-11-20 12:00:00', $tz)->utc(),
        ]);

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::PICKUP,
            'reason' => 'Customer ready now',
        ])->assertSessionHasErrors('to_status');

        $order->forceFill(['pickup_driver_id' => $driver->id])->save();

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order->fresh()), [
            'to_status' => GhOrderStatus::PICKUP,
        ])->assertSessionHasErrors('reason');

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order->fresh()), [
            'to_status' => GhOrderStatus::PICKUP,
            'reason' => 'Customer ready now — early force',
        ])->assertRedirect();

        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
        $event = $this->assertManualEvent($order->id, GhOrderStatus::LATER, GhOrderStatus::PICKUP, 'MANUAL');
        $this->assertTrue((bool) ($event->payload['early_force'] ?? false));
        $this->assertSame((int) $driver->id, (int) ($event->payload['pickup_driver_id'] ?? 0));
    }

    public function test_valid_backward_pickup_to_later_holds_indefinitely_until_resume(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_starts_at' => Carbon::parse('2026-11-15 14:00:00', $tz)->utc(),
            'pickup_ends_at' => Carbon::parse('2026-11-15 15:00:00', $tz)->utc(),
        ]);

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::LATER,
        ])->assertSessionHasErrors('reason');

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::LATER,
            'reason' => 'Customer asked to postpone visibility',
        ])->assertRedirect();

        $fresh = $order->fresh();
        $this->assertSame(GhOrderStatus::LATER, $fresh->status);
        $this->assertTrue((bool) $fresh->auto_promote_held);
        $event = $this->assertManualEvent($order->id, GhOrderStatus::PICKUP, GhOrderStatus::LATER, 'MANUAL');
        $this->assertSame('backward', $event->payload['direction'] ?? null);
        $this->assertTrue((bool) ($event->payload['auto_promote_held'] ?? false));
        $this->assertArrayNotHasKey('suppress_auto_until', $event->payload ?? []);

        // Still eligible for pickup — scheduler must not undo demotion while held.
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleLaterOrders());
        $this->assertSame(GhOrderStatus::LATER, $order->fresh()->status);

        // Days later — still held (no 24h expiry).
        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00:00', $tz));
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleLaterOrders());
        $this->assertSame(GhOrderStatus::LATER, $order->fresh()->status);

        $this->actingAs($manager)->post(route('gh.orders.resume-auto-promote', $order), [])
            ->assertRedirect(route('gh.orders.show', $order));

        $resumed = $order->fresh();
        $this->assertFalse((bool) $resumed->auto_promote_held);
        $this->assertNull($resumed->auto_promote_held_at);
        $resumeEvent = GhOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::AUTO_PROMOTE_RESUMED)
            ->latest('id')
            ->first();
        $this->assertNotNull($resumeEvent);

        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleLaterOrders());
        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
    }

    public function test_pickup_to_working_without_confirmation_denied(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'picked_up_at' => null,
        ]);

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::WORKING,
            'reason' => 'try fabricate',
        ])->assertSessionHasErrors('to_status');

        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
    }

    public function test_working_to_delivery_and_back_with_indefinite_hold(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 14:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $picked = Carbon::parse('2026-11-15 10:00:00', $tz)->utc();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'pickup_driver_id' => $driver->id,
            'picked_up_at' => $picked,
            'pickup_starts_at' => $picked,
            'scheduled_delivery_at' => $picked,
        ]);

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::DELIVERY,
        ])->assertRedirect();
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        // Manual WORKING→DELIVERY must not silently copy the pickup driver.
        $this->assertNull($order->fresh()->delivery_driver_id);

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::WORKING,
            'reason' => 'Opened too early',
        ])->assertRedirect();
        $held = $order->fresh();
        $this->assertSame(GhOrderStatus::WORKING, $held->status);
        $this->assertTrue((bool) $held->auto_promote_held);

        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);

        // Manual path still works (not suppressed).
        app(GhOrderStateService::class)->markDeliveryReady($order->fresh(), $manager);
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
    }

    public function test_working_to_pickup_preserves_pickup_evidence(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $picked = Carbon::parse('2026-11-15 10:00:00', $tz)->utc();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => $picked,
            'pickup_confirmed_by' => $driver->id,
            'pickup_confirmation_notes' => 'ok',
        ]);

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::PICKUP,
            'reason' => 'Wrong package confirmed',
        ])->assertRedirect();

        $fresh = $order->fresh();
        $this->assertSame(GhOrderStatus::PICKUP, $fresh->status);
        $this->assertNotNull($fresh->picked_up_at);
        $this->assertTrue($fresh->picked_up_at->equalTo($picked));
        $this->assertSame((int) $driver->id, (int) $fresh->pickup_confirmed_by);
        $this->assertSame('ok', $fresh->pickup_confirmation_notes);
        $event = $this->assertManualEvent($order->id, GhOrderStatus::WORKING, GhOrderStatus::PICKUP, 'MANUAL');
        $this->assertFalse((bool) ($event->payload['preserved_pickup']['cleared'] ?? true));
        $this->assertSame((int) $driver->id, (int) ($event->payload['preserved_pickup']['pickup_confirmed_by'] ?? 0));
    }

    public function test_completed_to_delivery_preserves_financials_and_holds(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 16:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $delivered = Carbon::parse('2026-11-15 15:00:00', $tz)->utc();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::COMPLETED,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'picked_up_at' => $delivered->copy()->subHours(4),
            'delivered_at' => $delivered,
            'delivered_by' => $driver->id,
            'delivery_notes' => 'left at door',
            'invoice_issued_at' => $delivered->copy()->subHour(),
        ]);

        $invoice = GhInvoice::query()->forceCreate([
            'order_id' => $order->id,
            'number' => 'GH-INV-MT-'.$order->id,
            'status' => GhInvoiceStatus::ISSUED,
            'payment_status' => 'PAID',
            'currency' => 'AED',
            'customer_id' => $order->customer_id,
            'customer_type' => GhCustomerType::B2C,
            'customer_name_ar' => 'عميل',
            'customer_name_en' => 'Customer',
            'order_reference' => 'GH-ORD-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            'lines_gross' => '100.00',
            'total_payable' => '100.00',
            'taxable_base' => '95.24',
            'vat_amount' => '4.76',
            'issued_at' => $delivered->copy()->subHour(),
            'issued_by' => $manager->id,
            'created_by' => $manager->id,
        ]);
        $payment = GhPayment::query()->forceCreate([
            'invoice_id' => $invoice->id,
            'reference' => 'GH-PAY-MT-'.$order->id,
            'method' => GhPaymentMethod::CASH,
            'status' => GhPaymentStatus::CONFIRMED,
            'amount' => '100.00',
            'currency' => 'AED',
            'idempotency_key' => 'gh-mt-pay-'.$order->id,
            'recorded_by' => $driver->id,
            'confirmed_by' => $driver->id,
            'occurred_at' => $delivered,
            'recorded_at' => $delivered,
            'confirmed_at' => $delivered,
        ]);
        $paymentCount = GhPayment::query()->where('invoice_id', $invoice->id)->count();
        $balanceBefore = app(GhPaymentBalanceService::class)->forInvoice($invoice->fresh());
        $invoiceTotalBefore = (string) $invoice->fresh()->total_payable;

        $this->actingAs($driver)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::DELIVERY,
            'reason' => 'driver reopen attempt',
        ])->assertForbidden();
        $this->assertSame(GhOrderStatus::COMPLETED, $order->fresh()->status);

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::DELIVERY,
            'reason' => 'Need to re-open delivery route note',
        ])->assertRedirect();

        $fresh = $order->fresh();
        $this->assertSame(GhOrderStatus::DELIVERY, $fresh->status);
        $this->assertTrue((bool) $fresh->auto_promote_held);
        $this->assertTrue($fresh->delivered_at->equalTo($delivered));
        $this->assertSame((int) $driver->id, (int) $fresh->delivered_by);
        $this->assertSame('left at door', $fresh->delivery_notes);
        $this->assertSame((int) $driver->id, (int) $fresh->delivery_driver_id);
        $this->assertSame($paymentCount, GhPayment::query()->where('invoice_id', $invoice->id)->count());
        $this->assertTrue(GhPayment::query()->whereKey($payment->id)->exists());
        $this->assertSame($invoiceTotalBefore, (string) $invoice->fresh()->total_payable);
        $this->assertSame(GhInvoiceStatus::ISSUED, $invoice->fresh()->status);

        $event = $this->assertManualEvent($order->id, GhOrderStatus::COMPLETED, GhOrderStatus::DELIVERY, 'MANUAL');
        $this->assertTrue((bool) ($event->payload['financial_untouched'] ?? false));
        $this->assertFalse((bool) ($event->payload['preserved_delivery']['cleared'] ?? true));

        // Recomplete without new collection / without rewriting delivery stamp.
        config(['general_house.payments.driver_delivery_payment_enabled' => true]);
        $this->actingAs($driver)->post(route('gh.orders.confirm-delivery', $order), [
            'idempotency_key' => 'recomplete-'.$order->id,
            'notes' => 'recomplete only',
        ])->assertRedirect();

        $done = $order->fresh();
        $this->assertSame(GhOrderStatus::COMPLETED, $done->status);
        $this->assertTrue($done->delivered_at->equalTo($delivered));
        $this->assertSame((int) $driver->id, (int) $done->delivered_by);
        $this->assertSame('left at door', $done->delivery_notes);
        $this->assertSame($paymentCount, GhPayment::query()->where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, GhPayment::query()->where('invoice_id', $invoice->id)->where('status', GhPaymentStatus::CONFIRMED)->count());
        $this->assertSame($invoiceTotalBefore, (string) $invoice->fresh()->total_payable);

        $balanceAfter = app(GhPaymentBalanceService::class)->forInvoice($invoice->fresh());
        $this->assertSame($balanceBefore['remaining_due'], $balanceAfter['remaining_due']);
        $this->assertSame($balanceBefore['payment_status'], $balanceAfter['payment_status']);
        $this->assertSame('0.00', $balanceAfter['remaining_due']);

        $recomplete = GhOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('to_status', GhOrderStatus::COMPLETED)
            ->where('from_status', GhOrderStatus::DELIVERY)
            ->latest('id')
            ->first();
        $this->assertNotNull($recomplete);
        $this->assertTrue((bool) ($recomplete->payload['preserved_delivery'] ?? false));
        $this->assertTrue((bool) ($recomplete->payload['no_new_collection'] ?? false));
    }

    public function test_cancelled_restore_safe_path_and_blocks_with_invoice(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();

        $safe = $this->makeOrder([
            'status' => GhOrderStatus::CANCELLED,
            'cancelled_at' => now('UTC'),
            'cancelled_by' => $manager->id,
            'cancel_reason' => 'customer changed mind',
            'pickup_driver_id' => $driver->id,
            'picked_up_at' => null,
            'invoice_issued_at' => null,
            'pickup_starts_at' => Carbon::parse('2026-11-20 10:00:00', $tz)->utc(),
        ]);

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $safe), [
            'to_status' => GhOrderStatus::LATER,
            'reason' => 'Customer reinstated order',
        ])->assertRedirect();

        $restored = $safe->fresh();
        $this->assertSame(GhOrderStatus::LATER, $restored->status);
        $this->assertNull($restored->cancelled_at);
        $this->assertNull($restored->cancel_reason);
        $event = $this->assertManualEvent($safe->id, GhOrderStatus::CANCELLED, GhOrderStatus::LATER, 'MANUAL');
        $this->assertNotEmpty($event->payload['restored_from_cancel'] ?? null);

        $blocked = $this->makeOrder([
            'status' => GhOrderStatus::CANCELLED,
            'cancelled_at' => now('UTC'),
            'cancel_reason' => 'after invoice attempt',
            'invoice_issued_at' => now('UTC'),
            'picked_up_at' => now('UTC')->subHour(),
        ]);

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $blocked), [
            'to_status' => GhOrderStatus::WORKING,
            'reason' => 'try unsafe restore',
        ])->assertSessionHasErrors('to_status');
        $this->assertSame(GhOrderStatus::CANCELLED, $blocked->fresh()->status);
        $this->assertSame([], app(GhOrderManualTransitionService::class)->permittedDestinations($blocked->fresh()));
    }

    public function test_driver_forbidden_and_duplicate_idempotent(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $driver->id,
            'pickup_starts_at' => Carbon::parse('2026-11-16 10:00:00', $tz)->utc(),
        ]);

        $this->actingAs($driver)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::LATER,
            'reason' => 'nope',
        ])->assertForbidden();

        $order->forceFill(['status' => GhOrderStatus::LATER])->save();
        $svc = app(GhOrderManualTransitionService::class);
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00:00', $tz));
        $svc->transition($order->fresh(), $manager, [
            'to_status' => GhOrderStatus::PICKUP,
            'reason' => 'open',
        ]);
        $count = GhOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::MANUAL_STATUS_TRANSITION)
            ->count();
        $svc->transition($order->fresh(), $manager, [
            'to_status' => GhOrderStatus::PICKUP,
            'reason' => 'duplicate',
        ]);
        $this->assertSame(
            $count,
            GhOrderEvent::query()
                ->where('order_id', $order->id)
                ->where('event_type', GhOrderEventType::MANUAL_STATUS_TRANSITION)
                ->count()
        );
    }

    public function test_show_page_lists_resume_and_permitted_destinations_for_manager(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => now('UTC')->subHour(),
            'pickup_driver_id' => $driver->id,
            'auto_promote_held' => true,
            'auto_promote_held_at' => now('UTC'),
            'auto_promote_held_by' => $manager->id,
            'auto_promote_held_reason' => 'held for review',
        ]);

        $this->actingAs($manager)->get(route('gh.orders.show', $order))
            ->assertOk()
            ->assertSee(__('gh.orders.manual_status_title'), false)
            ->assertSee(route('gh.orders.manual-status', $order), false)
            ->assertSee(__('gh.orders.auto_promote_held_title'), false)
            ->assertSee(route('gh.orders.resume-auto-promote', $order), false);
    }

    public function test_automatic_delivery_promotion_still_works_without_manual_hold(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:05:00', $tz));
        $driver = User::factory()->driver()->create();
        $picked = Carbon::parse('2026-11-15 10:00:00', $tz)->utc();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'pickup_driver_id' => $driver->id,
            'picked_up_at' => $picked,
            'pickup_starts_at' => $picked,
            'scheduled_delivery_at' => $picked,
        ]);

        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $event = GhOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::DELIVERY_READY)
            ->first();
        $this->assertSame('automatic', $event?->payload['trigger'] ?? null);
    }

    public function test_invoice_independent_regression_manual_delivery_without_invoice(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => now('UTC')->subHours(3),
            'invoice_issued_at' => null,
        ]);

        app(GhOrderManualTransitionService::class)->transition($order, $manager, [
            'to_status' => GhOrderStatus::DELIVERY,
        ]);
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $this->assertNull($order->fresh()->invoice_issued_at);
    }

    private function assertManualEvent(int $orderId, string $from, string $to, string $source): GhOrderEvent
    {
        $event = GhOrderEvent::query()
            ->where('order_id', $orderId)
            ->where('event_type', GhOrderEventType::MANUAL_STATUS_TRANSITION)
            ->where('from_status', $from)
            ->where('to_status', $to)
            ->latest('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame($source, $event->payload['source'] ?? null);
        $this->assertNotNull($event->actor_id);
        $this->assertNotNull($event->occurred_at);

        return $event;
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
            'pickup_starts_at' => Carbon::now($tz)->addDay()->setTime(10, 0)->utc(),
            'pickup_ends_at' => Carbon::now($tz)->addDay()->setTime(12, 0)->utc(),
            'scheduled_delivery_at' => null,
            'created_by' => $manager->id,
        ], $overrides));
    }
}
