<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Clock-out with optional multi photos (T-6.6).
 */
class ClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->isEmployee() || $user->isTeamLeader() || $user->isManager() || $user->isAccountant() || $user->isAssistant()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(ClockPhotoRules::rules(), ClockLocationRules::rules());
    }
}
