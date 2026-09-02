<?php

namespace App\Http\Requests\Gross;

use App\Support\GrossAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectCodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [];

        if (GrossAccess::mayPostTreasury($this->user())) {
            $rules['post_to_treasury'] = ['nullable', 'boolean'];
            $rules['treasury_account_id'] = [
                Rule::requiredIf(fn () => $this->boolean('post_to_treasury')),
                'nullable',
                'integer',
                'exists:gross_treasury_accounts,id',
            ];
        }

        return $rules;
    }

    /**
     * @return array{post_to_treasury: bool, treasury_account_id: int|null}
     */
    public function treasuryOptions(): array
    {
        if (! GrossAccess::mayPostTreasury($this->user())) {
            return [
                'post_to_treasury' => false,
                'treasury_account_id' => null,
            ];
        }

        return [
            'post_to_treasury' => $this->boolean('post_to_treasury'),
            'treasury_account_id' => $this->filled('treasury_account_id')
                ? (int) $this->input('treasury_account_id')
                : null,
        ];
    }
}
