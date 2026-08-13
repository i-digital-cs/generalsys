<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'default_language' => ['required', Rule::in(['ar', 'en'])],
            'default_currency' => ['required', Rule::in(['EGP', 'AED'])],
            'multipliers' => ['required', 'array'],
            'multipliers.friday_full_time' => ['required', 'numeric', 'min:0', 'max:10'],
            'multipliers.friday_online' => ['required', 'numeric', 'min:0', 'max:10'],
            'multipliers.day_off_full_time' => ['required', 'numeric', 'min:0', 'max:10'],
            'multipliers.day_off_online' => ['required', 'numeric', 'min:0', 'max:10'],
            'multipliers.overtime' => ['required', 'numeric', 'min:0', 'max:10'],
            'multipliers.official_holiday_work' => ['required', 'numeric', 'min:0', 'max:10'],
            'multipliers.extra_pay' => ['required', 'numeric', 'min:0', 'max:10'],
            'office_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'office_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'office_radius_meters' => ['nullable', 'integer', 'min:50', 'max:50000'],
        ];
    }
}
