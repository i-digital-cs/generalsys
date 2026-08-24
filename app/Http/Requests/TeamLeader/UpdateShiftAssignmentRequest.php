<?php

namespace App\Http\Requests\TeamLeader;

use App\Models\ShiftAssignment;
use App\Services\Attendance\ShiftAssignmentService;
use App\Services\Attendance\ShiftWindowValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ShiftAssignment $assignment */
        $assignment = $this->route('shiftAssignment');

        return $this->user()?->can('update', $assignment) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = app(ShiftAssignmentService::class);
        $employeeIds = $service->assignableEmployees($this->user())->pluck('id')->all();

        return [
            'user_id' => ['required', 'integer', Rule::in($employeeIds)],
            'date' => ['required', 'date'],
            'shift_type' => [
                'required',
                'string',
                Rule::in([
                    ShiftAssignment::TYPE_FULL_TIME,
                    ShiftAssignment::TYPE_ONLINE_HALF,
                    ShiftAssignment::TYPE_ONLINE_FULL,
                    ShiftAssignment::TYPE_EXTRA,
                    ShiftAssignment::TYPE_OVERTIME,
                ]),
            ],
            'expected_start_time' => ['required', 'date_format:H:i'],
            'expected_end_time' => ['required', 'date_format:H:i'],
            'expected_hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'multiplier' => ['nullable', 'numeric', 'min:0.5', 'max:5'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = (string) $this->input('shift_type', '');
            $start = (string) $this->input('expected_start_time', '');
            $end = (string) $this->input('expected_end_time', '');
            $hours = $this->input('expected_hours');

            if ($type === '' || $start === '' || $end === '' || $hours === null || $hours === '') {
                return;
            }

            $windows = app(ShiftWindowValidator::class);
            $planned = (float) $hours;
            if (! $windows->isCompatible($type, $start, $end, $planned)) {
                $validator->errors()->add(
                    'expected_end_time',
                    $windows->errorMessage($type, $start, $end, $planned),
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => __('shifts.employee'),
            'date' => __('shifts.date'),
            'shift_type' => __('shifts.shift_type'),
            'expected_start_time' => __('shifts.start_time'),
            'expected_end_time' => __('shifts.end_time'),
            'expected_hours' => __('shifts.expected_hours'),
            'multiplier' => __('shifts.multiplier'),
            'notes' => __('shifts.notes'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('multiplier') && $this->filled('shift_type')) {
            $type = $this->input('shift_type');
            $this->merge([
                'multiplier' => in_array($type, ShiftAssignment::PREMIUM_TYPES, true) ? 1.5 : 1.0,
            ]);
        }
    }
}
