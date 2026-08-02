<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadDocumentRequest;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\Employees\DocumentService;
use Illuminate\Http\RedirectResponse;

class EmployeeDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function store(UploadDocumentRequest $request, User $employee): RedirectResponse
    {
        $this->documents->storeMany(
            $employee,
            $request->file('documents'),
            $request->string('type')->toString(),
        );

        return redirect()
            ->route('admin.employees.edit', $employee)
            ->with('success', __('documents.flash.uploaded'));
    }

    public function destroy(User $employee, EmployeeDocument $document): RedirectResponse
    {
        abort_unless($document->user_id === $employee->id, 404);

        $this->documents->delete($document);

        return redirect()
            ->route('admin.employees.edit', $employee)
            ->with('success', __('documents.flash.deleted'));
    }
}
