<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGhOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:gh_products,id'],
            'treatment_id' => [
                'required',
                'integer',
                'exists:gh_treatments,id',
                Rule::unique('gh_product_offerings', 'treatment_id')
                    ->where(fn ($q) => $q->where('product_id', (int) $this->input('product_id'))),
            ],
            'standard_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'currency' => ['nullable', 'in:AED'],
            // Optional on create — defaults to configured STANDARD_RATED for routine laundry entry.
            'tax_class' => ['nullable', Rule::in(GhTaxClass::ALL)],
            'status' => ['required', Rule::in(GhCatalogStatus::ALL)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $default = (string) config('general_house.default_offering_tax_class', GhTaxClass::STANDARD_RATED);
        if (! in_array($default, GhTaxClass::ALL, true)) {
            $default = GhTaxClass::STANDARD_RATED;
        }
        $tax = trim((string) $this->input('tax_class', ''));
        $this->merge([
            'tax_class' => $tax !== '' ? $tax : $default,
        ]);
    }
}
