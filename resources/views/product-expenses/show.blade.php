@extends('layouts.app')

@section('title', $detail['expenseType']->localizedName().' — '.__('product_expenses.title'))

@section('content')
    @php
        $expenseType = $detail['expenseType'];
        $currency = $detail['currency'];
        $year = $detail['year'];
        $month = $detail['month'];
        $accountsWithBalances = $detail['accountsWithBalances'];
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $expenseType->localizedName() }}</h1>
            <p class="text-body-secondary mb-0">
                {{ __('product_expenses.title') }}
                <span class="num-ltr">· {{ $detail['month_label'] }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($canManage)
                <a class="btn btn-outline-primary btn-sm" href="{{ route('gross.expense-types.index') }}">
                    {{ __('product_expenses.manage_categories') }}
                </a>
                <a class="btn btn-outline-primary btn-sm"
                   href="{{ route('gross.treasury.create', ['type' => 'PAYMENT', 'purpose' => 'EXPENSE', 'expense_type_id' => $expenseType->id]) }}">
                    {{ __('product_expenses.open_treasury') }}
                </a>
            @endif
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('portal.product') }}">
                {{ __('product_expenses.back_dashboard') }}
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('product.expenses.show', $expenseType) }}" class="row g-2 align-items-end mb-3">
        <div class="col-auto">
            <label class="form-label" for="exp_year">{{ __('product_expenses.year') }}</label>
            <input class="form-control form-control-sm" dir="ltr" type="number" name="year" id="exp_year" value="{{ $year }}" min="2000" max="2100">
        </div>
        <div class="col-auto">
            <label class="form-label" for="exp_month">{{ __('product_expenses.month') }}</label>
            <input class="form-control form-control-sm" dir="ltr" type="number" name="month" id="exp_month" value="{{ $month }}" min="1" max="12">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">{{ __('general.filter') }}</button>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-4">
            <x-stat-card :title="__('product_expenses.expected')" icon="bi-bullseye" color="info">
                <x-money :amount="$detail['expected']" :currency="$currency" />
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-4">
            <x-stat-card :title="__('product_expenses.spent')" icon="bi-cash" color="warning">
                <x-money :amount="$detail['spent']" :currency="$currency" />
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-4">
            <x-stat-card
                :title="__('product_expenses.remaining')"
                icon="bi-wallet2"
                :color="$detail['remaining'] < 0 ? 'danger' : 'success'">
                <x-money :amount="$detail['remaining']" :currency="$currency" />
            </x-stat-card>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent fw-semibold">{{ __('product_expenses.formula_title') }}</div>
        <div class="card-body">
            <p class="mb-2 hp-value font-monospace">{{ $detail['formula'] }}</p>
            <p class="mb-0 text-body-secondary">{{ __('product_expenses.formula_body', ['month' => $detail['month_label']]) }}</p>
            <hr>
            <p class="mb-1 fw-semibold">{{ __('product_expenses.sources_title') }}</p>
            <ul class="mb-0">
                <li>
                    {{ __('product_expenses.source_expected') }}:
                    <span class="num-ltr hp-value"><x-money :amount="$detail['expected']" :currency="$currency" /></span>
                    @if (! $detail['budget'])
                        <span class="text-body-secondary">— {{ __('product_expenses.no_budget') }}</span>
                    @endif
                </li>
                <li>
                    {{ __('product_expenses.source_entries') }}:
                    <span class="num-ltr hp-value"><x-money :amount="$detail['spent']" :currency="$currency" /></span>
                    ({{ $detail['vouchers']->count() }})
                    — {{ __('product_expenses.source_treasury_hint') }}
                </li>
            </ul>
        </div>
    </div>

    @if ($canManage)
        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold">{{ __('product_expenses.set_expected') }}</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('product.expenses.budget', $expenseType) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="year" value="{{ $year }}">
                            <input type="hidden" name="month" value="{{ $month }}">
                            <div class="mb-3">
                                <label class="form-label" for="expected_amount">{{ __('product_expenses.amount') }}</label>
                                <input class="form-control @error('expected_amount') is-invalid @enderror" dir="ltr"
                                       name="expected_amount" id="expected_amount"
                                       value="{{ old('expected_amount', $detail['expected']) }}" min="0" step="0.01" required>
                                @error('expected_amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="budget_notes">{{ __('product_expenses.notes') }}</label>
                                <textarea class="form-control" name="notes" id="budget_notes" rows="2">{{ old('notes', $detail['budget']?->notes) }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">{{ __('product_expenses.save_budget') }}</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold">{{ __('product_expenses.add_entry') }}</div>
                    <div class="card-body">
                        <p class="small text-body-secondary">{{ __('product_expenses.add_entry_help') }}</p>
                        <form method="POST" action="{{ route('product.expenses.payments.store', $expenseType) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="account_id">{{ __('gross.treasury.account') }}</label>
                                <select class="form-select @error('account_id') is-invalid @enderror" name="account_id" id="account_id" required>
                                    <option value="">—</option>
                                    @foreach ($accountsWithBalances as $row)
                                        @php $account = $row['account']; @endphp
                                        <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>
                                            {{ $account->name }} ({{ $account->currency }}) — {{ $row['balance'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('account_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="entry_amount">{{ __('product_expenses.amount') }}</label>
                                <input class="form-control @error('amount') is-invalid @enderror" dir="ltr"
                                       name="amount" id="entry_amount" value="{{ old('amount') }}" min="0.01" step="0.01" required>
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="voucher_date">{{ __('product_expenses.entry_date') }}</label>
                                <input class="form-control @error('voucher_date') is-invalid @enderror" dir="ltr" type="date"
                                       name="voucher_date" id="voucher_date" value="{{ old('voucher_date', now()->toDateString()) }}" required>
                                @error('voucher_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            @if ($expenseType->supports_rent_period)
                                <div class="mb-3">
                                    <label class="form-label" for="rent_period">{{ __('product_expenses.rent_period') }}</label>
                                    <select class="form-select" name="rent_period" id="rent_period">
                                        <option value="">—</option>
                                        <option value="THIS_MONTH" @selected(old('rent_period') === 'THIS_MONTH')>{{ __('product_expenses.rent_periods.THIS_MONTH') }}</option>
                                        <option value="NEXT_MONTH" @selected(old('rent_period') === 'NEXT_MONTH')>{{ __('product_expenses.rent_periods.NEXT_MONTH') }}</option>
                                    </select>
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label" for="entry_notes">{{ __('product_expenses.notes') }}</label>
                                <textarea class="form-control" name="notes" id="entry_notes" rows="2">{{ old('notes') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">{{ __('product_expenses.save_entry') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">{{ __('product_expenses.source_entries') }}</div>
        <div class="card-body p-0">
            @if ($detail['vouchers']->isEmpty())
                <p class="text-body-secondary p-3 mb-0">{{ __('product_expenses.no_entries') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('gross.treasury.voucher_number') }}</th>
                                <th>{{ __('product_expenses.entry_date') }}</th>
                                <th>{{ __('gross.treasury.account') }}</th>
                                <th>{{ __('product_expenses.amount') }}</th>
                                <th>{{ __('product_expenses.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detail['vouchers'] as $voucher)
                                <tr>
                                    <td>
                                        <a class="num-ltr" href="{{ route('gross.treasury.show', $voucher) }}">{{ $voucher->voucher_number }}</a>
                                    </td>
                                    <td class="num-ltr">{{ $voucher->voucher_date->toDateString() }}</td>
                                    <td>{{ $voucher->account?->name ?? '—' }}</td>
                                    <td class="num-ltr"><x-money :amount="$voucher->amount" :currency="$voucher->currency" /></td>
                                    <td class="hp-value text-break">{{ $voucher->notes ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
