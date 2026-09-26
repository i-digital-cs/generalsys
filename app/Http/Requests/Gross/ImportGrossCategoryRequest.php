<?php

namespace App\Http\Requests\Gross;

use App\Support\GrossAccess;
use Illuminate\Foundation\Http\FormRequest;

class ImportGrossCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GrossAccess::mayPage($this->user(), 'categories');
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
