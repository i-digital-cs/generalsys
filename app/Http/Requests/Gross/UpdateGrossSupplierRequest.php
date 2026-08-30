<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossSupplier;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGrossSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GrossSupplier $supplier */
        $supplier = $this->route('supplier');

        return $this->user()?->can('update', $supplier) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
