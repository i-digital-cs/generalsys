<?php

namespace App\Http\Requests\GeneralHouse;

use App\Http\Requests\GeneralHouse\Concerns\CollectsGhCashAllocations;
use App\Support\GeneralHouse\GhCatalogAccess;
use Illuminate\Foundation\Http\FormRequest;

class AllocateGhDriverCashSettlementRequest extends FormRequest
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
            'idempotency_key' => ['required', 'string', 'max:64'],
            ...$this->allocationRules(),
        ];
    }

    /** @return array{allocations: list<array{collection_id: int, amount: string}>, idempotency_key: string} */
    public function allocationData(): array
    {
        return [
            'allocations' => $this->selectedAllocations(),
            'idempotency_key' => (string) $this->validated('idempotency_key'),
        ];
    }
}
