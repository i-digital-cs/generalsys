<?php

namespace App\Http\Requests\Gross;

use App\Support\GrossAccess;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGrossItemAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GrossAccess::mayPage($this->user(), 'attributes');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
        ];
    }
}
