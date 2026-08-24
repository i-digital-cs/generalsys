<?php

namespace App\Http\Requests\Admin;

use App\Models\Advance;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Advance::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereIn('role', [
                    User::ROLE_EMPLOYEE,
                    User::ROLE_DRIVER,
                    User::ROLE_TEAM_LEADER,
                    User::ROLE_ACCOUNTANT,
                    User::ROLE_ASSISTANT,
                ]),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'date_given' => ['required', 'date'],
            'deduction_amount_monthly' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999.99',
                'lte:amount',
            ],
        ];
    }
}
