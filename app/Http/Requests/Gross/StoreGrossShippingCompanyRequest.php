<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossShippingCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrossShippingCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GrossShippingCompany::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in([
                GrossShippingCompany::STATUS_ACTIVE,
                GrossShippingCompany::STATUS_INACTIVE,
            ])],
        ];
    }
}
