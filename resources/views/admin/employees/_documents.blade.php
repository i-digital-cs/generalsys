{{-- Documents section (edit screen only, T-1.6) --}}
<div class="card border-0 shadow-sm my-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('documents.title') }}</div>
    <div class="card-body">
        @if ($employee->documents->isEmpty())
            <p class="text-body-secondary">{{ __('documents.no_documents') }}</p>
        @else
            @php
                $showDocumentsToolbar = $employee->documents->count() > 1;
            @endphp
            @if ($showDocumentsToolbar)
                <div data-table-filter-root class="mb-3">
                    <x-table-toolbar
                        id="employee-documents"
                        :search-placeholder="__('documents.type')"
                    />
                    <div class="table-responsive">
                        <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('documents.type') }}</th>
                                    <th scope="col">{{ __('documents.uploaded_at') }}</th>
                                    <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($employee->documents as $document)
                                    <tr data-filter-text="{{ __("documents.types.{$document->type}") }}">
                                        <td>{{ __("documents.types.{$document->type}") }}</td>
                                        <td>
                                            <span class="num-ltr">{{ $document->uploaded_at?->translatedFormat('Y-m-d H:i') }}</span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <a href="{{ route('documents.download', $document) }}"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('documents.download') }}
                                                </a>
                                                <form method="POST"
                                                      action="{{ route('admin.employees.documents.destroy', [$employee, $document]) }}"
                                                      onsubmit="return confirm(@js(__('documents.confirm_delete')))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                            aria-label="{{ __('documents.delete') }}">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr data-table-empty-row class="d-none">
                                    <td colspan="3" class="text-body-secondary text-center py-3">
                                        {{ __('general.no_filter_results') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="table-responsive mb-3">
                    <table class="table table-hr table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('documents.type') }}</th>
                                <th scope="col">{{ __('documents.uploaded_at') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employee->documents as $document)
                                <tr>
                                    <td>{{ __("documents.types.{$document->type}") }}</td>
                                    <td>
                                        <span class="num-ltr">{{ $document->uploaded_at?->translatedFormat('Y-m-d H:i') }}</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('documents.download', $document) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('documents.download') }}
                                            </a>
                                            <form method="POST"
                                                  action="{{ route('admin.employees.documents.destroy', [$employee, $document]) }}"
                                                  onsubmit="return confirm(@js(__('documents.confirm_delete')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        aria-label="{{ __('documents.delete') }}">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        <form method="POST" action="{{ route('admin.employees.documents.store', $employee) }}"
              enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <div class="col-12 col-md-4">
                <label for="document_type" class="form-label">{{ __('documents.type') }}</label>
                <select id="document_type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                    @foreach (\App\Models\EmployeeDocument::TYPES as $type)
                        <option value="{{ $type }}">{{ __("documents.types.{$type}") }}</option>
                    @endforeach
                </select>
                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12 col-md-5">
                <label for="documents" class="form-label">{{ __('documents.choose_files') }}</label>
                <input type="file" id="documents" name="documents[]" accept=".jpg,.jpeg,.png,.pdf" multiple
                       class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror" required>
                <div class="form-text">{{ __('documents.allowed_types') }}</div>
                @error('documents') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @error('documents.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12 col-md-3 d-grid">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('documents.upload') }}
                </button>
            </div>
        </form>
    </div>
</div>
