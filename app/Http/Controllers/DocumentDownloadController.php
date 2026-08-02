<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authorized document download — MANAGER or the owning employee
 * (EmployeePolicy::downloadDocument). Files live on the `local` disk.
 */
class DocumentDownloadController extends Controller
{
    public function show(EmployeeDocument $document): StreamedResponse
    {
        $this->authorize('downloadDocument', $document->user);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path);
    }
}
