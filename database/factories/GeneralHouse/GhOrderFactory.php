<?php

namespace Database\Factories\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<GhOrder> */
class GhOrderFactory extends Factory
{
    protected $model = GhOrder::class;

    public function definition(): array
    {
        $tz = config('general_house.timezone', 'Asia/Dubai');
        $starts = Carbon::now($tz)->addDays(2)->setTime(10, 0);
        $ends = $starts->copy()->setTime(12, 0);
        $customer = GhCustomer::factory();
        $zone = GhZone::factory();

        return [
            'reference' => 'GH-ORD-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'customer_id' => $customer,
            'address_id' => GhCustomerAddress::factory()->state(fn (array $attrs) => [
                'customer_id' => $attrs['customer_id'] ?? GhCustomer::factory(),
                'zone_id' => $attrs['zone_id'] ?? GhZone::factory(),
            ]),
            'zone_id' => $zone,
            'contract_id' => null,
            'status' => GhOrderStatus::LATER,
            'pickup_starts_at' => $starts,
            'pickup_ends_at' => $ends,
            'timezone' => $tz,
            'notes' => null,
            'scheduling_notes' => null,
            'pickup_driver_id' => null,
            'delivery_driver_id' => null,
            'estimated_subtotal' => '0.00',
            'currency' => 'AED',
            'picked_up_at' => null,
            'invoice_issued_at' => null,
            'created_by' => User::factory()->manager(),
            'updated_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (GhOrder $order): void {
            if ($order->address_id) {
                $address = GhCustomerAddress::query()->find($order->address_id);
                if ($address !== null) {
                    $order->customer_id = $address->customer_id;
                    $order->zone_id = $address->zone_id;
                }
            }
        })->afterCreating(function (GhOrder $order): void {
            $address = $order->address()->first();
            if ($address !== null
                && ((int) $order->customer_id !== (int) $address->customer_id
                    || (int) $order->zone_id !== (int) $address->zone_id)) {
                $order->forceFill([
                    'customer_id' => $address->customer_id,
                    'zone_id' => $address->zone_id,
                ])->saveQuietly();
            }
        });
    }
}
