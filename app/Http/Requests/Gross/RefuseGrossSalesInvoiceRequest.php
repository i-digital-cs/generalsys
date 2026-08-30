<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossSalesInvoice;
use Illuminate\Foundation\Http\FormRequest;

class RefuseGrossSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GrossSalesInvoice $invoice */
        $invoice = $this->route('invoice');

        return $this->user()?->can('refuse', $invoice) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'refuse_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
