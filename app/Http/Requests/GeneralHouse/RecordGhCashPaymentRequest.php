<?php

namespace App\Http\Requests\GeneralHouse;

use Illuminate\Foundation\Http\FormRequest;

class RecordGhCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'collector_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
