@extends('layouts.app')
@section('title', __('gh.reports.collections'))
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('gh.reports.collections') }}</h1>
        <a href="{{ route('gh.reports.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.reports.back') }}</a>
    </div>
    @include('gh.reports.partials.filters', ['exportRoute' => 'gh.reports.collections'])
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('gh.payments.reference') }}</th>
                    <th>{{ __('gh.invoices.number') }}</th>
                    <th>{{ __('gh.reports.customer') }}</th>
                    <th>{{ __('gh.reports.method') }}</th>
                    <th>{{ __('gh.reports.amount') }}</th>
                    <th>{{ __('gh.reports.collector') }}</th>
                    <th>{{ __('gh.reports.confirmed') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $p)
                    <tr>
                        <td class="num-ltr">{{ $p->reference }}</td>
                        <td class="num-ltr">{{ $p->invoice?->number }}</td>
                        <td>{{ $p->invoice?->customer_name_en ?: $p->invoice?->customer_name_ar }}</td>
                        <td>{{ __('gh.payment_method.'.$p->method) }}</td>
                        <td class="num-ltr"><x-money :amount="$p->amount" currency="AED" /></td>
                        <td>{{ $p->collector?->name_en ?: $p->collector?->name_ar ?: '—' }}</td>
                        <td class="num-ltr small">{{ $p->confirmed_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-body-secondary">{{ __('gh.reports.empty') }}</td></tr>
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
