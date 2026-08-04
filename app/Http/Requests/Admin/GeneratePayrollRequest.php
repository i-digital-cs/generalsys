<?php

namespace App\Http\Requests\Admin;

use App\Models\PayrollRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('generate', PayrollRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'team_leader_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'TEAM_LEADER')],
        ];
    }
}
