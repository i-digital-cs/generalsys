<?php

namespace App\Http\Requests\GeneralHouse;

use App\Models\GeneralHouse\GhProductOffering;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGhOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var GhProductOffering $offering */
        $offering = $this->route('offering');

        return [
            'product_id' => ['required', 'integer', 'exists:gh_products,id'],
            'treatment_id' => [
                'required',
                'integer',
                'exists:gh_treatments,id',
                Rule::unique('gh_product_offerings', 'treatment_id')
                    ->where(fn ($q) => $q->where('product_id', (int) $this->input('product_id')))
                    ->ignore($offering->id),
            ],
            'standard_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'currency' => ['nullable', 'in:AED'],
            'tax_class' => ['required', Rule::in(GhTaxClass::ALL)],
            'status' => ['required', Rule::in(GhCatalogStatus::ALL)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }
}
