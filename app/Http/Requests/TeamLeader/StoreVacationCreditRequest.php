<?php

namespace App\Http\Requests\TeamLeader;

use Illuminate\Foundation\Http\FormRequest;

class StoreVacationCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeamLeader() || $this->user()?->canManageOperations();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount_days' => ['required', 'numeric', 'min:0.5', 'max:365'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'amount_days' => __('vacations.credit.fields.amount_days'),
            'reason' => __('vacations.credit.fields.reason'),
            'year' => __('vacations.credit.fields.year'),
        ];
    }
}
