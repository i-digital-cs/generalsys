<?php

namespace Tests\Feature\GeneralHouse\Concerns;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait GhDriverCashFixtures
{
    protected function setUpDriverCash(): void
    {
        foreach (['orders', 'invoices', 'payments', 'driver_cash_collections', 'driver_cash_settlements', 'driver_cash_settlement_reversals'] as $seq) {
            if (! DB::table('gh_sequences')->where('name', $seq)->exists()) {
                DB::table('gh_sequences')->insert(['name' => $seq, 'next_value' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        config([
            'general_house.min_order_surcharge_tax_class' => null,
            'general_house.min_order.surcharge_enabled' => false,
            'general_house.payments.cash_enabled' => true,
            'general_house.payments.partial_payments_enabled' => true,
            'general_house.payments.overpayment_allowed' => false,
            'general_house.payments.driver_collection_enabled' => false,
            'general_house.payments.driver_delivery_payment_enabled' => true,
            'general_house.standard_vat_rate' => '0.00',
        ]);
    }

    /** Order in DELIVERY with an issued, unpaid invoice of $total AED. */
    protected function invoicedDeliveryOrder(User $driver, string $total = '100.00'): GhOrder
    {
        $manager = User::factory()->manager()->create();
        $order = $this->workingOrder($driver, $manager);
        $offering = GhProductOffering::factory()->create(['standard_price' => $total, 'tax_class' => 'STANDARD_RATED']);
        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(['zone', 'customer']), $manager);
        $draft = app(GhInvoiceService::class)->updateDraft($draft, $manager, [
            'draft_version' => $draft->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'min_order_effective_amount' => '0.00',
            'min_order_reason' => 'driver cash test',
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
        ]);
        app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
        app(GhOrderStateService::class)->promoteOneToDelivery($order->fresh(), $manager, 'manual');

        return $order->fresh();
    }

    protected function uninvoicedDeliveryOrder(User $driver): GhOrder
    {
        $manager = User::factory()->manager()->create();
        $order = $this->workingOrder($driver, $manager);
        app(GhOrderStateService::class)->promoteOneToDelivery($order->fresh(), $manager, 'manual');

        return $order->fresh();
    }

    /** Driver confirms delivery with cash through the real route; returns the custody collection. */
    protected function collectCash(User $driver, ?string $amount = '100.00', ?GhOrder $order = null): GhDriverCashCollection
    {
        $order ??= $this->invoicedDeliveryOrder($driver, $amount);
        $this->actingAs($driver)->post(route('gh.orders.confirm-delivery', $order), [
            'payment_method' => 'CASH',
            'cash_collected_amount' => $amount,
        ])->assertRedirect();

        return GhDriverCashCollection::query()->where('order_id', $order->id)->sole();
    }

    /** @param  array<int, string>  $allocations  collection_id => amount */
    protected function settlePayload(string $received, array $allocations, ?string $key = null): array
    {
        return [
            'received_amount' => $received,
            'idempotency_key' => $key ?? (string) Str::uuid(),
            'allocations' => collect($allocations)->map(fn ($amount, $id) => ['collection_id' => $id, 'amount' => $amount])->values()->all(),
        ];
    }

    /** @param  array<int, string>  $allocations  collection_id => amount (HTTP form shape) */
    protected function settleForm(string $received, array $allocations, ?string $key = null): array
    {
        return [
            'received_amount' => $received,
            'idempotency_key' => $key ?? (string) Str::uuid(),
            'allocations' => collect($allocations)->map(fn ($amount) => ['selected' => '1', 'amount' => $amount])->all(),
        ];
    }

    protected function invoiceFor(GhOrder $order): GhInvoice
    {
        return GhInvoice::query()->where('order_id', $order->id)->sole();
    }

    private function workingOrder(User $driver, User $manager): GhOrder
    {
        $zone = GhZone::factory()->create(['min_order_amount' => '0.00', 'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create(['customer_id' => $customer->id, 'zone_id' => $zone->id]);
        $picked = now('UTC')->subHours(3);

        return GhOrder::factory()->create([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => $picked,
            'scheduled_delivery_at' => $picked,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'pickup_confirmed_by' => $driver->id,
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'created_by' => $manager->id,
        ]);
    }
}
