<?php

/** @var \Illuminate\Pagination\LengthAwarePaginator<\App\Models\Gross\GrossReceiptPurpose> $purposes */
/** @var array{q: string, status: string} $filters */
/** @var array{total: int, active: int, in_use: int} $summary */
?>
@extends('layouts.app')

@section('title', __('gross.receipt_purposes.title'))

@section('content')
    @php
        $locale = app()->getLocale();
        $hasFilters = ($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '';
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.receipt_purposes.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.receipt_purposes.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\Gross\GrossTreasuryVoucher::class)
                <a href="{{ route('gross.treasury.create', ['type' => 'RECEIPT', 'purpose' => 'OTHER']) }}"
                   class="btn btn-primary btn-sm">
                    {{ __('gross.treasury.create_receipt') }}
                </a>
            @endcan
            <a href="{{ route('gross.expense-types.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.expense_types.title') }}</a>
            <a href="{{ route('gross.treasury.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.treasury.title') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-4">
            <x-stat-card :title="__('gross.receipt_purposes.stats_total')" :value="$summary['total']" icon="bi-cash-coin" />
        </div>
        <div class="col-sm-4">
            <x-stat-card :title="__('gross.receipt_purposes.stats_active')" :value="$summary['active']" icon="bi-check-circle" color="success" />
        </div>
        <div class="col-sm-4">
            <x-stat-card :title="__('gross.receipt_purposes.stats_in_use')" :value="$summary['in_use']" icon="bi-wallet2" color="info" />
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

    @can('create', App\Models\Gross\GrossReceiptPurpose::class)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">{{ __('gross.receipt_purposes.add_title') }}</h2>
                <form method="post" action="{{ route('gross.receipt-purposes.store') }}" class="row g-2 align-items-end">
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
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gross.receipt_purposes.create') }}</button>
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
                           placeholder="{{ __('gross.receipt_purposes.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label class="form-label mb-1" for="status">{{ __('gross.receipt_purposes.status_filter') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('gross.statuses.ACTIVE') }}</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('gross.statuses.INACTIVE') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasFilters)
                        <a href="{{ route('gross.receipt-purposes.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            @if ($purposes->isEmpty())
                <x-empty-state :title="__('gross.receipt_purposes.empty')" :text="__('gross.receipt_purposes.empty_hint')" icon="bi-cash-coin" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.name') }}</th>
                                <th>{{ __('gross.fields.status') }}</th>
                                <th class="col-num">{{ __('gross.receipt_purposes.vouchers') }}</th>
                                <th class="text-end" style="width: 8rem">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purposes as $purpose)
                                @php
                                    $primary = $locale === 'ar' ? $purpose->name_ar : $purpose->name_en;
                                    $secondary = $locale === 'ar' ? $purpose->name_en : $purpose->name_ar;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $primary }}</div>
                                        <div class="small text-body-secondary">{{ $secondary }}</div>
                                    </td>
                                    <td>
                                        @if ($purpose->is_active)
                                            <span class="badge text-bg-success">{{ __('gross.statuses.ACTIVE') }}</span>
                                        @else
                                            <span class="badge text-bg-secondary">{{ __('gross.statuses.INACTIVE') }}</span>
                                        @endif
                                        @if ($purpose->vouchers_count > 0)
                                            <span class="badge text-bg-info ms-1">{{ __('gross.receipt_purposes.in_use') }}</span>
                                        @endif
                                    </td>
                                    <td class="col-num">
                                        @if ($purpose->vouchers_count > 0)
                                            <a href="{{ route('gross.treasury.index', ['purpose' => 'OTHER', 'receipt_purpose_id' => $purpose->id]) }}"
                                               class="num-ltr link-body-emphasis">
                                                {{ $purpose->vouchers_count }}
                                            </a>
                                        @else
                                            <span class="num-ltr text-body-secondary">0</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if ($purpose->is_active)
                                            @can('create', App\Models\Gross\GrossTreasuryVoucher::class)
                                                <a href="{{ route('gross.treasury.create', ['type' => 'RECEIPT', 'purpose' => 'OTHER', 'receipt_purpose_id' => $purpose->id]) }}"
                                                   class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                                   title="{{ __('gross.receipt_purposes.create_receipt') }}">
                                                    <i class="bi bi-cash-stack" aria-hidden="true"></i>
                                                    <span class="visually-hidden">{{ __('gross.receipt_purposes.create_receipt') }}</span>
                                                </a>
                                            @endcan
                                        @endif
                                        @can('update', $purpose)
                                            <button type="button"
                                                    class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                                    title="{{ __('general.edit') }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#gross-receipt-purpose-edit-modal"
                                                    data-name-ar="{{ $purpose->name_ar }}"
                                                    data-name-en="{{ $purpose->name_en }}"
                                                    data-is-active="{{ $purpose->is_active ? '1' : '0' }}"
                                                    data-action="{{ route('gross.receipt-purposes.update', $purpose) }}?{{ http_build_query(array_filter(['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? ''])) }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                <span class="visually-hidden">{{ __('general.edit') }}</span>
                                            </button>
                                        @endcan
                                        @can('delete', $purpose)
                                            <form method="post" action="{{ route('gross.receipt-purposes.destroy', $purpose) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm(@json(__('gross.receipt_purposes.confirm_delete')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-link btn-sm p-0 {{ $purpose->vouchers_count > 0 ? 'text-body-secondary' : 'text-danger' }}"
                                                        title="{{ $purpose->vouchers_count > 0 ? __('gross.errors.receipt_purpose_in_use') : __('general.delete') }}"
                                                        @disabled($purpose->vouchers_count > 0)>
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
                <div class="mt-3">{{ $purposes->links() }}</div>
            @endif
        </div>
    </div>

    @can('create', App\Models\Gross\GrossReceiptPurpose::class)
        <div class="modal fade" id="gross-receipt-purpose-edit-modal" tabindex="-1" aria-labelledby="gross-receipt-purpose-edit-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="post" id="gross-receipt-purpose-edit-form" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header py-2">
                        <h2 class="modal-title h6 mb-0" id="gross-receipt-purpose-edit-title">{{ __('gross.receipt_purposes.edit') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="gross-receipt-purpose-edit-name-ar">{{ __('gross.fields.name_ar') }}</label>
                            <input id="gross-receipt-purpose-edit-name-ar" name="name_ar" class="form-control" required maxlength="120" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gross-receipt-purpose-edit-name-en">{{ __('gross.fields.name_en') }}</label>
                            <input id="gross-receipt-purpose-edit-name-en" name="name_en" class="form-control" required maxlength="120" autocomplete="off">
                        </div>
                        <div class="mb-0">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" id="gross-receipt-purpose-edit-active" value="1">
                                <label class="form-check-label" for="gross-receipt-purpose-edit-active">{{ __('gross.statuses.ACTIVE') }}</label>
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
                    const modal = document.getElementById('gross-receipt-purpose-edit-modal');
                    if (!modal) return;
                    const form = document.getElementById('gross-receipt-purpose-edit-form');
                    const nameAr = document.getElementById('gross-receipt-purpose-edit-name-ar');
                    const nameEn = document.getElementById('gross-receipt-purpose-edit-name-en');
                    const active = document.getElementById('gross-receipt-purpose-edit-active');
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
