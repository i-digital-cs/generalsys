<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossCustomer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGrossCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GrossCustomer $customer */
        $customer = $this->route('customer');

        return $this->user()?->can('update', $customer) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['nullable', 'string'],
        ];
    }
}
