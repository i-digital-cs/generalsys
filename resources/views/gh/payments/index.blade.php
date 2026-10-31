@extends('layouts.app')

@section('title', __('gh.payments.title'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.payments.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.payments.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.invoices.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.invoices.title') }}</a>
    </div>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-4">
            <label class="form-label" for="q">{{ __('gh.payments.search') }}</label>
            <input id="q" name="q" class="form-control form-control-sm" value="{{ request('q') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="status">{{ __('gh.invoices.status') }}</label>
            <select id="status" name="status" class="form-select form-select-sm">
                <option value="">{{ __('general.all') }}</option>
                @foreach (['PENDING','CONFIRMED','CANCELLED'] as $st)
                    <option value="{{ $st }}" @selected(request('status')===$st)>{{ __('gh.pay_status.'.$st) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="method">{{ __('gh.payments.method') }}</label>
            <select id="method" name="method" class="form-select form-select-sm">
                <option value="">{{ __('general.all') }}</option>
                <option value="CASH" @selected(request('method')==='CASH')>{{ __('gh.payment_method.CASH') }}</option>
                <option value="ONLINE" @selected(request('method')==='ONLINE')>{{ __('gh.payment_method.ONLINE') }}</option>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">{{ __('general.filter') }}</button></div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('gh.payments.reference') }}</th>
                    <th>{{ __('gh.invoices.number') }}</th>
                    <th>{{ __('gh.payments.method') }}</th>
                    <th>{{ __('gh.invoices.status') }}</th>
                    <th class="text-end">{{ __('gh.payments.amount') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="num-ltr">{{ $row->reference }}</td>
                        <td class="num-ltr">{{ $row->invoice?->number }}</td>
                        <td>{{ __('gh.payment_method.'.$row->method) }}</td>
                        <td><span class="badge text-bg-{{ $row->status === 'CONFIRMED' ? 'success' : ($row->status === 'PENDING' ? 'warning' : 'secondary') }}">{{ __('gh.pay_status.'.$row->status) }}</span></td>
                        <td class="text-end"><x-money :amount="$row->amount" currency="AED" /></td>
                        <td class="text-end">
                            @if ($row->invoice)
                                <a href="{{ route('gh.invoices.show', $row->invoice) }}" class="btn btn-sm btn-outline-primary">{{ __('gh.invoices.view') }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('gh.payments.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer bg-transparent">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
