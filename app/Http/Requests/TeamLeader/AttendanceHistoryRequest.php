<?php

namespace App\Http\Requests\TeamLeader;

use App\Services\Attendance\AttendanceHistoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filters for the attendance history list (T-7.6).
 */
class AttendanceHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isTeamLeader() || $user->canManageOperations());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'employee_id' => $this->filled('employee_id') ? $this->input('employee_id') : null,
            'from' => $this->input('from') ?: now()->startOfMonth()->toDateString(),
            'to' => $this->input('to') ?: now()->toDateString(),
            'kind' => $this->input('kind') ?: 'all',
            'evidence' => $this->input('evidence') ?: 'all',
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'kind' => ['required', Rule::in(AttendanceHistoryService::KINDS)],
            'evidence' => ['required', Rule::in(AttendanceHistoryService::EVIDENCE)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->date('from');
            $to = $this->date('to');
            if ($from === null || $to === null) {
                return;
            }
            if ($from->diffInDays($to) > AttendanceHistoryService::MAX_RANGE_DAYS) {
                $validator->errors()->add('to', __('attendance.history.errors.range'));
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'employee_id' => __('attendance.history.employee'),
            'from' => __('attendance.history.from'),
            'to' => __('attendance.history.to'),
            'kind' => __('attendance.history.kind'),
            'evidence' => __('attendance.history.evidence'),
        ];
    }
}
