@extends('layouts.app')
@section('title', __('gh.reports.invoices'))
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('gh.reports.invoices') }}</h1>
        <a href="{{ route('gh.reports.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.reports.back') }}</a>
    </div>
    @include('gh.reports.partials.filters', ['exportRoute' => 'gh.reports.invoices'])
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('gh.invoices.number') }}</th>
                    <th>{{ __('gh.orders.reference') }}</th>
                    <th>{{ __('gh.reports.customer') }}</th>
                    <th>{{ __('gh.invoices.issued_at') }}</th>
                    <th>{{ __('gh.invoices.total') }}</th>
                    <th>{{ __('gh.reports.confirmed') }}</th>
                    <th>{{ __('gh.reports.remaining') }}</th>
                    <th>{{ __('gh.reports.payment_status') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $inv)
                    @php
                        $confirmed = $reports->confirmedForInvoice($inv);
                        $remaining = $reports->remainingForInvoice($inv);
                    @endphp
                    <tr>
                        <td><a class="num-ltr" href="{{ route('gh.invoices.show', $inv) }}">{{ $inv->number }}</a></td>
                        <td class="num-ltr">{{ $inv->order_reference }}</td>
                        <td>{{ app()->getLocale()==='ar' ? $inv->customer_name_ar : ($inv->customer_name_en ?: $inv->customer_name_ar) }}</td>
                        <td class="num-ltr small">{{ $inv->issued_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}</td>
                        <td class="num-ltr"><x-money :amount="$inv->total_payable" currency="AED" /></td>
                        <td class="num-ltr"><x-money :amount="$confirmed" currency="AED" /></td>
                        <td class="num-ltr"><x-money :amount="$remaining" currency="AED" /></td>
                        <td>{{ __('gh.payment_status.'.$inv->payment_status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-body-secondary">{{ __('gh.reports.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-body">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
