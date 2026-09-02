@extends('layouts.app')

@section('title', __('gross.shipping.statement_title', ['name' => $company->name]))

@section('content')
    @php
        $hasFilters = ($filters['from'] ?? '') !== ''
            || ($filters['to'] ?? '') !== ''
            || (($filters['currency'] ?? 'EGP') !== 'EGP');
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.shipping.statement_title', ['name' => $company->name]) }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.shipping.statement_subtitle') }}</p>
        </div>
        <a href="{{ route('gross.shipping.show', $company) }}" class="btn btn-outline-secondary btn-sm">{{ __('general.back') }}</a>
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
                        <a href="{{ route('gross.shipping.statement', $company) }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('gross.shipping.statement_invoices') }}</div>
                    <div class="fs-5 fw-semibold num-ltr">
                        <x-money :amount="$statement['total_invoices']" :currency="$statement['currency']" />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('gross.shipping.statement_adjustments') }}</div>
                    <div class="fs-5 fw-semibold num-ltr">
                        <x-money :amount="$statement['total_adjustments']" :currency="$statement['currency']" />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('gross.shipping.statement_collections') }}</div>
                    <div class="fs-5 fw-semibold num-ltr">
                        <x-money :amount="$statement['total_collections']" :currency="$statement['currency']" />
                    </div>
                    <div class="small text-body-secondary mt-1">{{ __('gross.shipping.statement_collections_stub') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('gross.shipping.statement_balance') }}</div>
                    <div class="fs-5 fw-semibold num-ltr">
                        <x-money :amount="$statement['balance']" :currency="$statement['currency']" />
                    </div>
                    <div class="small text-body-secondary mt-1">{{ __('gross.shipping.statement_balance_hint') }}</div>
                </div>
            </div>
        </div>
        @if ((float) $statement['total_delivery_fees'] > 0)
            <div class="col-md-3 col-lg-2">
                <div class="card border-0 shadow-sm h-100 border-warning">
                    <div class="card-body">
                        <div class="small text-body-secondary">{{ __('gross.shipping.statement_delivery_fees') }}</div>
                        <div class="fs-5 fw-semibold num-ltr text-warning">
                            <x-money :amount="$statement['total_delivery_fees']" :currency="$statement['currency']" />
                        </div>
                        <div class="small text-body-secondary mt-1">{{ __('gross.shipping.statement_delivery_fees_hint') }}</div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($statement['truncated'])
        <div class="alert alert-warning py-2 small">{{ __('gross.shipping.statement_truncated') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if ($statement['entries'] === [])
                <x-empty-state :title="__('gross.shipping.statement_empty')" icon="bi-journal-text" />
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
                                <tr @class(['text-body-secondary' => ! empty($row['informational'])])>
                                    <td class="small"><span class="num-ltr">{{ $row['at']->translatedFormat('d M Y H:i') }}</span></td>
                                    <td>
                                        {{ __('gross.statement_types.'.$row['type']) }}
                                        @if (! empty($row['informational']))
                                            <span class="badge text-bg-light border ms-1">{{ __('gross.shipping.statement_info_only') }}</span>
                                        @endif
                                    </td>
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
