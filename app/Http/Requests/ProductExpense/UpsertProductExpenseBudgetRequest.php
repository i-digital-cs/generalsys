<?php

namespace App\Http\Requests\ProductExpense;

use App\Support\GrossAccess;
use Illuminate\Foundation\Http\FormRequest;

class UpsertProductExpenseBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GrossAccess::mayManageMasterData($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'expected_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
