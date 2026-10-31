<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhAssignmentResponsibility;
use App\Support\GeneralHouse\GhOrderAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignGhDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhOrderAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'responsibility' => ['required', Rule::in(GhAssignmentResponsibility::ALL)],
            'driver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['prohibited'],
        ];
    }
}
