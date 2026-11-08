<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhOrderAccess;
use Illuminate\Foundation\Http\FormRequest;

class ReorderGhDriverListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhOrderAccess::mayUseDriverWorkspace($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order_ids' => ['required', 'array', 'min:1', 'max:50'],
            'order_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'previous_order_ids' => ['nullable', 'array', 'max:50'],
            'previous_order_ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }
}
