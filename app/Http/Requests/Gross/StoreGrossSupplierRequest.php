<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossSupplier;
use Illuminate\Foundation\Http\FormRequest;

class StoreGrossSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GrossSupplier::class) ?? false;
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
