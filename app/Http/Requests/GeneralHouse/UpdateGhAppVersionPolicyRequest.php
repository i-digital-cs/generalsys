<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhAppPublicationStatus;
use App\Support\GeneralHouse\GhAppUpdateMode;
use App\Support\GnrlAppAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGhAppVersionPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return GnrlAppAccess::mayManageVersions($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latest_version_name' => ['nullable', 'string', 'max:50'],
            'latest_build' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'minimum_build' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'update_mode' => ['required', 'string', Rule::in(GhAppUpdateMode::all())],
            'update_url' => ['nullable', 'string', 'max:500', 'url'],
            'release_notes_ar' => ['nullable', 'string', 'max:5000'],
            'release_notes_en' => ['nullable', 'string', 'max:5000'],
            'publication_status' => ['required', 'string', Rule::in(GhAppPublicationStatus::all())],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
