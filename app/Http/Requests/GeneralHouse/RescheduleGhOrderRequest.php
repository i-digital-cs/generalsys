<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhOrderAccess;
use Illuminate\Foundation\Http\FormRequest;

class RescheduleGhOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhOrderAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pickup_date' => ['required', 'date_format:Y-m-d'],
            'pickup_window_start' => ['required', 'date_format:H:i'],
            'pickup_window_end' => ['required', 'date_format:H:i'],
            'scheduling_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['pickup_window_start', 'pickup_window_end'] as $timeField) {
            $raw = (string) $this->input($timeField, '');
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $raw)) {
                $this->merge([$timeField => substr($raw, 0, 5)]);
            }
        }
    }
}
