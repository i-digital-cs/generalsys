<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhAttemptReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordGhAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reason_code' => ['nullable', 'string', Rule::in(array_values(array_unique(array_merge(
                GhAttemptReason::PICKUP,
                GhAttemptReason::DELIVERY,
            ))))],
            'status' => ['prohibited'],
        ];
    }
}
