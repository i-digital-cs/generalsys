<?php

namespace App\Http\Requests\GeneralHouse;

use App\Models\GeneralHouse\GhOrder;
use App\Support\GeneralHouse\GhOrderAccess;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmGhPickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GhOrder $order */
        $order = $this->route('order');

        return GhOrderAccess::mayConfirmPickup($this->user(), $order);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'received' => ['required', 'array', 'min:1'],
            'received.*.order_line_id' => ['nullable', 'integer', 'exists:gh_order_lines,id'],
            'received.*.offering_id' => ['required', 'integer', 'exists:gh_product_offerings,id'],
            'received.*.quantity' => ['required', 'integer', 'min:0', 'max:9999'],
            'received.*.notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['prohibited'],
        ];
    }
}
