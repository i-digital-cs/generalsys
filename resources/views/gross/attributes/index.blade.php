@extends('layouts.app')

@section('title', __('gross.attributes.title'))

@section('content')
    @php
        $icons = [
            'COLOR' => 'bi-palette',
            'SIZE' => 'bi-rulers',
            'MODEL' => 'bi-bounding-box',
        ];
        $activeTab = old('kind', $activeTab ?? 'COLOR');
        if (! in_array($activeTab, ['COLOR', 'SIZE', 'MODEL'], true)) {
            $activeTab = 'COLOR';
        }
        $filters = $filters ?? ['q' => ''];
        $searchQ = $filters['q'] ?? '';
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.attributes.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.attributes.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\Gross\GrossItem::class)
                <a href="{{ route('gross.attributes.template') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('gross.attributes.template') }}
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
        <div class="card-body pb-0">
            <form method="get" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <div class="col-md-6 col-lg-4">
                    <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="{{ $searchQ }}"
                           placeholder="{{ __('gross.attributes.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($searchQ !== '')
                        <a href="{{ route('gross.attributes.index', ['tab' => $activeTab]) }}"
                           class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="card-header bg-transparent border-0 pt-0 pb-0">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                @foreach (['COLOR', 'SIZE', 'MODEL'] as $kind)
                    @php($count = ($grouped[$kind] ?? collect())->count())
                    <li class="nav-item" role="presentation">
                        <a href="{{ route('gross.attributes.index', array_filter(['tab' => $kind, 'q' => $searchQ !== '' ? $searchQ : null])) }}"
                           class="nav-link @if ($activeTab === $kind) active @endif"
                           id="tab-{{ strtolower($kind) }}"
                           role="tab"
                           aria-selected="{{ $activeTab === $kind ? 'true' : 'false' }}">
                            <i class="bi {{ $icons[$kind] }} me-1" aria-hidden="true"></i>
                            {{ __('gross.attributes.kinds.'.$kind) }}
                            <span class="badge text-bg-secondary ms-1 num-ltr">{{ $count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body pt-3">
            @php($rows = $grouped[$activeTab] ?? collect())
            @can('create', App\Models\Gross\GrossItem::class)
                <form method="post" action="{{ route('gross.attributes.store') }}"
                      class="row g-2 align-items-end mb-3 pb-3 border-bottom">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $activeTab }}">
                    <div class="col-sm-5 col-md-4">
                        <label class="form-label mb-1" for="name_ar">{{ __('gross.fields.name_ar') }}</label>
                        <input id="name_ar" name="name_ar"
                               class="form-control form-control-sm @error('name_ar') is-invalid @enderror"
                               value="{{ old('name_ar') }}"
                               required maxlength="120" autocomplete="off">
                    </div>
                    <div class="col-sm-5 col-md-4">
                        <label class="form-label mb-1" for="name_en">{{ __('gross.fields.name_en') }}</label>
                        <input id="name_en" name="name_en"
                               class="form-control form-control-sm @error('name_en') is-invalid @enderror"
                               value="{{ old('name_en') }}"
                               required maxlength="120" autocomplete="off">
                    </div>
                    <div class="col-sm-2 col-md-auto">
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gross.attributes.create') }}</button>
                    </div>
                </form>
            @endcan

            @if ($rows->isEmpty())
                <x-empty-state
                    :title="$searchQ !== '' ? __('general.no_filter_results') : __('gross.attributes.empty')"
                    icon="{{ $icons[$activeTab] }}"
                    :text="$searchQ !== '' ? null : __('gross.attributes.empty_hint')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.name_ar') }}</th>
                                <th>{{ __('gross.fields.name_en') }}</th>
                                <th class="text-end" style="width: 6rem">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row->name_ar }}</td>
                                    <td>{{ $row->name_en }}</td>
                                    <td class="text-end text-nowrap">
                                        @can('create', App\Models\Gross\GrossItem::class)
                                            <button type="button"
                                                    class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                                    title="{{ __('general.edit') }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#gross-attr-edit-modal"
                                                    data-id="{{ $row->id }}"
                                                    data-kind="{{ $row->kind }}"
                                                    data-name-ar="{{ $row->name_ar }}"
                                                    data-name-en="{{ $row->name_en }}"
                                                    data-action="{{ route('gross.attributes.update', $row) }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                <span class="visually-hidden">{{ __('general.edit') }}</span>
                                            </button>
                                            <form method="post" action="{{ route('gross.attributes.destroy', $row) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm(@json(__('gross.attributes.confirm_delete')))">
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
                        <h2 class="h6 mb-1">{{ __('gross.attributes.import_title') }}</h2>
                        <p class="small text-body-secondary mb-0">{{ __('gross.attributes.import_help') }}</p>
                    </div>
                    <a href="{{ route('gross.attributes.template') }}" class="btn btn-link btn-sm px-0">
                        {{ __('gross.attributes.template') }}
                    </a>
                </div>
                <form method="post" action="{{ route('gross.attributes.import') }}" enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label mb-1" for="attribute_file">{{ __('gross.attributes.import_file') }}</label>
                        <input id="attribute_file" name="file" type="file" class="form-control form-control-sm"
                               accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="col-md-4 col-lg-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('gross.attributes.import') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @can('create', App\Models\Gross\GrossItem::class)
        <div class="modal fade" id="gross-attr-edit-modal" tabindex="-1" aria-labelledby="gross-attr-edit-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="post" id="gross-attr-edit-form" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header py-2">
                        <h2 class="modal-title h6 mb-0" id="gross-attr-edit-title">{{ __('general.edit') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-body-secondary mb-3">
                            <span id="gross-attr-edit-kind-label"></span>
                        </p>
                        <div class="mb-3">
                            <label class="form-label" for="gross-attr-edit-name-ar">{{ __('gross.fields.name_ar') }}</label>
                            <input id="gross-attr-edit-name-ar" name="name_ar" class="form-control" required maxlength="120" autocomplete="off">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="gross-attr-edit-name-en">{{ __('gross.fields.name_en') }}</label>
                            <input id="gross-attr-edit-name-en" name="name_en" class="form-control" required maxlength="120" autocomplete="off">
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
                    const modal = document.getElementById('gross-attr-edit-modal');
                    if (!modal) return;
                    const form = document.getElementById('gross-attr-edit-form');
                    const kindLabel = document.getElementById('gross-attr-edit-kind-label');
                    const nameAr = document.getElementById('gross-attr-edit-name-ar');
                    const nameEn = document.getElementById('gross-attr-edit-name-en');
                    const kindNames = {
                        COLOR: @json(__('gross.attributes.kinds.COLOR')),
                        SIZE: @json(__('gross.attributes.kinds.SIZE')),
                        MODEL: @json(__('gross.attributes.kinds.MODEL')),
                    };
                    modal.addEventListener('show.bs.modal', function (event) {
                        const btn = event.relatedTarget;
                        if (!btn) return;
                        form.setAttribute('action', btn.getAttribute('data-action') || '');
                        kindLabel.textContent = kindNames[btn.getAttribute('data-kind')] || '';
                        nameAr.value = btn.getAttribute('data-name-ar') || '';
                        nameEn.value = btn.getAttribute('data-name-en') || '';
                    });
                })();
            </script>
        @endpush
    @endcan
@endsection
