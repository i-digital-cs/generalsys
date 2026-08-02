<?php

namespace App\Services\Employees;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Employee document storage — `local` disk only (outside web root),
 * random stored filenames (AGENTS.md §5.5).
 */
class DocumentService
{
    /**
     * Store a batch of documents of one type in a single transaction.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, EmployeeDocument>
     */
    public function storeMany(User $employee, array $files, string $type): array
    {
        return DB::transaction(function () use ($employee, $files, $type) {
            $documents = [];

            foreach ($files as $file) {
                $documents[] = $this->store($employee, $file, $type);
            }

            return $documents;
        });
    }

    /**
     * Remove the storage file and the row, atomically.
     */
    public function delete(EmployeeDocument $document): void
    {
        DB::transaction(function () use ($document) {
            $document->delete();

            Storage::disk('local')->delete($document->file_path);
        });
    }

    public function store(User $employee, UploadedFile $file, string $type): EmployeeDocument
    {
        $path = $file->storeAs(
            "employee-documents/{$employee->id}",
            Str::random(40).'.'.$file->extension(),
            'local',
        );

        return $employee->documents()->create([
            'type' => $type,
            'file_path' => $path,
            'uploaded_at' => now(),
        ]);
    }
}
