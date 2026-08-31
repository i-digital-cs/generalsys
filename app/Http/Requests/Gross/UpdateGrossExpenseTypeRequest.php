<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossExpenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrossExpenseTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GrossExpenseType $type */
        $type = $this->route('expenseType');

        return $this->user()?->can('update', $type) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var GrossExpenseType $type */
        $type = $this->route('expenseType');

        return [
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => [
                'required',
                'string',
                'max:120',
                Rule::unique('gross_expense_types', 'name_en')->ignore($type->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
