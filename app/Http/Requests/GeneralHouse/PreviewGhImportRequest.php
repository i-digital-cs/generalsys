<?php

namespace App\Http\Requests\GeneralHouse;

use App\Services\GeneralHouse\Import\GhImportService;
use Illuminate\Foundation\Http\FormRequest;

class PreviewGhImportRequest extends FormRequest
{
    protected $errorBag = 'ghImport';

    public function authorize(): bool
    {
        $importer = app(GhImportService::class)->importer((string) $this->route('entity'));

        return $importer !== null && ($this->user()?->can('create', $importer->modelClass()) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:4096', 'mimes:xlsx,xls'],
            'zip' => ['nullable', 'file', 'max:20480', 'mimes:zip'],
        ];
    }
}
