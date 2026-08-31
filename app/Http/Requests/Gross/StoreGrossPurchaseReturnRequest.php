<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossPurchaseInvoice;
use Illuminate\Foundation\Http\FormRequest;

class StoreGrossPurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GrossPurchaseInvoice $purchase */
        $purchase = $this->route('purchase');

        return $this->user()?->can('returnLines', $purchase) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines', []);
        if (! is_array($lines)) {
            return;
        }

        $normalized = [];
        foreach ($lines as $row) {
            if (! is_array($row)) {
                continue;
            }
            $qty = (int) ($row['qty'] ?? 0);
            if ($qty < 1) {
                continue;
            }
            $normalized[] = [
                'purchase_invoice_line_id' => (int) ($row['purchase_invoice_line_id'] ?? 0),
                'qty' => $qty,
            ];
        }

        $this->merge(['lines' => $normalized]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.purchase_invoice_line_id' => [
                'required',
                'integer',
                'exists:gross_purchase_invoice_lines,id',
            ],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
