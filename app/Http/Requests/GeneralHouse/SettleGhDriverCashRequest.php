<?php

namespace App\Http\Requests\GeneralHouse;

use App\Http\Requests\GeneralHouse\Concerns\CollectsGhCashAllocations;
use App\Support\GeneralHouse\GhCatalogAccess;
use Illuminate\Foundation\Http\FormRequest;

class SettleGhDriverCashRequest extends FormRequest
{
    use CollectsGhCashAllocations;

    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'received_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999.99'],
            'receipt_reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            ...$this->allocationRules(),
        ];
    }

    /** @return array<string, mixed> */
    public function settlementData(): array
    {
        return [
            'received_amount' => (string) $this->validated('received_amount'),
            'receipt_reference' => $this->validated('receipt_reference'),
            'notes' => $this->validated('notes'),
            'idempotency_key' => (string) $this->validated('idempotency_key'),
            'allocations' => $this->selectedAllocations(),
        ];
    }
}
