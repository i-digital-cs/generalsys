@extends('layouts.app')

@section('title', __('gross.suppliers.title'))

@section('content')
    @php($hasFilters = ($filters['q'] ?? '') !== '')

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.suppliers.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.suppliers.subtitle') }}</p>
        </div>
        @can('create', App\Models\Gross\GrossSupplier::class)
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('gross.suppliers.template') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('gross.suppliers.template') }}
                </a>
                <a href="{{ route('gross.suppliers.create') }}" class="btn btn-primary btn-sm">{{ __('gross.suppliers.create') }}</a>
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
                           placeholder="{{ __('gross.suppliers.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasFilters)
                        <a href="{{ route('gross.suppliers.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            @if ($suppliers->isEmpty())
                <x-empty-state :title="__('gross.suppliers.empty')" icon="bi-truck-flatbed" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.name') }}</th>
                                <th>{{ __('gross.fields.phone') }}</th>
                                <th>{{ __('gross.fields.address') }}</th>
                                <th class="col-num">{{ __('gross.suppliers.list_balance') }}</th>
                                <th class="text-end" style="min-width: 9rem">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($suppliers as $supplier)
                                @php($balance = $balances[$supplier->id] ?? '0.00')
                                <tr>
                                    <td class="fw-semibold">
                                        <a href="{{ route('gross.suppliers.show', $supplier) }}" class="link-body-emphasis text-decoration-none">
                                            {{ $supplier->name }}
                                        </a>
                                    </td>
                                    <td><span class="num-ltr">{{ $supplier->phone }}</span></td>
                                    <td class="small text-body-secondary">{{ $supplier->address }}</td>
                                    <td class="col-num">
                                        <span class="num-ltr"><x-money :amount="$balance" currency="EGP" /></span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @can('view', $supplier)
                                            <a href="{{ route('gross.suppliers.statement', $supplier) }}"
                                               class="btn btn-outline-primary btn-sm me-1"
                                               title="{{ __('gross.suppliers.statement') }}">
                                                <i class="bi bi-journal-text me-1" aria-hidden="true"></i>{{ __('gross.suppliers.statement') }}
                                            </a>
                                        @endcan
                                        @can('update', $supplier)
                                            <button type="button"
                                                    class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                                    title="{{ __('general.edit') }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#gross-supplier-edit-modal"
                                                    data-name="{{ $supplier->name }}"
                                                    data-phone="{{ $supplier->phone }}"
                                                    data-address="{{ $supplier->address }}"
                                                    data-notes="{{ $supplier->notes }}"
                                                    data-action="{{ route('gross.suppliers.update', $supplier) }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </button>
                                        @endcan
                                        @can('delete', $supplier)
                                            <form method="post" action="{{ route('gross.suppliers.destroy', $supplier) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm(@json(__('gross.suppliers.confirm_delete')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="{{ __('general.delete') }}">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $suppliers->links() }}</div>
            @endif
        </div>
    </div>

    @can('create', App\Models\Gross\GrossSupplier::class)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h2 class="h6 mb-1">{{ __('gross.suppliers.import_title') }}</h2>
                <p class="small text-body-secondary mb-2">{{ __('gross.suppliers.import_help') }}</p>
                <form method="post" action="{{ route('gross.suppliers.import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label mb-1" for="supplier_file">{{ __('gross.suppliers.import_file') }}</label>
                        <input id="supplier_file" name="file" type="file" class="form-control form-control-sm" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="col-md-4 col-lg-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">{{ __('gross.suppliers.import') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="gross-supplier-edit-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="post" id="gross-supplier-edit-form" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header py-2">
                        <h2 class="modal-title h6 mb-0">{{ __('gross.suppliers.edit') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="gross-supplier-edit-name">{{ __('gross.fields.name') }}</label>
                            <input id="gross-supplier-edit-name" name="name" list="gross-suggest-supplier-name-modal"
                                   class="form-control" required maxlength="191" autocomplete="off">
                            <datalist id="gross-suggest-supplier-name-modal">
                                @foreach (($nameSuggestions ?? []) as $value)
                                    <option value="{{ $value }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gross-supplier-edit-phone">{{ __('gross.fields.phone') }}</label>
                            <input id="gross-supplier-edit-phone" name="phone" class="form-control" required maxlength="40">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gross-supplier-edit-address">{{ __('gross.fields.address') }}</label>
                            <textarea id="gross-supplier-edit-address" name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="gross-supplier-edit-notes">{{ __('gross.fields.notes') }}</label>
                            <textarea id="gross-supplier-edit-notes" name="notes" class="form-control" rows="2"></textarea>
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
                    const modal = document.getElementById('gross-supplier-edit-modal');
                    if (!modal) return;
                    const form = document.getElementById('gross-supplier-edit-form');
                    modal.addEventListener('show.bs.modal', function (event) {
                        const btn = event.relatedTarget;
                        if (!btn) return;
                        form.setAttribute('action', btn.getAttribute('data-action') || '');
                        document.getElementById('gross-supplier-edit-name').value = btn.getAttribute('data-name') || '';
                        document.getElementById('gross-supplier-edit-phone').value = btn.getAttribute('data-phone') || '';
                        document.getElementById('gross-supplier-edit-address').value = btn.getAttribute('data-address') || '';
                        document.getElementById('gross-supplier-edit-notes').value = btn.getAttribute('data-notes') || '';
                    });
                })();
            </script>
        @endpush
    @endcan
@endsection
