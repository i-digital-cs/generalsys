<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossSalesInvoice;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalesInvoiceMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GrossSalesInvoice $invoice */
        $invoice = $this->route('invoice');

        return $this->user()?->can('manageMedia', $invoice) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,webp',
            ],
            'title' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
