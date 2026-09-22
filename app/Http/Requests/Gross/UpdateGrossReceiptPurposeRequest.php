<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossReceiptPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrossReceiptPurposeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GrossReceiptPurpose $purpose */
        $purpose = $this->route('receiptPurpose');

        return $this->user()?->can('update', $purpose) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var GrossReceiptPurpose $purpose */
        $purpose = $this->route('receiptPurpose');

        return [
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => [
                'required',
                'string',
                'max:120',
                Rule::unique('gross_receipt_purposes', 'name_en')->ignore($purpose->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
