<?php

namespace App\Http\Requests\GeneralHouse;

use App\Models\GeneralHouse\GhOrder;
use App\Support\GeneralHouse\GhOrderAccess;
use Illuminate\Foundation\Http\FormRequest;

class SyncGhReceivedLinesRequest extends FormRequest
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
            'received' => ['nullable', 'array'],
            'received.*.order_line_id' => ['nullable', 'integer', 'exists:gh_order_lines,id'],
            'received.*.offering_id' => ['required', 'integer', 'exists:gh_product_offerings,id'],
            'received.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'received.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
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
            $qty = (int) ($row['quantity'] ?? 0);
            if ($offeringId < 1 || $qty < 1) {
                continue;
            }
            $filtered[] = [
                'order_line_id' => $row['order_line_id'] ?? null,
                'offering_id' => $offeringId,
                'quantity' => $qty,
                'notes' => $row['notes'] ?? null,
            ];
        }
        $this->merge(['received' => $filtered]);
    }
}
