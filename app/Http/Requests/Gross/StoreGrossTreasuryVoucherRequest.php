<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossTreasuryVoucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrossTreasuryVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GrossTreasuryVoucher::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $purpose = strtoupper((string) $this->input('purpose', ''));
        $isSupplier = $purpose === GrossTreasuryVoucher::PURPOSE_SUPPLIER;
        $isExpense = $purpose === GrossTreasuryVoucher::PURPOSE_EXPENSE;

        return [
            'type' => ['required', Rule::in([
                GrossTreasuryVoucher::TYPE_PAYMENT,
                GrossTreasuryVoucher::TYPE_RECEIPT,
            ])],
            'purpose' => ['required', Rule::in([
                GrossTreasuryVoucher::PURPOSE_SUPPLIER,
                GrossTreasuryVoucher::PURPOSE_EXPENSE,
            ])],
            'account_id' => ['required', 'integer', 'exists:gross_treasury_accounts,id'],
            'supplier_id' => [
                $isSupplier ? 'required' : 'nullable',
                'integer',
                'exists:gross_suppliers,id',
            ],
            'expense_type_id' => [
                $isExpense ? 'required' : 'nullable',
                'integer',
                'exists:gross_expense_types,id',
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'voucher_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
