@extends('layouts.app')

@section('title', __('gh.financial_corrections.show'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.financial_corrections.show') }}</h1>
            <p class="text-body-secondary small mb-0 num-ltr">{{ $correction->reference ?: '—' }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-secondary align-self-center">{{ __('gh.fc_status.'.$correction->status) }}</span>
            <a href="{{ route('gh.financial-corrections.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.financial_corrections.title') }}</a>
        </div>
    </div>

    <div class="alert alert-warning small">{{ __('gh.financial_corrections.execute_blocked_note') }}</div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.financial_corrections.details') }}</h2></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>{{ __('gh.financial_corrections.type') }}</span><span>{{ __('gh.fc_type.'.$correction->correction_type) }}</span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.refunds.amount') }}</span><span class="hp-value num-ltr"><x-money :amount="$correction->amount" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.payments.reference') }}</span><span class="num-ltr">{{ $correction->payment?->reference }}</span></div>
                    <p class="mt-2 mb-0">{{ $correction->reason_text }}</p>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.financial_corrections.actions') }}</h2></div>
                <div class="card-body d-grid gap-2">
                    @if ($correction->status === 'REQUESTED')
                        @can('submit', $correction)
                            <form method="post" action="{{ route('gh.financial-corrections.submit', $correction) }}">@csrf<button class="btn btn-primary btn-sm">{{ __('gh.financial_corrections.submit') }}</button></form>
                        @endcan
                    @endif
                    @if ($correction->status === 'PENDING_APPROVAL')
                        @can('approve', $correction)
                            <form method="post" action="{{ route('gh.financial-corrections.approve', $correction) }}">@csrf<button class="btn btn-success btn-sm">{{ __('gh.financial_corrections.approve') }}</button></form>
                        @endcan
                        @can('reject', $correction)
                            <form method="post" action="{{ route('gh.financial-corrections.reject', $correction) }}" class="d-grid gap-1">
                                @csrf
                                <label class="form-label" for="rejection_reason">{{ __('gh.financial_corrections.rejection_reason') }}</label>
                                <input class="form-control form-control-sm" name="rejection_reason" id="rejection_reason" required>
                                <button class="btn btn-outline-danger btn-sm">{{ __('gh.financial_corrections.reject') }}</button>
                            </form>
                        @endcan
                    @endif
                    @if ($correction->status === 'APPROVED')
                        @can('execute', $correction)
                            <form method="post" action="{{ route('gh.financial-corrections.execute', $correction) }}">@csrf
                                <button class="btn btn-warning btn-sm" @disabled(!\App\Support\GeneralHouse\GhLaunchConfig::paymentCorrectionExecuteEnabled())>
                                    {{ __('gh.financial_corrections.execute') }}
                                </button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.financial_corrections.audit') }}</h2></div>
                <ul class="list-group list-group-flush small">
                    @foreach ($correction->events as $event)
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span>{{ $event->event_type }} → {{ $event->to_status }}</span>
                            <span class="text-body-secondary num-ltr">{{ $event->actor?->name_en ?? $event->actor?->name_ar }} · {{ optional($event->occurred_at)->timezone(config('general_house.timezone'))->format('Y-m-d H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
