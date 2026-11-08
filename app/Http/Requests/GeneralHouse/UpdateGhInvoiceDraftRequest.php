<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhDiscountType;
use App\Support\GeneralHouse\GhMinOrderOverrideType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGhInvoiceDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'draft_version' => ['required', 'integer', 'min:1'],
            'invoice_discount_type' => ['required', Rule::in(GhDiscountType::ALL)],
            'invoice_discount_value' => ['nullable', 'numeric', 'min:0'],
            'min_order_effective_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_override_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_override_type' => ['nullable', Rule::in(GhMinOrderOverrideType::ALL)],
            'min_order_reason' => ['nullable', 'string', 'max:500'],
            'lines' => ['nullable', 'array'],
            'lines.*.offering_id' => ['required', 'integer', 'exists:gh_product_offerings,id'],
            'lines.*.received_line_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.quantity_received' => ['nullable', 'integer', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.out_of_contract' => ['sometimes', 'boolean'],
            'lines.*.approve_price' => ['sometimes', 'boolean'],
            'lines.*.item_discount_type' => ['nullable', Rule::in(GhDiscountType::ALL)],
            'lines.*.item_discount_value' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines', []);
        if (! is_array($lines)) {
            $lines = [];
        }
        foreach ($lines as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (array_key_exists('out_of_contract', $row)) {
                $lines[$i]['out_of_contract'] = filter_var($row['out_of_contract'], FILTER_VALIDATE_BOOLEAN);
            }
            if (array_key_exists('approve_price', $row)) {
                $lines[$i]['approve_price'] = filter_var($row['approve_price'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $merge = ['lines' => array_values($lines)];
        if ($this->has('min_order_effective_amount') && ! $this->has('min_order_override_amount')) {
            $merge['min_order_override_amount'] = $this->input('min_order_effective_amount');
        }

        $this->merge($merge);
    }
}
