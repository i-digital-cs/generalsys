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
            'scheduled_pickup_at' => ['nullable', 'required_without_all:pickup_date,pickup_window_start', 'date'],
            'pickup_date' => ['nullable', 'required_without:scheduled_pickup_at', 'date_format:Y-m-d'],
            'pickup_window_start' => ['nullable', 'required_without:scheduled_pickup_at', 'date_format:H:i'],
            'pickup_window_end' => ['nullable', 'date_format:H:i'],
            'scheduling_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $scheduled = trim((string) $this->input('scheduled_pickup_at', ''));
        if ($scheduled !== '') {
            $scheduled = str_replace('T', ' ', $scheduled);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $scheduled)) {
                $this->merge(['scheduled_pickup_at' => substr($scheduled, 0, 16)]);
            }
        }

        foreach (['pickup_window_start', 'pickup_window_end'] as $timeField) {
            $raw = (string) $this->input($timeField, '');
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $raw)) {
                $this->merge([$timeField => substr($raw, 0, 5)]);
            }
        }
    }
}
