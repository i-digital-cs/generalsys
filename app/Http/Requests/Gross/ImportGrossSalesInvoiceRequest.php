<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossSalesInvoice;
use Illuminate\Foundation\Http\FormRequest;

class ImportGrossSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GrossSalesInvoice::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:4096',
                'mimes:xlsx,xls,csv',
            ],
        ];
    }
}
