<?php

namespace App\Http\Requests\GeneralHouse;

use App\Models\GeneralHouse\GhOrder;
use App\Support\GeneralHouse\GhCatalogAccess;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGhOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var GhOrder|null $order */
        $order = $this->route('order');
        $fullEdit = $order instanceof GhOrder && $order->isEditableOperationally();

        if (! $fullEdit) {
            return [
                'notes' => ['nullable', 'string', 'max:5000'],
                'scheduling_notes' => ['nullable', 'string', 'max:2000'],
                'delivery_driver_id' => ['nullable', 'integer', 'exists:users,id'],
                'scheduled_delivery_at' => ['nullable', 'date'],
                'scheduled_delivery_date' => ['nullable', 'date_format:Y-m-d'],
                'scheduled_delivery_time' => ['nullable', 'date_format:H:i'],
                'lines' => ['prohibited'],
                'scheduled_pickup_at' => ['prohibited'],
                'pickup_date' => ['prohibited'],
                'pickup_window_start' => ['prohibited'],
                'pickup_window_end' => ['prohibited'],
                'pickup_driver_id' => ['prohibited'],
                'address_id' => ['prohibited'],
                'customer_id' => ['prohibited'],
                'status' => ['prohibited'],
                'lines.*.unit_price' => ['prohibited'],
            ];
        }

        return [
            'address_id' => ['nullable', 'integer', 'exists:gh_customer_addresses,id'],
            'new_address' => ['nullable', 'array'],
            'new_address.zone_id' => ['required_with:new_address.label', 'nullable', 'integer', 'exists:gh_zones,id'],
            'new_address.label' => ['nullable', 'string', 'max:80'],
            'new_address.building' => ['nullable', 'string', 'max:120'],
            'new_address.street' => ['nullable', 'string', 'max:190'],
            'new_address.area' => ['nullable', 'string', 'max:120'],
            'new_address.directions' => ['nullable', 'string', 'max:500'],
            'new_address.is_default' => ['nullable', 'boolean'],

            // Operational edit may clear estimate lines; pickup records actuals.
            'lines' => ['nullable', 'array'],
            'lines.*.offering_id' => ['required', 'integer', 'exists:gh_product_offerings,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'lines.*.unit_price' => ['prohibited'],
            'lines.*.unit_price_estimate' => ['prohibited'],

            'scheduled_pickup_at' => ['nullable', 'required_without_all:pickup_date,pickup_window_start', 'date'],
            'pickup_date' => ['nullable', 'required_without:scheduled_pickup_at', 'date_format:Y-m-d'],
            'pickup_window_start' => ['nullable', 'required_without:scheduled_pickup_at', 'date_format:H:i'],
            'pickup_window_end' => ['nullable', 'date_format:H:i'],
            // Full pre-pickup edit keeps delivery appointment mandatory.
            'scheduled_delivery_at' => ['nullable', 'required_without_all:scheduled_delivery_date,scheduled_delivery_time', 'date'],
            'scheduled_delivery_date' => ['nullable', 'required_without:scheduled_delivery_at', 'required_with:scheduled_delivery_time', 'date_format:Y-m-d'],
            'scheduled_delivery_time' => ['nullable', 'required_without:scheduled_delivery_at', 'required_with:scheduled_delivery_date', 'date_format:H:i'],
            'pickup_driver_id' => ['required', 'integer', 'exists:users,id'],
            'delivery_driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'scheduling_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
            'customer_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $msg = __('gh.errors.delivery_schedule_required_on_create');

        return [
            'scheduled_delivery_at.required_without_all' => $msg,
            'scheduled_delivery_date.required_without' => $msg,
            'scheduled_delivery_date.required_with' => $msg,
            'scheduled_delivery_time.required_without' => $msg,
            'scheduled_delivery_time.required_with' => $msg,
        ];
    }

    protected function prepareForValidation(): void
    {
        $scheduled = trim((string) $this->input('scheduled_pickup_at', ''));
        if ($scheduled !== '') {
            $scheduled = str_replace('T', ' ', $scheduled);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $scheduled)) {
                $this->merge(['scheduled_pickup_at' => substr($scheduled, 0, 16)]);
            }
        }

        $deliveryAt = trim((string) $this->input('scheduled_delivery_at', ''));
        if ($deliveryAt !== '') {
            $deliveryAt = str_replace('T', ' ', $deliveryAt);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $deliveryAt)) {
                $this->merge(['scheduled_delivery_at' => substr($deliveryAt, 0, 16)]);
            }
        }

        foreach (['pickup_window_start', 'pickup_window_end', 'scheduled_delivery_time'] as $timeField) {
            $raw = (string) $this->input($timeField, '');
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $raw)) {
                $this->merge([$timeField => substr($raw, 0, 5)]);
            }
        }

        $lines = $this->input('lines');
        if (! is_array($lines)) {
            return;
        }

        $filtered = [];
        foreach ($lines as $row) {
            if (! is_array($row)) {
                continue;
            }
            $oid = (int) ($row['offering_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);
            if ($oid > 0 && $qty > 0) {
                $filtered[] = [
                    'offering_id' => $oid,
                    'quantity' => $qty,
                ];
            }
        }
        $this->merge(['lines' => $filtered]);
    }
}
