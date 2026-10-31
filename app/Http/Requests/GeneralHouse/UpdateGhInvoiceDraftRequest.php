<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhDiscountType;
use App\Support\GeneralHouse\GhMinOrderOverrideType;
use App\Support\GeneralHouse\GhTaxClass;
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
            'min_order_override_type' => ['required', Rule::in(GhMinOrderOverrideType::ALL)],
            'min_order_override_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_reason' => ['nullable', 'string', 'max:500'],
            'min_order_tax_class' => ['nullable', Rule::in(GhTaxClass::ALL)],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.offering_id' => ['required', 'integer', 'exists:gh_product_offerings,id'],
            'lines.*.received_line_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.quantity_received' => ['nullable', 'integer', 'min:0'],
            'lines.*.item_discount_type' => ['nullable', Rule::in(GhDiscountType::ALL)],
            'lines.*.item_discount_value' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
