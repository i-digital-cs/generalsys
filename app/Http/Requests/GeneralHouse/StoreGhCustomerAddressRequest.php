<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreGhCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', 'exists:gh_zones,id'],
            'label' => ['required', 'string', 'max:80'],
            'building' => ['nullable', 'string', 'max:120'],
            'street' => ['nullable', 'string', 'max:190'],
            'area' => ['nullable', 'string', 'max:120'],
            'directions' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_default')) {
            $this->merge(['is_default' => $this->boolean('is_default')]);
        }
    }
}
