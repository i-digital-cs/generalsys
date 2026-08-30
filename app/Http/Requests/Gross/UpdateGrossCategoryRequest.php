<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossItem;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGrossCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GrossItem::class) ?? false;
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
