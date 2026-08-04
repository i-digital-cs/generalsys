<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreRestDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User $employee */
        $employee = $this->route('employee');

        return [
            'date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($employee) {
                    if ($employee->restDays()->whereDate('date', $value)->exists()) {
                        $fail(__('employees.rest_days.errors.duplicate'));
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'date' => __('employees.rest_days.fields.date'),
        ];
    }
}
