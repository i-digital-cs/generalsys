<?php

namespace App\Http\Requests\GeneralHouse;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGhVatSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'standard_vat_rate' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
