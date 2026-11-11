{{-- Legacy baseline: git HEAD for resources/views/gh/treatments/index.blade.php --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.treatments.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.treatments.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.offerings.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.offerings.title') }}</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            @can('create', App\Models\GeneralHouse\GhTreatment::class)
                <form method="post" action="{{ route('gh.treatments.store') }}" class="row g-2 align-items-end mb-3 pb-3 border-bottom">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label mb-1" for="name_ar">{{ __('gh.fields.name_ar') }}</label>
                        <input id="name_ar" name="name_ar" class="form-control form-control-sm @error('name_ar') is-invalid @enderror"
                               value="{{ old('name_ar') }}" required maxlength="120" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1" for="name_en">{{ __('gh.fields.name_en') }}</label>
                        <input id="name_en" name="name_en" class="form-control form-control-sm @error('name_en') is-invalid @enderror"
                               value="{{ old('name_en') }}" required maxlength="120" autocomplete="off">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1" for="status">{{ __('gh.fields.status') }}</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                                <option value="{{ $st }}" @selected(old('status', 'ACTIVE') === $st)>{{ __('gh.status.'.$st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1" for="sort_order">{{ __('gh.fields.sort_order') }}</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" class="form-control form-control-sm"
                               value="{{ old('sort_order', 0) }}">
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gh.treatments.create') }}</button>
                    </div>
                </form>
            @endcan

            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"
                           placeholder="{{ __('gh.treatments.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1" for="filter_status">{{ __('gh.fields.status') }}</label>
                    <select id="filter_status" name="status" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ __('gh.status.'.$st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '')
                        <a href="{{ route('gh.treatments.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            @if ($rows->isEmpty())
                <x-empty-state :title="__('gh.treatments.empty')" icon="bi-droplet" :text="__('gh.treatments.empty_hint')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 7rem">{{ __('gh.fields.sort_order') }}</th>
                                <th>{{ __('gh.fields.name_ar') }}</th>
                                <th>{{ __('gh.fields.name_en') }}</th>
                                <th>{{ __('gh.fields.status') }}</th>
                                <th class="text-end" style="width: 6rem">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="text-center"><span class="hp-value num-ltr">{{ $row->sort_order }}</span></td>
                                    <td>{{ $row->name_ar }}</td>
                                    <td>{{ $row->name_en }}</td>
                                    <td>@include('gh.partials.status-badge', ['status' => $row->status])</td>
                                    <td class="text-end text-nowrap">
                                        @can('update', $row)
                                            <button type="button" class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                                    title="{{ __('general.edit') }}"
                                                    data-bs-toggle="modal" data-bs-target="#gh-treat-edit-modal"
                                                    data-action="{{ route('gh.treatments.update', $row) }}"
                                                    data-name-ar="{{ $row->name_ar }}"
                                                    data-name-en="{{ $row->name_en }}"
                                                    data-status="{{ $row->status }}"
                                                    data-sort="{{ $row->sort_order }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                <span class="visually-hidden">{{ __('general.edit') }}</span>
                                            </button>
                                            <form method="post" action="{{ route('gh.treatments.destroy', $row) }}" class="d-inline"
                                                  onsubmit="return confirm(@json(__('gh.treatments.confirm_delete')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="{{ __('general.delete') }}">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                    <span class="visually-hidden">{{ __('general.delete') }}</span>
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $rows->links() }}</div>
            @endif
        </div>
    </div>

    @can('create', App\Models\GeneralHouse\GhTreatment::class)
        <div class="modal fade" id="gh-treat-edit-modal" tabindex="-1" aria-labelledby="gh-treat-edit-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="post" id="gh-treat-edit-form" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header py-2">
                        <h2 class="modal-title h6 mb-0" id="gh-treat-edit-title">{{ __('gh.treatments.edit') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="gh-treat-edit-name-ar">{{ __('gh.fields.name_ar') }}</label>
                            <input id="gh-treat-edit-name-ar" name="name_ar" class="form-control" required maxlength="120" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gh-treat-edit-name-en">{{ __('gh.fields.name_en') }}</label>
                            <input id="gh-treat-edit-name-en" name="name_en" class="form-control" required maxlength="120" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gh-treat-edit-status">{{ __('gh.fields.status') }}</label>
                            <select id="gh-treat-edit-status" name="status" class="form-select">
                                @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                                    <option value="{{ $st }}">{{ __('gh.status.'.$st) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="gh-treat-edit-sort">{{ __('gh.fields.sort_order') }}</label>
                            <input id="gh-treat-edit-sort" name="sort_order" type="number" min="0" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('general.cancel') }}</button>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('general.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
        @push('scripts')
            <script>
                (function () {
                    const modal = document.getElementById('gh-treat-edit-modal');
                    if (!modal) return;
                    const form = document.getElementById('gh-treat-edit-form');
                    modal.addEventListener('show.bs.modal', function (event) {
                        const btn = event.relatedTarget;
                        if (!btn) return;
                        form.setAttribute('action', btn.getAttribute('data-action') || '');
                        document.getElementById('gh-treat-edit-name-ar').value = btn.getAttribute('data-name-ar') || '';
                        document.getElementById('gh-treat-edit-name-en').value = btn.getAttribute('data-name-en') || '';
                        document.getElementById('gh-treat-edit-status').value = btn.getAttribute('data-status') || 'ACTIVE';
                        document.getElementById('gh-treat-edit-sort').value = btn.getAttribute('data-sort') || '0';
                    });
                })();
            </script>
        @endpush
    @endcan
    @include('gh.import.partials.import-card', ['entity' => 'treatments', 'model' => App\Models\GeneralHouse\GhTreatment::class])
