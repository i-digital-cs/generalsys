<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrossSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GrossSalesInvoice $invoice */
        $invoice = $this->route('invoice');

        return $this->user()?->can('update', $invoice) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = $this->normalizedDiscounts();
        if ($this->input('sales_user_id') === '' || $this->input('sales_user_id') === null) {
            $merge['sales_user_id'] = null;
        }
        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:gross_customers,id'],
            'customer_name' => ['required', 'string', 'max:191'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'customer_address' => ['nullable', 'string'],
            'shipping_company_id' => ['nullable', 'integer', 'exists:gross_shipping_companies,id'],
            'shipping_company_name' => ['nullable', 'string', 'max:191'],
            'sales_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'currency' => ['required', Rule::in(['EGP', 'AED'])],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'discount_mode' => ['nullable', Rule::in(['amount', 'percent'])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => ['required', 'integer', 'exists:gross_items,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.unit' => ['required', Rule::in([
                GrossSalesInvoiceLine::UNIT_PIECE,
                GrossSalesInvoiceLine::UNIT_CARTON,
            ])],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
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
