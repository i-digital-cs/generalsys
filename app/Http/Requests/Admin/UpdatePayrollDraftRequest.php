<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payroll = $this->route('payroll');

        return $this->user()?->can('update', $payroll) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_time_days' => ['required', 'integer', 'min:0', 'max:31'],
            'online_half_days' => ['required', 'integer', 'min:0', 'max:31'],
            'online_full_days' => ['required', 'integer', 'min:0', 'max:31'],
            'rest_days' => ['required', 'integer', 'min:0', 'max:31'],
            'overtime_hours' => ['required', 'numeric', 'min:0', 'max:744'],
            'overtime_multiplier' => ['required', 'numeric', 'min:0', 'max:10'],
            'day_off_work_days' => ['required', 'integer', 'min:0', 'max:31'],
            'official_holiday_work_days' => ['required', 'integer', 'min:0', 'max:31'],
            'hours_deduction' => ['required', 'numeric', 'min:0', 'max:744'],
            'excused_absence_days' => ['required', 'integer', 'min:0', 'max:31'],
            'unexcused_absence_days' => ['required', 'integer', 'min:0', 'max:31'],
            'late_minutes_total' => ['required', 'integer', 'min:0', 'max:44640'],
            'extra_days' => ['required', 'integer', 'min:0', 'max:31'],
            'extra_pay' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
