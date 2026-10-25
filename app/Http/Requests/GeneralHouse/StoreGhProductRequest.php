<?php

namespace App\Http\Requests\GeneralHouse;

use App\Services\GeneralHouse\Catalog\GhProductService;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGhProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:gh_service_categories,id'],
            'code' => ['required', 'string', 'max:40', 'alpha_dash:ascii', 'unique:gh_products,code'],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'status' => ['required', Rule::in(GhCatalogStatus::ALL)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'image' => ['nullable', 'file', 'max:'.GhProductService::IMAGE_MAX_KB, 'mimes:'.implode(',', GhProductService::IMAGE_MIMES)],
        ];
    }
}
