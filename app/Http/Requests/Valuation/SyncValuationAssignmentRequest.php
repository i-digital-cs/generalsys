<?php

namespace App\Http\Requests\Valuation;

use App\Models\ValuationAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncValuationAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(ValuationAssignment::MODULES)],
        ];
    }
}
