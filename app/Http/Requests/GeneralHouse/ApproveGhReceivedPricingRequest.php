<?php

namespace App\Http\Requests\GeneralHouse;

use App\Models\GeneralHouse\GhOrder;
use App\Support\GeneralHouse\GhOrderAccess;
use Illuminate\Foundation\Http\FormRequest;

class ApproveGhReceivedPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GhOrder $order */
        $order = $this->route('order');

        return GhOrderAccess::mayManage($this->user()) && $order instanceof GhOrder;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'offering_id' => ['nullable', 'integer', 'exists:gh_product_offerings,id'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
