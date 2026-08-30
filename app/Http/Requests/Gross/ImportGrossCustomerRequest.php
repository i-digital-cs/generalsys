<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossCustomer;
use Illuminate\Foundation\Http\FormRequest;

class ImportGrossCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GrossCustomer::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:2048',
                'mimes:xlsx,xls,csv',
            ],
        ];
    }
}
