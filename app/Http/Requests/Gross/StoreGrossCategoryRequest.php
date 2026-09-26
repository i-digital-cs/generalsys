<?php

namespace App\Http\Requests\Gross;

use App\Support\GrossAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreGrossCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GrossAccess::mayPage($this->user(), 'categories');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:gross_categories,id'],
        ];
    }
}
