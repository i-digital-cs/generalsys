<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGhCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(GhCustomerType::ALL)],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'billing_name' => ['nullable', 'string', 'max:190'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'tax_registration_number' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(GhCatalogStatus::ALL)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
