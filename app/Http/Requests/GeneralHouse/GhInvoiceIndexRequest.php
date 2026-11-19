<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhCashSettlementStatus;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GhInvoiceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(GhInvoiceStatus::ALL)],
            'number' => ['nullable', 'string', 'max:64'],
            'order' => ['nullable', 'string', 'max:64'],
            'customer' => ['nullable', 'string', 'max:100'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'payment_status' => ['nullable', 'string', Rule::in(GhInvoicePaymentStatus::ALL)],
            'settlement_status' => ['nullable', 'string', Rule::in(GhCashSettlementStatus::ALL)],
        ];
    }
}
