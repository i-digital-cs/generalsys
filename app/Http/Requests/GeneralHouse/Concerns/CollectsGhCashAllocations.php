<?php

namespace App\Http\Requests\GeneralHouse\Concerns;

/**
 * Form shape: allocations[<collection_id>][selected|amount]. Only rows the manager ticked are submitted to the service.
 */
trait CollectsGhCashAllocations
{
    /** @return array<string, mixed> */
    protected function allocationRules(): array
    {
        return [
            'allocations' => ['required', 'array', 'max:200'],
            'allocations.*.selected' => ['nullable', 'boolean'],
            'allocations.*.amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
        ];
    }

    /** @return list<array{collection_id: int, amount: string}> */
    public function selectedAllocations(): array
    {
        $rows = [];
        foreach ((array) $this->validated('allocations', []) as $collectionId => $row) {
            if (! filter_var($row['selected'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            $rows[] = ['collection_id' => (int) $collectionId, 'amount' => (string) ($row['amount'] ?? '0')];
        }

        return $rows;
    }
}
