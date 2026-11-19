@extends('layouts.app')

@section('title', __('gh.debit_adjustments.show'))

@section('content')
@php
    $displayTz = (string) config('general_house.timezone', 'Asia/Dubai');
    $localeAr = app()->getLocale() === 'ar';
@endphp
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.debit_adjustments.show') }}</h1>
            <p class="text-body-secondary small mb-0 num-ltr">{{ $adjustment->number ?: __('gh.debit_adjustments.draft_no_number') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-secondary align-self-center">{{ __('gh.da_status.'.$adjustment->status) }}</span>
            <a href="{{ route('gh.debit-adjustments.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.debit_adjustments.title') }}</a>
            @if ($adjustment->invoice)
                <a href="{{ route('gh.invoices.show', $adjustment->invoice) }}" class="btn btn-outline-primary btn-sm">{{ __('gh.invoices.view') }}</a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.debit_adjustments.amounts') }}</h2></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>{{ __('gh.debit_adjustments.lines_net') }}</span><span class="hp-value num-ltr"><x-money :amount="$adjustment->lines_net" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.debit_adjustments.vat') }}</span><span class="hp-value num-ltr"><x-money :amount="$adjustment->vat_amount" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between fw-bold"><span>{{ __('gh.debit_adjustments.total') }}</span><span class="hp-value num-ltr"><x-money :amount="$adjustment->total_debit" currency="AED" /></span></div>
                    <hr>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.debit_adjustments.reason_code') }}</span><span>{{ __('gh.da_reason.'.$adjustment->reason_code) }}</span></div>
                    <p class="mb-0 mt-2">{{ $adjustment->reason_text }}</p>
                    @if ($adjustment->issued_at)
                        <p class="small text-body-secondary mt-2 mb-0">
                            {{ __('gh.debit_adjustments.issued_at') }}:
                            <span class="num-ltr"><x-gh.dubai-time :at="$adjustment->issued_at" :badge="true" /></span>
                        </p>
                    @endif
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.debit_adjustments.invoice_context') }}</h2></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['invoice_total']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.debit_adjustments.debits_issued') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['debits_issued']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.credits_issued') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['credits_issued']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.net_invoiced') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['net_invoiced']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.remaining_due') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['remaining_due']" currency="AED" /></span></div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.debit_adjustments.actions') }}</h2></div>
                <div class="card-body d-grid gap-2">
                    @if ($adjustment->isDraft())
                        @can('issue', $adjustment)
                            <form method="post" action="{{ route('gh.debit-adjustments.issue', $adjustment) }}">@csrf<button class="btn btn-primary btn-sm">{{ __('gh.debit_adjustments.issue') }}</button></form>
                        @endcan
                        @can('cancel', $adjustment)
                            <form method="post" action="{{ route('gh.debit-adjustments.cancel', $adjustment) }}">@csrf<button class="btn btn-outline-danger btn-sm">{{ __('general.cancel') }}</button></form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.debit_adjustments.lines') }}</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('gh.debit_adjustments.line') }}</th>
                            <th>{{ __('gh.debit_adjustments.line_type') }}</th>
                            <th class="text-end">{{ __('gh.orders.quantity') }}</th>
                            <th class="text-end">{{ __('gh.debit_adjustments.vat') }}</th>
                            <th class="text-end">{{ __('gh.debit_adjustments.total') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($adjustment->lines as $line)
                            <tr>
                                <td>
                                    {{ $localeAr ? ($line->product_name_ar ?: $line->product_name_en) : ($line->product_name_en ?: $line->product_name_ar) }}
                                    <div class="small text-body-secondary">{{ __('gh.tax_class.'.$line->tax_class) }}</div>
                                </td>
                                <td>{{ __('gh.da_line_type.'.$line->line_type) }}</td>
                                <td class="text-end num-ltr">{{ $line->quantity }}</td>
                                <td class="text-end"><x-money :amount="$line->line_vat" currency="AED" /></td>
                                <td class="text-end"><x-money :amount="$line->line_total" currency="AED" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.debit_adjustments.audit') }}</h2></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($adjustment->events as $event)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <span>{{ $event->event_type }}</span>
                                <span class="num-ltr text-body-secondary"><x-gh.dubai-time :at="$event->occurred_at" :badge="true" /></span>
                            </div>
                            @if ($event->actor)
                                <div class="text-body-secondary">{{ $event->actor->name }}</div>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('gh.debit_adjustments.audit_empty') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
