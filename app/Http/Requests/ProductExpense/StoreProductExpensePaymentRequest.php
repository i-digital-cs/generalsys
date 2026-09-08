<?php

namespace App\Http\Requests\ProductExpense;

use App\Support\GrossAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductExpensePaymentRequest extends FormRequest
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
            'account_id' => ['required', 'integer', 'exists:gross_treasury_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'voucher_date' => ['required', 'date'],
            'rent_period' => ['nullable', 'string', Rule::in([
                'THIS_MONTH',
                'NEXT_MONTH',
            ])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
