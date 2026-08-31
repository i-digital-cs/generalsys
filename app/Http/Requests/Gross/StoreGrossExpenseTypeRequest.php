<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossExpenseType;
use Illuminate\Foundation\Http\FormRequest;

class StoreGrossExpenseTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GrossExpenseType::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120', 'unique:gross_expense_types,name_en'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
