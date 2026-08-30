@extends('layouts.app')

@section('title', __('gross.customers.title'))

@section('content')
    @php
        $hasFilters = ($filters['q'] ?? '') !== '';
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.customers.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.customers.subtitle') }}</p>
        </div>
        @can('create', App\Models\Gross\GrossCustomer::class)
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('gross.customers.template') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('gross.customers.template') }}
                </a>
                <a href="{{ route('gross.customers.create') }}" class="btn btn-primary btn-sm">{{ __('gross.customers.create') }}</a>
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
                           placeholder="{{ __('gross.customers.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasFilters)
                        <a href="{{ route('gross.customers.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            @if ($customers->isEmpty())
                <x-empty-state :title="__('gross.customers.empty')" icon="bi-people" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.name') }}</th>
                                <th>{{ __('gross.fields.phone') }}</th>
                                <th>{{ __('gross.fields.address') }}</th>
                                <th class="text-end" style="width: 6rem">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $customer)
                                <tr>
                                    <td class="fw-semibold">
                                        <a href="{{ route('gross.customers.show', $customer) }}" class="link-body-emphasis text-decoration-none">
                                            {{ $customer->name }}
                                        </a>
                                    </td>
                                    <td><span class="num-ltr">{{ $customer->phone }}</span></td>
                                    <td class="text-body-secondary small">{{ $customer->address }}</td>
                                    <td class="text-end text-nowrap">
                                        @can('update', $customer)
                                            <button type="button"
                                                    class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                                    title="{{ __('general.edit') }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#gross-customer-edit-modal"
                                                    data-name="{{ $customer->name }}"
                                                    data-phone="{{ $customer->phone }}"
                                                    data-address="{{ $customer->address }}"
                                                    data-action="{{ route('gross.customers.update', $customer) }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                <span class="visually-hidden">{{ __('general.edit') }}</span>
                                            </button>
                                        @endcan
                                        @can('delete', $customer)
                                            <form method="post" action="{{ route('gross.customers.destroy', $customer) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm(@json(__('gross.customers.confirm_delete')))">
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
                <div class="mt-3">{{ $customers->links() }}</div>
            @endif
        </div>
    </div>

    @can('create', App\Models\Gross\GrossCustomer::class)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <h2 class="h6 mb-1">{{ __('gross.customers.import_title') }}</h2>
                        <p class="small text-body-secondary mb-0">{{ __('gross.customers.import_help') }}</p>
                    </div>
                    <a href="{{ route('gross.customers.template') }}" class="btn btn-link btn-sm px-0">
                        {{ __('gross.customers.template') }}
                    </a>
                </div>
                <form method="post" action="{{ route('gross.customers.import') }}" enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label mb-1" for="customer_file">{{ __('gross.customers.import_file') }}</label>
                        <input id="customer_file" name="file" type="file" class="form-control form-control-sm"
                               accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="col-md-4 col-lg-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('gross.customers.import') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="gross-customer-edit-modal" tabindex="-1" aria-labelledby="gross-customer-edit-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="post" id="gross-customer-edit-form" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header py-2">
                        <h2 class="modal-title h6 mb-0" id="gross-customer-edit-title">{{ __('gross.customers.edit') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="gross-customer-edit-name">{{ __('gross.fields.name') }}</label>
                            <input id="gross-customer-edit-name" name="name" list="gross-suggest-customer-name-modal"
                                   class="form-control" required maxlength="191" autocomplete="off"
                                   placeholder="{{ __('gross.customers.name_suggest_hint') }}">
                            <datalist id="gross-suggest-customer-name-modal">
                                @foreach (($nameSuggestions ?? []) as $value)
                                    <option value="{{ $value }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gross-customer-edit-phone">{{ __('gross.fields.phone') }}</label>
                            <input id="gross-customer-edit-phone" name="phone" class="form-control" required maxlength="40" autocomplete="off">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="gross-customer-edit-address">{{ __('gross.fields.address') }}</label>
                            <textarea id="gross-customer-edit-address" name="address" class="form-control" rows="2"></textarea>
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
                    const modal = document.getElementById('gross-customer-edit-modal');
                    if (!modal) return;
                    const form = document.getElementById('gross-customer-edit-form');
                    const name = document.getElementById('gross-customer-edit-name');
                    const phone = document.getElementById('gross-customer-edit-phone');
                    const address = document.getElementById('gross-customer-edit-address');
                    modal.addEventListener('show.bs.modal', function (event) {
                        const btn = event.relatedTarget;
                        if (!btn) return;
                        form.setAttribute('action', btn.getAttribute('data-action') || '');
                        name.value = btn.getAttribute('data-name') || '';
                        phone.value = btn.getAttribute('data-phone') || '';
                        address.value = btn.getAttribute('data-address') || '';
                    });
                })();
            </script>
        @endpush
    @endcan
@endsection
