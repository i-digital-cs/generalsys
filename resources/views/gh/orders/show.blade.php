@extends('layouts.app')

@section('title', $order->reference)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $order->reference }}</h1>
            <p class="text-body-secondary small mb-0">
                <span class="badge text-bg-primary">{{ __('gh.order_status.'.$order->status) }}</span>
                · {{ $order->customer?->localizedName() }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('update', $order)
                <a href="{{ route('gh.orders.edit', $order) }}" class="btn btn-outline-primary btn-sm">{{ __('general.edit') }}</a>
            @endcan
            @if ($order->status === \App\Support\GeneralHouse\GhOrderStatus::WORKING)
                @can('create', \App\Models\GeneralHouse\GhInvoice::class)
                    @if ($order->invoice)
                        <a href="{{ route('gh.invoices.builder', $order->invoice) }}" class="btn btn-primary btn-sm">{{ __('gh.invoices.builder') }}</a>
                    @else
                        <form method="post" action="{{ route('gh.invoices.create-from-order', $order) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('gh.invoices.create_draft') }}</button>
                        </form>
                    @endif
                @endcan
            @elseif ($order->invoice)
                @can('view', $order->invoice)
                    <a href="{{ route('gh.invoices.show', $order->invoice) }}" class="btn btn-outline-primary btn-sm">{{ __('gh.invoices.view') }}</a>
                @endcan
            @endif
            <a href="{{ route('gh.orders.history', $order) }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.history') }}</a>
            <a href="{{ route('gh.orders.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.back') }}</a>
        </div>
    </div>

    <div class="alert alert-info py-2 small" role="status">{{ __('gh.orders.estimate_banner') }}</div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.fields.customer') }}</h2></div>
                <div class="card-body small">
                    <div class="mb-2"><span class="form-label d-block mb-0">{{ __('gh.fields.customer') }}</span>
                        <span class="hp-value">{{ $order->customer?->localizedName() }}</span>
                        <span class="badge text-bg-secondary ms-1">{{ __('gh.customer_type.'.$order->customer?->type) }}</span>
                    </div>
                    <div class="mb-2"><span class="form-label d-block mb-0">{{ __('gh.orders.select_address') }}</span>
                        <span class="hp-value">{{ $order->address?->label }}</span>
                        <div class="text-body-secondary">{{ $order->address?->building }} {{ $order->address?->street }} {{ $order->address?->area }}</div>
                    </div>
                    <div><span class="form-label d-block mb-0">{{ __('gh.orders.zone') }}</span>
                        <span class="hp-value">{{ $order->zone?->localizedName() }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.pickup_window') }}</h2></div>
                <div class="card-body small">
                    <div class="mb-2 num-ltr hp-value">
                        {{ $order->pickup_starts_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}
                        –
                        {{ $order->pickup_ends_at?->timezone($displayTz)->format('H:i') }}
                        ({{ $displayTz }})
                    </div>
                    <div class="mb-2">
                        <span class="form-label d-block mb-0">{{ __('gh.orders.eligibility') }}</span>
                        @if ($order->status === \App\Support\GeneralHouse\GhOrderStatus::LATER)
                            <span class="badge {{ $order->isPickupEligible() ? 'text-bg-warning' : 'text-bg-secondary' }}">
                                {{ $order->isPickupEligible() ? __('gh.orders.eligible_pending_job') : __('gh.orders.not_yet_eligible') }}
                            </span>
                        @elseif ($order->status === \App\Support\GeneralHouse\GhOrderStatus::PICKUP)
                            <span class="badge text-bg-success">{{ __('gh.orders.in_pickup') }}</span>
                        @else
                            <span class="badge text-bg-light">{{ __('gh.order_status.'.$order->status) }}</span>
                        @endif
                    </div>
                    <div class="mb-2"><span class="form-label d-block mb-0">{{ __('gh.orders.pickup_driver') }}</span>
                        <span class="hp-value">{{ $order->pickupDriver?->localizedName() ?? '—' }}</span>
                    </div>
                    <div class="mb-2"><span class="form-label d-block mb-0">{{ __('gh.orders.delivery_driver') }}</span>
                        <span class="hp-value">{{ $order->deliveryDriver?->localizedName() ?? __('gh.orders.none') }}</span>
                    </div>
                    @if ($order->scheduling_notes)
                        <div class="mb-2"><span class="form-label d-block mb-0">{{ __('gh.orders.scheduling_notes') }}</span>{{ $order->scheduling_notes }}</div>
                    @endif
                    @if ($order->notes)
                        <div><span class="form-label d-block mb-0">{{ __('gh.orders.operational_notes') }}</span>{{ $order->notes }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h2 class="h6 card-title mb-0">{{ __('gh.orders.lines') }}</h2>
            <span class="small">{{ __('gh.orders.estimated_subtotal') }}:
                <span class="hp-value"><x-money :amount="$order->estimated_subtotal" currency="AED" /></span>
            </span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hr table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('gh.fields.category') }}</th>
                        <th>{{ __('gh.fields.product') }}</th>
                        <th>{{ __('gh.fields.treatment') }}</th>
                        <th>{{ __('gh.orders.quantity') }}</th>
                        <th>{{ __('gh.orders.unit_estimate') }}</th>
                        <th>{{ __('gh.orders.price_source') }}</th>
                        <th>{{ __('gh.orders.line_estimate') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($order->lines as $line)
                        <tr>
                            <td>{{ app()->getLocale() === 'ar' ? $line->category_name_ar : ($line->category_name_en ?: $line->category_name_ar) }}</td>
                            <td>{{ app()->getLocale() === 'ar' ? $line->product_name_ar : ($line->product_name_en ?: $line->product_name_ar) }}</td>
                            <td>{{ app()->getLocale() === 'ar' ? $line->treatment_name_ar : ($line->treatment_name_en ?: $line->treatment_name_ar) }}</td>
                            <td class="hp-value num-ltr">{{ $line->quantity }}</td>
                            <td class="hp-value"><x-money :amount="$line->unit_price_estimate" currency="AED" /></td>
                            <td>{{ __('gh.price_source.'.$line->price_source) }}</td>
                            <td class="hp-value"><x-money :amount="$line->lineEstimateTotal()" currency="AED" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('gh.orders.partials.logistics')

    @if ($order->receivedLines->isNotEmpty())
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.received_lines') }}</h2></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hr table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('gh.fields.product') }}</th>
                            <th>{{ __('gh.fields.treatment') }}</th>
                            <th>{{ __('gh.orders.quantity') }}</th>
                            <th>{{ __('gh.orders.discrepancy') }}</th>
                            <th>{{ __('gh.fields.notes') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($order->receivedLines as $recv)
                            <tr>
                                <td>{{ app()->getLocale() === 'ar' ? $recv->product_name_ar : ($recv->product_name_en ?: $recv->product_name_ar) }}</td>
                                <td>{{ app()->getLocale() === 'ar' ? $recv->treatment_name_ar : ($recv->treatment_name_en ?: $recv->treatment_name_ar) }}</td>
                                <td class="hp-value num-ltr">{{ $recv->quantity }}</td>
                                <td><span class="badge text-bg-secondary">{{ __('gh.discrepancy.'.$recv->discrepancy_type) }}</span></td>
                                <td class="small">{{ $recv->notes }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.events') }}</h2></div>
        <div class="card-body">
            @if ($order->events->isEmpty())
                <p class="text-body-secondary small mb-0">—</p>
            @else
                <ul class="list-unstyled mb-0 small">
                    @foreach ($order->events->take(10) as $event)
                        <li class="border-bottom py-2">
                            <span class="badge text-bg-light">{{ $event->event_type }}</span>
                            <span class="num-ltr text-body-secondary">{{ $event->occurred_at?->timezone($order->timezone)->translatedFormat('Y-m-d H:i') }}</span>
                            @if ($event->actor)
                                · {{ $event->actor->localizedName() }}
                            @endif
                            @if ($event->from_status || $event->to_status)
                                · {{ $event->from_status ?? '—' }} → {{ $event->to_status ?? '—' }}
                            @endif
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('gh.orders.history', $order) }}" class="btn btn-link btn-sm px-0">{{ __('gh.orders.history') }}</a>
            @endif
        </div>
    </div>
@endsection
