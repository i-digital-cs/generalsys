<?php

namespace App\Http\Requests\GeneralHouse;

use App\Services\GeneralHouse\Payments\GhDriverCashSettlementService;
use App\Support\GeneralHouse\GhCatalogAccess;
use Illuminate\Foundation\Http\FormRequest;

class ReverseGhDriverCashSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:'.GhDriverCashSettlementService::REASON_MAX_LENGTH],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => __('gh.errors.cash_settlement_reversal_reason_required'),
        ];
    }
}
