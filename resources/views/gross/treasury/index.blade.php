@extends('layouts.app')

@section('title', __('gross.treasury.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.treasury.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.treasury.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\Gross\GrossTreasuryVoucher::class)
                <a href="{{ route('gross.treasury.create', ['type' => 'RECEIPT']) }}" class="btn btn-primary">{{ __('gross.treasury.create_receipt') }}</a>
                <a href="{{ route('gross.treasury.create', ['type' => 'PAYMENT']) }}" class="btn btn-outline-primary">{{ __('gross.treasury.create_payment') }}</a>
            @endcan
            @can('viewAny', App\Models\Gross\GrossExpenseType::class)
                <a href="{{ route('gross.expense-types.index') }}" class="btn btn-outline-secondary">{{ __('gross.expense_types.title') }}</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ($accounts as $row)
            <div class="col-md-4">
                <x-stat-card
                    :title="__('gross.treasury.kinds.'.$row['account']->kind)"
                    :subtitle="$row['account']->name.' · '.$row['account']->currency"
                    :icon="$row['account']->kind === \App\Models\Gross\GrossTreasuryAccount::KIND_BANK ? 'bi-bank' : 'bi-cash-coin'"
                    :color="$row['account']->kind === \App\Models\Gross\GrossTreasuryAccount::KIND_BANK ? 'info' : 'success'"
                    :show-sparkline="false"
                >
                    <span class="num-ltr"><x-money :amount="$row['balance']" :currency="$row['account']->currency" /></span>
                    @if (! $row['account']->is_active)
                        <span class="d-block small text-danger mt-1">{{ __('gross.statuses.INACTIVE') }}</span>
                    @endif
                </x-stat-card>
            </div>
        @endforeach
        @foreach ($combinedTotals as $total)
            <div class="col-md-4">
                <x-stat-card
                    :title="__('gross.treasury.total_cash_bank')"
                    :subtitle="__('gross.treasury.total_cash_bank_hint', ['currency' => $total['currency']])"
                    icon="bi-wallet2"
                    color="primary"
                    :show-sparkline="false"
                >
                    <span class="num-ltr"><x-money :amount="$total['balance']" :currency="$total['currency']" /></span>
                </x-stat-card>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            @php
                $hasTreasuryFilters = ($filters['type'] ?? '') !== ''
                    || ($filters['purpose'] ?? '') !== ''
                    || ($filters['account_id'] ?? '') !== ''
                    || ($filters['expense_type_id'] ?? '') !== '';
            @endphp
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label mb-1" for="type">{{ __('gross.fields.type') }}</label>
                    <select id="type" name="type" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        <option value="PAYMENT" @selected(($filters['type'] ?? '') === 'PAYMENT')>{{ __('gross.treasury.types.PAYMENT') }}</option>
                        <option value="RECEIPT" @selected(($filters['type'] ?? '') === 'RECEIPT')>{{ __('gross.treasury.types.RECEIPT') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1" for="purpose">{{ __('gross.treasury.purpose') }}</label>
                    <select id="purpose" name="purpose" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        <option value="EXPENSE" @selected(($filters['purpose'] ?? '') === 'EXPENSE')>{{ __('gross.treasury.purposes.EXPENSE') }}</option>
                        <option value="SUPPLIER" @selected(($filters['purpose'] ?? '') === 'SUPPLIER')>{{ __('gross.treasury.purposes.SUPPLIER') }}</option>
                        <option value="COURIER_COD" @selected(($filters['purpose'] ?? '') === 'COURIER_COD')>{{ __('gross.treasury.purposes.COURIER_COD') }}</option>
                        <option value="OTHER" @selected(($filters['purpose'] ?? '') === 'OTHER')>{{ __('gross.treasury.purposes.OTHER') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1" for="expense_type_id">{{ __('gross.treasury.expense_type') }}</label>
                    <select id="expense_type_id" name="expense_type_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($filterExpenseTypes as $expenseType)
                            <option value="{{ $expenseType->id }}" @selected((string) ($filters['expense_type_id'] ?? '') === (string) $expenseType->id)>
                                {{ $expenseType->localizedName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1" for="account_id">{{ __('gross.treasury.account') }}</label>
                    <select id="account_id" name="account_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($filterAccounts as $account)
                            <option value="{{ $account->id }}" @selected((string) ($filters['account_id'] ?? '') === (string) $account->id)>
                                {{ __('gross.treasury.kinds.'.$account->kind) }} ({{ $account->currency }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasTreasuryFilters)
                        <a href="{{ route('gross.treasury.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if ($vouchers->isEmpty())
                <x-empty-state :title="__('gross.treasury.empty')" icon="bi-wallet2" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.treasury.voucher_number') }}</th>
                                <th>{{ __('gross.fields.date') }}</th>
                                <th>{{ __('gross.fields.type') }}</th>
                                <th>{{ __('gross.treasury.purpose') }}</th>
                                <th>{{ __('gross.treasury.account') }}</th>
                                <th>{{ __('gross.treasury.counterparty') }}</th>
                                <th class="col-num">{{ __('gross.fields.amount') }}</th>
                                <th class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vouchers as $voucher)
                                <tr>
                                    <td class="fw-semibold"><span class="num-ltr">{{ $voucher->voucher_number }}</span></td>
                                    <td class="small"><span class="num-ltr">{{ $voucher->voucher_date->translatedFormat('d M Y') }}</span></td>
                                    <td>{{ __('gross.treasury.types.'.$voucher->type) }}</td>
                                    <td>{{ __('gross.treasury.purposes.'.$voucher->purpose) }}</td>
                                    <td>{{ __('gross.treasury.kinds.'.$voucher->account->kind) }}</td>
                                    <td>
                                        @if ($voucher->isSupplierPurpose())
                                            {{ $voucher->supplier?->name ?? '—' }}
                                        @else
                                            {{ $voucher->expenseType?->localizedName() ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="col-num">
                                        <span class="num-ltr"><x-money :amount="$voucher->amount" :currency="$voucher->currency" /></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('gross.treasury.show', $voucher) }}" class="btn btn-sm btn-outline-primary">{{ __('general.view') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $vouchers->links() }}</div>
            @endif
        </div>
    </div>
@endsection
