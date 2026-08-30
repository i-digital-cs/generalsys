@extends('layouts.app')

@section('title', __('gross.categories.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.categories.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.categories.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\Gross\GrossItem::class)
                <a href="{{ route('gross.categories.template') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('gross.categories.template') }}
                </a>
            @endcan
            <a href="{{ route('gross.items.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.items.title') }}</a>
        </div>
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
            @can('create', App\Models\Gross\GrossItem::class)
                <form method="post" action="{{ route('gross.categories.store') }}"
                      class="row g-2 align-items-end mb-3 pb-3 border-bottom">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label mb-1" for="name_ar">{{ __('gross.fields.name_ar') }}</label>
                        <input id="name_ar" name="name_ar" class="form-control form-control-sm @error('name_ar') is-invalid @enderror"
                               value="{{ old('name_ar') }}" required maxlength="120" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1" for="name_en">{{ __('gross.fields.name_en') }}</label>
                        <input id="name_en" name="name_en" class="form-control form-control-sm @error('name_en') is-invalid @enderror"
                               value="{{ old('name_en') }}" required maxlength="120" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1" for="parent_id">{{ __('gross.fields.parent') }}</label>
                        <select id="parent_id" name="parent_id" class="form-select form-select-sm">
                            <option value="">{{ __('gross.categories.parent_none') }}</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}" @selected((string) old('parent_id') === (string) $parent->id)>
                                    {{ $parent->localizedName() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gross.categories.create') }}</button>
                    </div>
                </form>
            @endcan

            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-6 col-lg-4">
                    <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"
                           placeholder="{{ __('gross.categories.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if (! empty($filters['q']))
                        <a href="{{ route('gross.categories.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            @if ($rows->isEmpty())
                <x-empty-state :title="__('gross.categories.empty')" icon="bi-tags"
                               :text="__('gross.categories.empty_hint')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.name_ar') }}</th>
                                <th>{{ __('gross.fields.name_en') }}</th>
                                <th>{{ __('gross.fields.parent') }}</th>
                                <th class="text-end" style="width: 6rem">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        @if ($row->parent_id)
                                            <span class="text-body-secondary">↳</span>
                                        @endif
                                        {{ $row->name_ar }}
                                    </td>
                                    <td>{{ $row->name_en }}</td>
                                    <td>
                                        @if ($row->parent)
                                            {{ $row->parent->localizedName() }}
                                        @else
                                            <span class="badge text-bg-secondary">{{ __('gross.categories.type_main') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @can('create', App\Models\Gross\GrossItem::class)
                                            <button type="button"
                                                    class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                                    title="{{ __('general.edit') }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#gross-cat-edit-modal"
                                                    data-id="{{ $row->id }}"
                                                    data-name-ar="{{ $row->name_ar }}"
                                                    data-name-en="{{ $row->name_en }}"
                                                    data-parent-id="{{ $row->parent_id ?? '' }}"
                                                    data-action="{{ route('gross.categories.update', $row) }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                <span class="visually-hidden">{{ __('general.edit') }}</span>
                                            </button>
                                            <form method="post" action="{{ route('gross.categories.destroy', $row) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm(@json(__('gross.categories.confirm_delete')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link btn-sm text-danger p-0"
                                                        title="{{ __('general.delete') }}">
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
            @endif
        </div>
    </div>

    @can('create', App\Models\Gross\GrossItem::class)
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <h2 class="h6 mb-1">{{ __('gross.categories.import_title') }}</h2>
                        <p class="small text-body-secondary mb-0">{{ __('gross.categories.import_help') }}</p>
                    </div>
                    <a href="{{ route('gross.categories.template') }}" class="btn btn-link btn-sm px-0">
                        {{ __('gross.categories.template') }}
                    </a>
                </div>
                <form method="post" action="{{ route('gross.categories.import') }}" enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label mb-1" for="category_file">{{ __('gross.categories.import_file') }}</label>
                        <input id="category_file" name="file" type="file" class="form-control form-control-sm"
                               accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="col-md-4 col-lg-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('gross.categories.import') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="gross-cat-edit-modal" tabindex="-1" aria-labelledby="gross-cat-edit-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="post" id="gross-cat-edit-form" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header py-2">
                        <h2 class="modal-title h6 mb-0" id="gross-cat-edit-title">{{ __('general.edit') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="gross-cat-edit-name-ar">{{ __('gross.fields.name_ar') }}</label>
                            <input id="gross-cat-edit-name-ar" name="name_ar" class="form-control" required maxlength="120" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gross-cat-edit-name-en">{{ __('gross.fields.name_en') }}</label>
                            <input id="gross-cat-edit-name-en" name="name_en" class="form-control" required maxlength="120" autocomplete="off">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="gross-cat-edit-parent">{{ __('gross.fields.parent') }}</label>
                            <select id="gross-cat-edit-parent" name="parent_id" class="form-select">
                                <option value="">{{ __('gross.categories.parent_none') }}</option>
                                @foreach ($parents as $parent)
                                    <option value="{{ $parent->id }}">{{ $parent->localizedName() }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('gross.categories.parent_hint') }}</div>
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
                    const modal = document.getElementById('gross-cat-edit-modal');
                    if (!modal) return;
                    const form = document.getElementById('gross-cat-edit-form');
                    const nameAr = document.getElementById('gross-cat-edit-name-ar');
                    const nameEn = document.getElementById('gross-cat-edit-name-en');
                    const parent = document.getElementById('gross-cat-edit-parent');
                    modal.addEventListener('show.bs.modal', function (event) {
                        const btn = event.relatedTarget;
                        if (!btn) return;
                        form.setAttribute('action', btn.getAttribute('data-action') || '');
                        nameAr.value = btn.getAttribute('data-name-ar') || '';
                        nameEn.value = btn.getAttribute('data-name-en') || '';
                        parent.value = btn.getAttribute('data-parent-id') || '';
                        const editingId = btn.getAttribute('data-id') || '';
                        Array.from(parent.options).forEach(function (opt) {
                            opt.disabled = opt.value !== '' && opt.value === editingId;
                        });
                    });
                })();
            </script>
        @endpush
    @endcan
@endsection
