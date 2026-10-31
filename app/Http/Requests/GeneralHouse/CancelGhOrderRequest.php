<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhOrderAccess;
use Illuminate\Foundation\Http\FormRequest;

class CancelGhOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhOrderAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'max:500'],
            'status' => ['prohibited'],
        ];
    }
}
