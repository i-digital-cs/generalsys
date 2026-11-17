@extends('layouts.app')

@section('title', __('gh.driver_cash.title'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.driver_cash.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.driver_cash.subtitle') }}</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver_cash.outstanding_drivers') }}</h2></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                <tr>
                    <th>{{ __('gh.driver_cash.driver') }}</th>
                    <th class="text-end">{{ __('gh.driver_cash.collected_total') }}</th>
                    <th class="text-end">{{ __('gh.driver_cash.settled_total') }}</th>
                    <th class="text-end">{{ __('gh.driver_cash.outstanding') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($outstandingDrivers as $row)
                    <tr>
                        <td>{{ $row['driver']?->localizedName() }}</td>
                        <td class="text-end num-ltr hp-value"><x-money :amount="$row['collected_total']" currency="AED" /></td>
                        <td class="text-end num-ltr hp-value"><x-money :amount="$row['settled_total']" currency="AED" /></td>
                        <td class="text-end num-ltr hp-value"><x-money :amount="$row['outstanding']" currency="AED" /></td>
                        <td class="text-end">
                            <a href="{{ route('gh.driver-cash.show', $row['driver']) }}" class="btn btn-sm btn-outline-primary">{{ __('gh.driver_cash.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-body-secondary">{{ __('gh.driver_cash.no_outstanding') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver_cash.collections') }}</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>{{ __('gh.driver_cash.collection_ref') }}</th>
                            <th>{{ __('gh.driver_cash.driver') }}</th>
                            <th>{{ __('gh.driver_cash.order') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.outstanding') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($collections as $c)
                            <tr>
                                <td class="num-ltr">{{ $c->reference }}</td>
                                <td>{{ $c->driver?->localizedName() }}</td>
                                <td class="num-ltr"><a href="{{ route('gh.orders.show', $c->order_id) }}">{{ $c->order?->reference }}</a></td>
                                <td class="text-end num-ltr hp-value"><x-money :amount="$c->amount" currency="AED" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-transparent">{{ $collections->links() }}</div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver_cash.settlements') }}</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>{{ __('gh.driver_cash.settlement_ref') }}</th>
                            <th>{{ __('gh.driver_cash.driver') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.settle_amount') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($settlements as $s)
                            <tr>
                                <td class="num-ltr">{{ $s->reference }}</td>
                                <td>{{ $s->driver?->localizedName() }}</td>
                                <td class="text-end num-ltr hp-value"><x-money :amount="$s->amount" currency="AED" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-transparent">{{ $settlements->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
