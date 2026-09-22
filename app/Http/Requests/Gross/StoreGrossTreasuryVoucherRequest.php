<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossTreasuryVoucher;
use App\Services\Gross\TreasuryVoucherAttachmentService;
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
        $isCourierCod = $purpose === GrossTreasuryVoucher::PURPOSE_COURIER_COD;
        $isOther = $purpose === GrossTreasuryVoucher::PURPOSE_OTHER;

        return [
            'type' => ['required', Rule::in([
                GrossTreasuryVoucher::TYPE_PAYMENT,
                GrossTreasuryVoucher::TYPE_RECEIPT,
            ])],
            'purpose' => ['required', Rule::in([
                GrossTreasuryVoucher::PURPOSE_SUPPLIER,
                GrossTreasuryVoucher::PURPOSE_EXPENSE,
                GrossTreasuryVoucher::PURPOSE_COURIER_COD,
                GrossTreasuryVoucher::PURPOSE_OTHER,
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
            'receipt_purpose_id' => [
                $isOther ? 'required' : 'nullable',
                'integer',
                'exists:gross_receipt_purposes,id',
            ],
            'sales_invoice_id' => [
                $isCourierCod ? 'required' : 'nullable',
                'integer',
                'exists:gross_sales_invoices,id',
            ],
            'amount' => [$isCourierCod ? 'nullable' : 'required', 'numeric', 'min:0.01'],
            'voucher_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:'.TreasuryVoucherAttachmentService::MAX_PER_VOUCHER],
            'attachments.*' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = strtoupper((string) $this->input('type', ''));
            $purpose = strtoupper((string) $this->input('purpose', ''));

            if ($purpose === GrossTreasuryVoucher::PURPOSE_OTHER && $type !== GrossTreasuryVoucher::TYPE_RECEIPT) {
                $validator->errors()->add('purpose', __('gross.errors.other_receipt_only'));
            }

            if ($purpose === GrossTreasuryVoucher::PURPOSE_COURIER_COD && $type !== GrossTreasuryVoucher::TYPE_RECEIPT) {
                $validator->errors()->add('purpose', __('gross.errors.courier_cod_receipt_only'));
            }
        });
    }
}
