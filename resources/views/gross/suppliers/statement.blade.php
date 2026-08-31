@extends('layouts.app')

@section('title', __('gross.suppliers.statement_title', ['name' => $supplier->name]))

@section('content')
    @php
        $hasFilters = ($filters['from'] ?? '') !== ''
            || ($filters['to'] ?? '') !== ''
            || (($filters['currency'] ?? 'EGP') !== 'EGP');
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.suppliers.statement_title', ['name' => $supplier->name]) }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.suppliers.statement_subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\Gross\GrossTreasuryVoucher::class)
                <a href="{{ route('gross.treasury.create', ['type' => 'PAYMENT', 'purpose' => 'SUPPLIER', 'supplier_id' => $supplier->id]) }}"
                   class="btn btn-primary btn-sm">
                    <i class="bi bi-wallet2 me-1" aria-hidden="true"></i>{{ __('gross.suppliers.pay') }}
                </a>
            @endcan
            <a href="{{ route('gross.suppliers.show', $supplier) }}" class="btn btn-outline-secondary btn-sm">{{ __('general.back') }}</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label mb-1" for="currency">{{ __('gross.fields.currency') }}</label>
                    <select id="currency" name="currency" class="form-select form-select-sm">
                        @foreach (['EGP', 'AED'] as $cur)
                            <option value="{{ $cur }}" @selected(($filters['currency'] ?? 'EGP') === $cur)>{{ $cur }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1" for="from">{{ __('gross.fields.date_from') }}</label>
                    <input id="from" name="from" type="date" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1" for="to">{{ __('gross.fields.date_to') }}</label>
                    <input id="to" name="to" type="date" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasFilters)
                        <a href="{{ route('gross.suppliers.statement', $supplier) }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('gross.suppliers.statement_purchases') }}</div>
                    <div class="fs-5 fw-semibold num-ltr">
                        <x-money :amount="$statement['total_purchases']" :currency="$statement['currency']" />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('gross.suppliers.statement_returns') }}</div>
                    <div class="fs-5 fw-semibold num-ltr">
                        <x-money :amount="$statement['total_returns']" :currency="$statement['currency']" />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('gross.suppliers.statement_payments') }}</div>
                    <div class="fs-5 fw-semibold num-ltr">
                        <x-money :amount="$statement['total_payments']" :currency="$statement['currency']" />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('gross.suppliers.statement_balance') }}</div>
                    <div class="fs-5 fw-semibold num-ltr">
                        <x-money :amount="$statement['balance']" :currency="$statement['currency']" />
                    </div>
                    <div class="small text-body-secondary mt-1">{{ __('gross.suppliers.statement_balance_hint') }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($statement['truncated'])
        <div class="alert alert-warning py-2 small">{{ __('gross.suppliers.statement_truncated') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if ($statement['entries'] === [])
                <x-empty-state :title="__('gross.suppliers.statement_empty')" icon="bi-journal-text" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.date') }}</th>
                                <th>{{ __('gross.fields.type') }}</th>
                                <th>{{ __('gross.fields.reference') }}</th>
                                <th class="col-num">{{ __('gross.fields.debit') }}</th>
                                <th class="col-num">{{ __('gross.fields.credit') }}</th>
                                <th class="col-num">{{ __('gross.fields.balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statement['entries'] as $row)
                                <tr>
                                    <td class="small"><span class="num-ltr">{{ $row['at']->translatedFormat('d M Y H:i') }}</span></td>
                                    <td>{{ __('gross.statement_types.'.$row['type']) }}</td>
                                    <td>
                                        @if ($row['url'])
                                            <a href="{{ $row['url'] }}" class="num-ltr text-decoration-none">{{ $row['reference'] }}</a>
                                        @else
                                            <span class="num-ltr">{{ $row['reference'] }}</span>
                                        @endif
                                    </td>
                                    <td class="col-num">
                                        @if ((float) $row['debit'] > 0)
                                            <span class="num-ltr"><x-money :amount="$row['debit']" :currency="$statement['currency']" /></span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="col-num">
                                        @if ((float) $row['credit'] > 0)
                                            <span class="num-ltr"><x-money :amount="$row['credit']" :currency="$statement['currency']" /></span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="col-num fw-semibold">
                                        <span class="num-ltr"><x-money :amount="$row['balance']" :currency="$statement['currency']" /></span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
