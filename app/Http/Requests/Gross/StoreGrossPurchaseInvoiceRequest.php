<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossPurchaseInvoiceLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrossPurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GrossPurchaseInvoice::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedDiscounts());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer', 'exists:gross_suppliers,id'],
            'supplier_name' => ['required', 'string', 'max:191'],
            'supplier_phone' => ['required', 'string', 'max:40'],
            'supplier_address' => ['nullable', 'string'],
            'currency' => ['required', Rule::in(['EGP', 'AED'])],
            'discount_mode' => ['nullable', Rule::in(['amount', 'percent'])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => ['required', 'integer', 'exists:gross_items,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.unit' => ['required', Rule::in([
                GrossPurchaseInvoiceLine::UNIT_PIECE,
                GrossPurchaseInvoiceLine::UNIT_CARTON,
            ])],
            'lines.*.buy_price' => ['required', 'numeric', 'min:0'],
            'lines.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_mode' => ['nullable', Rule::in(['amount', 'percent'])],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    private function normalizedDiscounts(): array
    {
        $mode = $this->input('discount_mode', 'amount');
        $out = [
            'discount_mode' => $mode,
            'discount_amount' => $mode === 'amount' ? $this->input('discount_amount', 0) : 0,
            'discount_percent' => $mode === 'percent' ? $this->input('discount_percent', 0) : 0,
        ];

        $lines = $this->input('lines', []);
        if (is_array($lines)) {
            foreach ($lines as $i => $line) {
                if (! is_array($line)) {
                    continue;
                }
                $lineMode = $line['discount_mode'] ?? 'amount';
                $lines[$i]['discount_mode'] = $lineMode;
                $lines[$i]['discount_amount'] = $lineMode === 'amount' ? ($line['discount_amount'] ?? 0) : 0;
                $lines[$i]['discount_percent'] = $lineMode === 'percent' ? ($line['discount_percent'] ?? 0) : 0;
            }
            $out['lines'] = $lines;
        }

        return $out;
    }
}
