<?php

namespace App\Http\Requests\Admin;

use App\Models\PayrollAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payroll = $this->route('payroll');

        return $this->user()?->can('update', $payroll) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in([
                PayrollAdjustment::TYPE_DEPARTMENT_COMMISSION,
                PayrollAdjustment::TYPE_SALES_COMMISSION,
                PayrollAdjustment::TYPE_PROFIT_COMMISSION,
                PayrollAdjustment::TYPE_BONUS,
                PayrollAdjustment::TYPE_ADVANCE,
                PayrollAdjustment::TYPE_OTHER_DEDUCTION,
            ])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
