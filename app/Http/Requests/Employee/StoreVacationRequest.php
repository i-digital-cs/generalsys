<?php

namespace App\Http\Requests\Employee;

use App\Models\Vacation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVacationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->isEmployee() || $user->isTeamLeader() || $user->isManager()
        );
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('start_date')) {
            $this->merge(['end_date' => $this->input('start_date')]);
        }

        if (! $this->filled('days_requested')) {
            $this->merge(['days_requested' => 1]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in([
                Vacation::TYPE_ANNUAL,
                Vacation::TYPE_SICK,
                Vacation::TYPE_EMERGENCY,
            ])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'same:start_date'],
            'days_requested' => ['required', 'numeric', 'min:0.5', 'max:1'],
            'replace_rest_day_id' => [
                'required',
                'integer',
                Rule::exists('rest_days', 'id')->where(fn ($q) => $q->where('user_id', $this->user()->id)),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type' => __('vacations.fields.type'),
            'start_date' => __('vacations.fields.start_date'),
            'end_date' => __('vacations.fields.end_date'),
            'days_requested' => __('vacations.fields.days_requested'),
            'replace_rest_day_id' => __('vacations.fields.replace_rest_day'),
        ];
    }
}
