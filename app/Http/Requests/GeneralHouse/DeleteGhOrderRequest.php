<?php

namespace App\Http\Requests\GeneralHouse;

use App\Services\GeneralHouse\Orders\GhOrderDeletionService;
use App\Support\GeneralHouse\GhOrderAccess;
use Illuminate\Foundation\Http\FormRequest;

class DeleteGhOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhOrderAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'delete_reason' => ['required', 'string', 'max:'.GhOrderDeletionService::REASON_MAX_LENGTH],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'delete_reason.required' => __('gh.errors.order_delete_reason_required'),
            'delete_reason.max' => __('gh.errors.order_delete_reason_too_long'),
        ];
    }
}
