<?php

namespace App\Http\Requests\Admin;

use App\Models\EmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    /**
     * Batch upload: every file whitelisted jpg/jpeg/png/pdf, max 5MB each (AGENTS.md §5.5).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(EmployeeDocument::TYPES)],
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
