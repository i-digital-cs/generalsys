<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadDocumentRequest;
use App\Models\User;
use App\Services\Employees\DocumentService;
use Illuminate\Http\RedirectResponse;

class EmployeeDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function store(UploadDocumentRequest $request, User $employee): RedirectResponse
    {
        $this->documents->store(
            $employee,
            $request->file('document'),
            $request->string('type')->toString(),
        );

        return redirect()
            ->route('admin.employees.edit', $employee)
            ->with('success', __('documents.flash.uploaded'));
    }
}
