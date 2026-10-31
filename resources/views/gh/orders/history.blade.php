@extends('layouts.app')

@section('title', __('gh.orders.history'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.orders.history') }}</h1>
            <p class="text-body-secondary small mb-0">{{ $order->reference }} · {{ $order->customer?->localizedName() }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gh.orders.show', $order) }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.show') }}</a>
            <a href="{{ route('gh.orders.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.back') }}</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hr table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('gh.orders.events') }}</th>
                        <th>occurred_at</th>
                        <th>recorded_at</th>
                        <th>{{ __('general.actions') }}</th>
                        <th>payload</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($order->events as $event)
                        <tr>
                            <td>
                                <span class="badge text-bg-secondary">{{ $event->event_type }}</span>
                                @if ($event->from_status || $event->to_status)
                                    <div class="small text-body-secondary">{{ $event->from_status ?? '—' }} → {{ $event->to_status ?? '—' }}</div>
                                @endif
                            </td>
                            <td class="num-ltr small">{{ $event->occurred_at?->timezone($order->timezone)->toDateTimeString() }}</td>
                            <td class="num-ltr small">{{ $event->recorded_at?->timezone($order->timezone)->toDateTimeString() }}</td>
                            <td>{{ $event->actor?->localizedName() ?? '—' }}</td>
                            <td class="small"><code class="text-break">{{ $event->payload ? json_encode($event->payload, JSON_UNESCAPED_UNICODE) : '—' }}</code></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-body-secondary">—</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
