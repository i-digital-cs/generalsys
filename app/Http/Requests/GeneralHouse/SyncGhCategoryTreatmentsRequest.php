<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogAccess;
use Illuminate\Foundation\Http\FormRequest;

class SyncGhCategoryTreatmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'treatment_ids' => ['nullable', 'array'],
            'treatment_ids.*' => ['integer', 'distinct', 'exists:gh_treatments,id'],
        ];
    }
}
