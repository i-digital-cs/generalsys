<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GhReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', Rule::in(GhReportPeriod::PRESETS)],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom', 'after_or_equal:from'],
            'customer_type' => ['nullable', 'string', Rule::in(GhCustomerType::ALL)],
            'customer_id' => ['nullable', 'integer', 'exists:gh_customers,id'],
            'zone_id' => ['nullable', 'integer', 'exists:gh_zones,id'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'order_status' => ['nullable', 'string', Rule::in(GhOrderStatus::ALL)],
            'payment_status' => ['nullable', 'string', Rule::in([
                GhInvoicePaymentStatus::UNPAID,
                GhInvoicePaymentStatus::PARTIAL,
                GhInvoicePaymentStatus::PAID,
            ])],
        ];
    }
}
