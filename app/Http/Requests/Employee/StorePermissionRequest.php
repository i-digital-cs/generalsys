<?php

namespace App\Http\Requests\Employee;

use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
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
        return [
            'type' => ['required', 'string', Rule::in([
                Permission::TYPE_LEAVE,
                Permission::TYPE_LATE,
            ])],
            'request_date' => ['required', 'date'],
            'from_time' => ['required', 'date_format:H:i'],
            'to_time' => ['required', 'date_format:H:i', 'after:from_time'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('permissions.fields.type'),
            'request_date' => __('permissions.fields.request_date'),
            'from_time' => __('permissions.fields.from_time'),
            'to_time' => __('permissions.fields.to_time'),
            'reason' => __('permissions.fields.reason'),
        ];
    }
}
