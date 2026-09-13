<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossPurchaseInvoice;
use Illuminate\Foundation\Http\FormRequest;

class ImportGrossPurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GrossPurchaseInvoice::class) ?? false;
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
