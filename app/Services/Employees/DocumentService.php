<?php

namespace App\Services\Employees;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Employee document storage — `local` disk only (outside web root),
 * random stored filenames (AGENTS.md §5.5).
 */
class DocumentService
{
    public function store(User $employee, UploadedFile $file, string $type): EmployeeDocument
    {
        $filename = Str::random(40).'.'.$file->extension();

        $path = $file->storeAs("employee-documents/{$employee->id}", $filename, 'local');

        return $employee->documents()->create([
            'type' => $type,
            'file_path' => $path,
            'uploaded_at' => now(),
        ]);
    }
}
