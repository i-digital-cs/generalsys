<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossSalesInvoice;
use Illuminate\Foundation\Http\FormRequest;

class GrossAllocationQtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GrossSalesInvoice $invoice */
        $invoice = $this->route('invoice');
        $action = $this->route()->getActionMethod();

        if ($action === 'lossLine') {
            return $this->user()?->can('lossLine', $invoice) ?? false;
        }

        return $this->user()?->can('returnLine', $invoice) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'allocation_id' => ['required', 'integer', 'exists:gross_shipment_allocations,id'],
            'qty_pieces' => ['required', 'integer', 'min:1'],
        ];
    }
}
