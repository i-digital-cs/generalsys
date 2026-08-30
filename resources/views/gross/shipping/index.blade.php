@extends('layouts.app')

@section('title', __('gross.shipping.title'))

@section('content')
    @php
        $hasFilters = ($filters['q'] ?? '') !== '';
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.shipping.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.shipping.subtitle') }}</p>
        </div>
        @can('create', App\Models\Gross\GrossShippingCompany::class)
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('gross.shipping.template') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('gross.shipping.template') }}
                </a>
                <a href="{{ route('gross.shipping.create') }}" class="btn btn-primary btn-sm">{{ __('gross.shipping.create') }}</a>
            </div>
        @endcan
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

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-6 col-lg-4">
                    <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"
                           placeholder="{{ __('gross.shipping.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasFilters)
                        <a href="{{ route('gross.shipping.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            @if ($companies->isEmpty())
                <x-empty-state :title="__('gross.shipping.empty')" icon="bi-truck" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.name') }}</th>
                                <th>{{ __('gross.fields.phone') }}</th>
                                <th>{{ __('gross.fields.status') }}</th>
                                <th class="text-end" style="width: 6rem">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($companies as $company)
                                <tr>
                                    <td class="fw-semibold">
                                        <a href="{{ route('gross.shipping.show', $company) }}" class="link-body-emphasis text-decoration-none">
                                            {{ $company->name }}
                                        </a>
                                    </td>
                                    <td><span class="num-ltr">{{ $company->phone }}</span></td>
                                    <td>{{ __('gross.statuses.'.$company->status) }}</td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('gross.shipping.show', $company) }}"
                                           class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                           title="{{ __('general.view') }}">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                            <span class="visually-hidden">{{ __('general.view') }}</span>
                                        </a>
                                        @can('update', $company)
                                            <button type="button"
                                                    class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                                    title="{{ __('general.edit') }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#gross-shipping-edit-modal"
                                                    data-name="{{ $company->name }}"
                                                    data-phone="{{ $company->phone }}"
                                                    data-address="{{ $company->address }}"
                                                    data-notes="{{ $company->notes }}"
                                                    data-status="{{ $company->status }}"
                                                    data-action="{{ route('gross.shipping.update', $company) }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                <span class="visually-hidden">{{ __('general.edit') }}</span>
                                            </button>
                                        @endcan
                                        @can('delete', $company)
                                            <form method="post" action="{{ route('gross.shipping.destroy', $company) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm(@json(__('gross.shipping.confirm_delete')))">
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
                <div class="mt-3">{{ $companies->links() }}</div>
            @endif
        </div>
    </div>

    @can('create', App\Models\Gross\GrossShippingCompany::class)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <h2 class="h6 mb-1">{{ __('gross.shipping.import_title') }}</h2>
                        <p class="small text-body-secondary mb-0">{{ __('gross.shipping.import_help') }}</p>
                    </div>
                    <a href="{{ route('gross.shipping.template') }}" class="btn btn-link btn-sm px-0">
                        {{ __('gross.shipping.template') }}
                    </a>
                </div>
                <form method="post" action="{{ route('gross.shipping.import') }}" enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label mb-1" for="shipping_file">{{ __('gross.shipping.import_file') }}</label>
                        <input id="shipping_file" name="file" type="file" class="form-control form-control-sm"
                               accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="col-md-4 col-lg-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('gross.shipping.import') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="gross-shipping-edit-modal" tabindex="-1" aria-labelledby="gross-shipping-edit-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="post" id="gross-shipping-edit-form" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header py-2">
                        <h2 class="modal-title h6 mb-0" id="gross-shipping-edit-title">{{ __('gross.shipping.edit') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="gross-shipping-edit-name">{{ __('gross.fields.name') }}</label>
                            <input id="gross-shipping-edit-name" name="name" list="gross-suggest-shipping-name-modal"
                                   class="form-control" required maxlength="191" autocomplete="off"
                                   placeholder="{{ __('gross.shipping.name_suggest_hint') }}">
                            <datalist id="gross-suggest-shipping-name-modal">
                                @foreach (($nameSuggestions ?? []) as $value)
                                    <option value="{{ $value }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gross-shipping-edit-phone">{{ __('gross.fields.phone') }}</label>
                            <input id="gross-shipping-edit-phone" name="phone" class="form-control" maxlength="40" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gross-shipping-edit-address">{{ __('gross.fields.address') }}</label>
                            <textarea id="gross-shipping-edit-address" name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gross-shipping-edit-notes">{{ __('gross.fields.notes') }}</label>
                            <textarea id="gross-shipping-edit-notes" name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="gross-shipping-edit-status">{{ __('gross.fields.status') }}</label>
                            <select id="gross-shipping-edit-status" name="status" class="form-select" required>
                                @foreach (['ACTIVE', 'INACTIVE'] as $st)
                                    <option value="{{ $st }}">{{ __('gross.statuses.'.$st) }}</option>
                                @endforeach
                            </select>
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
                    const modal = document.getElementById('gross-shipping-edit-modal');
                    if (!modal) return;
                    const form = document.getElementById('gross-shipping-edit-form');
                    const name = document.getElementById('gross-shipping-edit-name');
                    const phone = document.getElementById('gross-shipping-edit-phone');
                    const address = document.getElementById('gross-shipping-edit-address');
                    const notes = document.getElementById('gross-shipping-edit-notes');
                    const status = document.getElementById('gross-shipping-edit-status');
                    modal.addEventListener('show.bs.modal', function (event) {
                        const btn = event.relatedTarget;
                        if (!btn) return;
                        form.setAttribute('action', btn.getAttribute('data-action') || '');
                        name.value = btn.getAttribute('data-name') || '';
                        phone.value = btn.getAttribute('data-phone') || '';
                        address.value = btn.getAttribute('data-address') || '';
                        notes.value = btn.getAttribute('data-notes') || '';
                        status.value = btn.getAttribute('data-status') || 'ACTIVE';
                    });
                })();
            </script>
        @endpush
    @endcan
@endsection
