<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemAttribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrossItemAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GrossItem::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(GrossItemAttribute::KINDS)],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
        ];
    }
}
