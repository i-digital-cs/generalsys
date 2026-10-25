<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGhContractOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contract_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'currency' => ['nullable', 'in:AED'],
            'status' => ['required', Rule::in(GhCatalogStatus::ALL)],
        ];
    }
}
