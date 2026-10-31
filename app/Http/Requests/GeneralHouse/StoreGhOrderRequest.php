<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhCustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGhOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:gh_customers,id', 'required_without:new_customer.name_ar'],
            'new_customer' => ['nullable', 'array'],
            'new_customer.type' => ['required_with:new_customer.name_ar', Rule::in([GhCustomerType::B2C])],
            'new_customer.name_ar' => ['required_without:customer_id', 'nullable', 'string', 'max:150'],
            'new_customer.name_en' => ['nullable', 'string', 'max:150'],
            'new_customer.phone' => ['nullable', 'string', 'max:40'],
            'new_customer.email' => ['nullable', 'email', 'max:190'],

            'address_id' => ['nullable', 'integer', 'exists:gh_customer_addresses,id', 'required_without:new_address.label'],
            'new_address' => ['nullable', 'array'],
            'new_address.zone_id' => ['required_with:new_address.label', 'nullable', 'integer', 'exists:gh_zones,id'],
            'new_address.label' => ['required_without:address_id', 'nullable', 'string', 'max:80'],
            'new_address.building' => ['nullable', 'string', 'max:120'],
            'new_address.street' => ['nullable', 'string', 'max:190'],
            'new_address.area' => ['nullable', 'string', 'max:120'],
            'new_address.directions' => ['nullable', 'string', 'max:500'],
            'new_address.is_default' => ['nullable', 'boolean'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.offering_id' => ['required', 'integer', 'exists:gh_product_offerings,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            // Reject client price overrides — never accepted.
            'lines.*.unit_price' => ['prohibited'],
            'lines.*.unit_price_estimate' => ['prohibited'],

            'pickup_date' => ['required', 'date_format:Y-m-d'],
            'pickup_window_start' => ['required', 'date_format:H:i'],
            'pickup_window_end' => ['required', 'date_format:H:i'],
            'pickup_driver_id' => ['required', 'integer', 'exists:users,id'],
            'delivery_driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'scheduling_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('new_address.is_default')) {
            $this->merge([
                'new_address' => array_merge($this->input('new_address', []), [
                    'is_default' => $this->boolean('new_address.is_default'),
                ]),
            ]);
        }

        foreach (['pickup_window_start', 'pickup_window_end'] as $timeField) {
            $raw = (string) $this->input($timeField, '');
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $raw)) {
                $this->merge([$timeField => substr($raw, 0, 5)]);
            }
        }

        // Drop empty line rows from the form.
        $lines = $this->input('lines', []);
        if (is_array($lines)) {
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
}
