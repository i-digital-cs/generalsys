<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossStockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustGrossStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjustStock', GrossItem::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', 'exists:gross_items,id'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'qty' => ['required', 'integer', 'min:1'],
            'unit' => ['required', Rule::in(GrossStockMovement::UNITS)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
