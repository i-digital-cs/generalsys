@can('create', $model)
    @php($withZip = $withZip ?? false)
    @php($importErrors = $errors->getBag('ghImport'))
    @php($problems = session('gh_import_problems', []))
    @php($forceOpen = $importErrors->any() || $problems !== [])
    <div class="card border-0 shadow-sm mb-3 gh-import-card" id="gh-import-{{ $entity }}">
        <details class="gh-import-card__details" @if ($forceOpen) open @endif>
            <summary class="gh-import-card__summary">
                <span class="gh-import-card__summary-text">
                    <span class="fw-semibold">{{ __('gh.import.title') }}</span>
                    <span class="small text-body-secondary d-block">{{ __('gh.import.collapsed_hint') }}</span>
                </span>
                <i class="bi bi-chevron-down gh-import-card__chevron" aria-hidden="true"></i>
            </summary>
            <div class="card-body pt-2">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                    <div>
                        <p class="small text-body-secondary mb-1">{{ __('gh.import.help.'.$entity) }}</p>
                        <p class="small text-body-secondary mb-0">{{ __('gh.import.max_rows_hint') }}</p>
                    </div>
                    <a href="{{ route('gh.import.template', ['entity' => $entity]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('gh.import.download_example') }}
                    </a>
                </div>

                @if ($importErrors->any())
                    <div class="alert alert-danger py-2 small">
                        <ul class="mb-0 ps-3">
                            @foreach ($importErrors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($problems !== [])
                    <div class="alert alert-warning py-2 small">
                        <strong>{{ __('gh.import.preview.problems_title') }}</strong>
                        <ul class="mb-0 ps-3">
                            @foreach ($problems as $problem)
                                <li>{{ $problem }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('gh.import.preview.store', ['entity' => $entity]) }}" enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="{{ $withZip ? 'col-md-5' : 'col-md-8' }}">
                        <label class="form-label mb-1" for="gh-import-file-{{ $entity }}">{{ __('gh.import.file') }}</label>
                        <input id="gh-import-file-{{ $entity }}" name="file" type="file" class="form-control form-control-sm"
                               accept=".xlsx,.xls" required>
                    </div>
                    @if ($withZip)
                        <div class="col-md-4">
                            <label class="form-label mb-1" for="gh-import-zip-{{ $entity }}">{{ __('gh.import.zip') }}</label>
                            <input id="gh-import-zip-{{ $entity }}" name="zip" type="file" class="form-control form-control-sm"
                                   accept=".zip" aria-describedby="gh-import-zip-help-{{ $entity }}">
                        </div>
                    @endif
                    <div class="col-md-3 col-lg-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('gh.import.preview_button') }}
                        </button>
                    </div>
                    @if ($withZip)
                        <div class="col-12">
                            <div id="gh-import-zip-help-{{ $entity }}" class="form-text">{{ __('gh.import.zip_help') }}</div>
                        </div>
                    @endif
                </form>
                <p class="small text-body-secondary mt-2 mb-0">{{ __('gh.import.financial_no_import') }}</p>
            </div>
        </details>
    </div>
@endcan
