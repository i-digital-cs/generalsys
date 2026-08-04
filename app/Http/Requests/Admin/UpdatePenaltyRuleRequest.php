<?php

namespace App\Http\Requests\Admin;

use App\Models\PenaltyRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePenaltyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PenaltyRule $rule */
        $rule = $this->route('penalty_rule');

        return $this->user()?->can('update', $rule) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'amount_per_unit' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'unit' => ['required', Rule::in([
                PenaltyRule::UNIT_MINUTE,
                PenaltyRule::UNIT_HOUR,
                PenaltyRule::UNIT_DAY,
            ])],
            'double_penalty_multiplier' => ['required', 'numeric', 'min:1', 'max:99.99'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
