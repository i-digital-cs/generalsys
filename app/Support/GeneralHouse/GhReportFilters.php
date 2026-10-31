<?php

namespace App\Support\GeneralHouse;

/**
 * Validated reporting filters (Manager dashboard / reports).
 */
final class GhReportFilters
{
    public function __construct(
        public readonly GhReportPeriod $period,
        public readonly ?string $customerType = null,
        public readonly ?int $customerId = null,
        public readonly ?int $zoneId = null,
        public readonly ?int $driverId = null,
        public readonly ?string $orderStatus = null,
        public readonly ?string $paymentStatus = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        $period = GhReportPeriod::fromInput([
            'preset' => $input['preset'] ?? GhReportPeriod::PRESET_LAST_30,
            'from' => $input['from'] ?? null,
            'to' => $input['to'] ?? null,
        ]);

        $customerType = isset($input['customer_type']) && $input['customer_type'] !== ''
            ? (string) $input['customer_type']
            : null;
        $orderStatus = isset($input['order_status']) && $input['order_status'] !== ''
            ? (string) $input['order_status']
            : null;
        $paymentStatus = isset($input['payment_status']) && $input['payment_status'] !== ''
            ? (string) $input['payment_status']
            : null;

        return new self(
            period: $period,
            customerType: $customerType,
            customerId: isset($input['customer_id']) && $input['customer_id'] !== '' && $input['customer_id'] !== null
                ? (int) $input['customer_id']
                : null,
            zoneId: isset($input['zone_id']) && $input['zone_id'] !== '' && $input['zone_id'] !== null
                ? (int) $input['zone_id']
                : null,
            driverId: isset($input['driver_id']) && $input['driver_id'] !== '' && $input['driver_id'] !== null
                ? (int) $input['driver_id']
                : null,
            orderStatus: $orderStatus,
            paymentStatus: $paymentStatus,
        );
    }

    /** @return array<string, string|int|null> */
    public function toQuery(): array
    {
        return [
            'preset' => $this->period->preset,
            'from' => $this->period->fromDate,
            'to' => $this->period->toDate,
            'customer_type' => $this->customerType,
            'customer_id' => $this->customerId,
            'zone_id' => $this->zoneId,
            'driver_id' => $this->driverId,
            'order_status' => $this->orderStatus,
            'payment_status' => $this->paymentStatus,
        ];
    }
}
