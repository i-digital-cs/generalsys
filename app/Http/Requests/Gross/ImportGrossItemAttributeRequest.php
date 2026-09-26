<?php

namespace App\Http\Requests\Gross;

use App\Support\GrossAccess;
use Illuminate\Foundation\Http\FormRequest;

class ImportGrossItemAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GrossAccess::mayPage($this->user(), 'attributes');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:2048',
                'mimes:xlsx,xls,csv',
            ],
        ];
    }
}
