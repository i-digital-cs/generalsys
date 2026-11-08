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
            // Package pickup: received garment lines are optional (staff counts during WORKING).
            // Legacy payloads with received[] remain accepted for compatibility.
            'received' => ['sometimes', 'nullable', 'array'],
            'received.*.order_line_id' => ['nullable', 'integer', 'exists:gh_order_lines,id'],
            'received.*.offering_id' => ['required_with:received', 'integer', 'exists:gh_product_offerings,id'],
            'received.*.quantity' => ['required_with:received', 'integer', 'min:0', 'max:9999'],
            'received.*.notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('received')) {
            $this->merge(['received' => []]);

            return;
        }

        $received = $this->input('received', []);
        if (! is_array($received)) {
            $this->merge(['received' => []]);

            return;
        }

        $filtered = [];
        foreach ($received as $row) {
            if (! is_array($row)) {
                continue;
            }
            $offeringId = (int) ($row['offering_id'] ?? 0);
            if ($offeringId < 1) {
                continue;
            }
            $filtered[] = [
                'order_line_id' => $row['order_line_id'] ?? null,
                'offering_id' => $offeringId,
                'quantity' => (int) ($row['quantity'] ?? 0),
                'notes' => $row['notes'] ?? null,
            ];
        }
        $this->merge(['received' => $filtered]);
    }
}
