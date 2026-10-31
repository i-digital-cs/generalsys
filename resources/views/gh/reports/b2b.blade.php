@extends('layouts.app')
@section('title', __('gh.reports.b2b'))
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('gh.reports.b2b') }}</h1>
        <a href="{{ route('gh.reports.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.reports.back') }}</a>
    </div>
    @include('gh.reports.partials.filters', ['exportRoute' => 'gh.reports.b2b'])
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('gh.reports.customer') }}</th>
                    <th>{{ __('gh.reports.contract') }}</th>
                    <th>{{ __('gh.reports.issued_total') }}</th>
                    <th>{{ __('gh.reports.confirmed') }}</th>
                    <th>{{ __('gh.reports.remaining') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ app()->getLocale()==='ar' ? $r->customer_name_ar : ($r->customer_name_en ?: $r->customer_name_ar) }}</td>
                        <td class="num-ltr">{{ $r->contract_reference ?: '—' }}</td>
                        <td class="num-ltr"><x-money :amount="$r->issued_total" currency="AED" /></td>
                        <td class="num-ltr"><x-money :amount="$r->confirmed_total" currency="AED" /></td>
                        <td class="num-ltr"><x-money :amount="$r->outstanding_total" currency="AED" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-body-secondary">{{ __('gh.reports.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($rows, 'hasPages') && $rows->hasPages())
            <div class="card-body">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
