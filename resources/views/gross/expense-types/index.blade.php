@extends('layouts.app')

@section('title', __('gross.expense_types.title'))

@section('content')
    @php
        $locale = app()->getLocale();
        $hasFilters = ($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '';
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.expense_types.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.expense_types.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\Gross\GrossTreasuryVoucher::class)
                <a href="{{ route('gross.treasury.create', ['type' => 'PAYMENT', 'purpose' => 'EXPENSE']) }}"
                   class="btn btn-primary btn-sm">
                    {{ __('gross.treasury.create_payment') }}
                </a>
            @endcan
            @can('viewAny', App\Models\Gross\GrossReceiptPurpose::class)
                <a href="{{ route('gross.receipt-purposes.index') }}" class="btn btn-outline-primary btn-sm">
                    {{ __('gross.receipt_purposes.title') }}
                </a>
            @endcan
            <a href="{{ route('gross.treasury.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.treasury.title') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-4">
            <x-stat-card :title="__('gross.expense_types.stats_total')" :value="$summary['total']" icon="bi-tags" />
        </div>
        <div class="col-sm-4">
            <x-stat-card :title="__('gross.expense_types.stats_active')" :value="$summary['active']" icon="bi-check-circle" color="success" />
        </div>
        <div class="col-sm-4">
            <x-stat-card :title="__('gross.expense_types.stats_in_use')" :value="$summary['in_use']" icon="bi-wallet2" color="info" />
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

    @can('create', App\Models\Gross\GrossExpenseType::class)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">{{ __('gross.expense_types.add_title') }}</h2>
                <form method="post" action="{{ route('gross.expense-types.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label mb-1" for="name_ar">{{ __('gross.fields.name_ar') }}</label>
                        <input id="name_ar" name="name_ar" class="form-control form-control-sm @error('name_ar') is-invalid @enderror"
                               value="{{ old('name_ar') }}" required maxlength="120" autocomplete="off">
                        @error('name_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1" for="name_en">{{ __('gross.fields.name_en') }}</label>
                        <input id="name_en" name="name_en" class="form-control form-control-sm @error('name_en') is-invalid @enderror"
                               value="{{ old('name_en') }}" required maxlength="120" autocomplete="off">
                        @error('name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mt-4">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', '1') === '1')>
                            <label class="form-check-label" for="is_active">{{ __('gross.statuses.ACTIVE') }}</label>
                        </div>
                    </div>
                    <div class="col-md-2 col-lg-auto">
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gross.expense_types.create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-5 col-lg-4">
                    <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"
                           placeholder="{{ __('gross.expense_types.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label class="form-label mb-1" for="status">{{ __('gross.expense_types.status_filter') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('gross.statuses.ACTIVE') }}</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('gross.statuses.INACTIVE') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasFilters)
                        <a href="{{ route('gross.expense-types.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            @if ($types->isEmpty())
                <x-empty-state :title="__('gross.expense_types.empty')" :text="__('gross.expense_types.empty_hint')" icon="bi-tags" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.name') }}</th>
                                <th>{{ __('gross.fields.status') }}</th>
                                <th class="col-num">{{ __('gross.expense_types.vouchers') }}</th>
                                <th class="text-end" style="width: 8rem">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($types as $type)
                                @php
                                    $primary = $locale === 'ar' ? $type->name_ar : $type->name_en;
                                    $secondary = $locale === 'ar' ? $type->name_en : $type->name_ar;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $primary }}</div>
                                        <div class="small text-body-secondary">{{ $secondary }}</div>
                                    </td>
                                    <td>
                                        @if ($type->is_active)
                                            <span class="badge text-bg-success">{{ __('gross.statuses.ACTIVE') }}</span>
                                        @else
                                            <span class="badge text-bg-secondary">{{ __('gross.statuses.INACTIVE') }}</span>
                                        @endif
                                        @if ($type->vouchers_count > 0)
                                            <span class="badge text-bg-info ms-1">{{ __('gross.expense_types.in_use') }}</span>
                                        @endif
                                    </td>
                                    <td class="col-num">
                                        @if ($type->vouchers_count > 0)
                                            <a href="{{ route('gross.treasury.index', ['purpose' => 'EXPENSE', 'expense_type_id' => $type->id]) }}"
                                               class="num-ltr link-body-emphasis">
                                                {{ $type->vouchers_count }}
                                            </a>
                                        @else
                                            <span class="num-ltr text-body-secondary">0</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if ($type->is_active)
                                            @can('create', App\Models\Gross\GrossTreasuryVoucher::class)
                                                <a href="{{ route('gross.treasury.create', ['type' => 'PAYMENT', 'purpose' => 'EXPENSE', 'expense_type_id' => $type->id]) }}"
                                                   class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                                   title="{{ __('gross.expense_types.create_payment') }}">
                                                    <i class="bi bi-cash-stack" aria-hidden="true"></i>
                                                    <span class="visually-hidden">{{ __('gross.expense_types.create_payment') }}</span>
                                                </a>
                                            @endcan
                                        @endif
                                        @can('update', $type)
                                            <button type="button"
                                                    class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                                    title="{{ __('general.edit') }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#gross-expense-type-edit-modal"
                                                    data-name-ar="{{ $type->name_ar }}"
                                                    data-name-en="{{ $type->name_en }}"
                                                    data-is-active="{{ $type->is_active ? '1' : '0' }}"
                                                    data-action="{{ route('gross.expense-types.update', $type) }}?{{ http_build_query(array_filter(['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? ''])) }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                <span class="visually-hidden">{{ __('general.edit') }}</span>
                                            </button>
                                        @endcan
                                        @can('delete', $type)
                                            <form method="post" action="{{ route('gross.expense-types.destroy', $type) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm(@json(__('gross.expense_types.confirm_delete')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-link btn-sm p-0 {{ $type->vouchers_count > 0 ? 'text-body-secondary' : 'text-danger' }}"
                                                        title="{{ $type->vouchers_count > 0 ? __('gross.errors.expense_type_in_use') : __('general.delete') }}"
                                                        @disabled($type->vouchers_count > 0)>
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
                <div class="mt-3">{{ $types->links() }}</div>
            @endif
        </div>
    </div>

    @can('create', App\Models\Gross\GrossExpenseType::class)
        <div class="modal fade" id="gross-expense-type-edit-modal" tabindex="-1" aria-labelledby="gross-expense-type-edit-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="post" id="gross-expense-type-edit-form" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header py-2">
                        <h2 class="modal-title h6 mb-0" id="gross-expense-type-edit-title">{{ __('gross.expense_types.edit') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="gross-expense-type-edit-name-ar">{{ __('gross.fields.name_ar') }}</label>
                            <input id="gross-expense-type-edit-name-ar" name="name_ar" class="form-control" required maxlength="120" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gross-expense-type-edit-name-en">{{ __('gross.fields.name_en') }}</label>
                            <input id="gross-expense-type-edit-name-en" name="name_en" class="form-control" required maxlength="120" autocomplete="off">
                        </div>
                        <div class="mb-0">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" id="gross-expense-type-edit-active" value="1">
                                <label class="form-check-label" for="gross-expense-type-edit-active">{{ __('gross.statuses.ACTIVE') }}</label>
                            </div>
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
                    const modal = document.getElementById('gross-expense-type-edit-modal');
                    if (!modal) return;
                    const form = document.getElementById('gross-expense-type-edit-form');
                    const nameAr = document.getElementById('gross-expense-type-edit-name-ar');
                    const nameEn = document.getElementById('gross-expense-type-edit-name-en');
                    const active = document.getElementById('gross-expense-type-edit-active');
                    modal.addEventListener('show.bs.modal', function (event) {
                        const btn = event.relatedTarget;
                        if (!btn) return;
                        form.setAttribute('action', btn.getAttribute('data-action') || '');
                        nameAr.value = btn.getAttribute('data-name-ar') || '';
                        nameEn.value = btn.getAttribute('data-name-en') || '';
                        active.checked = btn.getAttribute('data-is-active') === '1';
                    });
                })();
            </script>
        @endpush
    @endcan
@endsection
