<?php

namespace App\Http\Requests\Employee;

use App\Models\Permission;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->isEmployee() || $user->isSales() || $user->isTeamLeader() || $user->isManager() || $user->isAccountant() || $user->isAssistant()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isAbsence = $this->input('type') === Permission::TYPE_ABSENCE;

        return [
            'type' => ['required', 'string', Rule::in([
                Permission::TYPE_LEAVE,
                Permission::TYPE_LATE,
                Permission::TYPE_ABSENCE,
            ])],
            'request_date' => ['required', 'date'],
            'end_date' => [
                $isAbsence ? 'required' : 'nullable',
                'date',
                $isAbsence ? 'after_or_equal:request_date' : 'nullable',
            ],
            'from_time' => [$isAbsence ? 'nullable' : 'required', 'date_format:H:i'],
            'to_time' => array_values(array_filter([
                $isAbsence ? 'nullable' : 'required',
                'date_format:H:i',
                $isAbsence ? null : 'after:from_time',
            ])),
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('type') !== Permission::TYPE_ABSENCE) {
                $this->merge(['end_date' => $this->input('request_date')]);

                return;
            }

            $this->merge([
                'from_time' => null,
                'to_time' => null,
            ]);

            $start = $this->input('request_date');
            $end = $this->input('end_date');
            if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
                return;
            }

            $days = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
            if ($days > 31) {
                $validator->errors()->add('end_date', __('permissions.errors.absence_period_too_long'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('permissions.fields.type'),
            'request_date' => __('permissions.fields.request_date'),
            'end_date' => __('permissions.fields.end_date'),
            'from_time' => __('permissions.fields.from_time'),
            'to_time' => __('permissions.fields.to_time'),
            'reason' => __('permissions.fields.reason'),
        ];
    }
}
