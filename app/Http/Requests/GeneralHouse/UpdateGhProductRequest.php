<?php

namespace App\Http\Requests\GeneralHouse;

use App\Http\Requests\GeneralHouse\Concerns\ValidatesGhProductOfferings;
use App\Models\GeneralHouse\GhProduct;
use App\Services\GeneralHouse\Catalog\GhProductService;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGhProductRequest extends FormRequest
{
    use ValidatesGhProductOfferings;

    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    protected function prepareForValidation(): void
    {
        $offerings = $this->input('offerings');
        if (is_array($offerings)) {
            $normalized = [];
            foreach ($offerings as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $row['enabled'] = filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    || ($row['enabled'] ?? null) === 1
                    || ($row['enabled'] ?? null) === '1';
                $normalized[] = $row;
            }
            $this->merge(['offerings' => $normalized]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var GhProduct $product */
        $product = $this->route('product');

        return array_merge([
            'category_id' => ['required', 'integer', 'exists:gh_service_categories,id'],
            'code' => [
                'required',
                'string',
                'max:40',
                'alpha_dash:ascii',
                Rule::unique('gh_products', 'code')->ignore($product->id),
            ],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'status' => ['required', Rule::in(GhCatalogStatus::ALL)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'image' => ['nullable', 'file', 'max:'.GhProductService::IMAGE_MAX_KB, 'mimes:'.implode(',', GhProductService::IMAGE_MIMES)],
            'remove_image' => ['nullable', 'boolean'],
        ], $this->offeringRules());
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateEnabledOfferingPrices($validator);
    }
}
