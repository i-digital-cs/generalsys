<?php

namespace App\Http\Requests\GeneralHouse;

use Illuminate\Foundation\Http\FormRequest;

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
            'status' => ['prohibited'],
        ];
    }
}
