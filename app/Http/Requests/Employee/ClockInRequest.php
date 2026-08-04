<?php

namespace App\Http\Requests\Employee;

use App\Services\Attendance\AttendanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->isEmployee() || $user->isTeamLeader() || $user->isManager()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AttendanceService $attendance */
        $attendance = app(AttendanceService::class);
        $allowed = $attendance->availableShiftTypes($this->user());

        return [
            'shift_type' => ['required', 'string', Rule::in($allowed)],
        ];
    }
}
