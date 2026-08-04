<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $advance = $this->route('advance');

        return $this->user()?->can('update', $advance) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
