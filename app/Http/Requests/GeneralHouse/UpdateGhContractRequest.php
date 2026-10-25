<?php

namespace App\Http\Requests\GeneralHouse;

use App\Models\GeneralHouse\GhB2bContract;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhContractStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGhContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var GhB2bContract $contract */
        $contract = $this->route('contract');

        return [
            'customer_id' => ['required', 'integer', 'exists:gh_customers,id'],
            'reference' => ['required', 'string', 'max:60', Rule::unique('gh_b2b_contracts', 'reference')->ignore($contract->id)],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', Rule::in(GhContractStatus::ALL)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
