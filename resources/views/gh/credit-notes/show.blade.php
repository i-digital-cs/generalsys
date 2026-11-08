@extends('layouts.app')

@section('title', __('gh.credit_notes.show'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.credit_notes.show') }}</h1>
            <p class="text-body-secondary small mb-0 num-ltr">{{ $creditNote->number ?: __('gh.credit_notes.draft_no_number') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-secondary align-self-center">{{ __('gh.cn_status.'.$creditNote->status) }}</span>
            <a href="{{ route('gh.credit-notes.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.credit_notes.title') }}</a>
            @if ($creditNote->invoice)
                <a href="{{ route('gh.invoices.show', $creditNote->invoice) }}" class="btn btn-outline-primary btn-sm">{{ __('gh.invoices.view') }}</a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.credit_notes.amounts') }}</h2></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.lines_net') }}</span><span class="hp-value num-ltr"><x-money :amount="$creditNote->lines_net" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.vat') }}</span><span class="hp-value num-ltr"><x-money :amount="$creditNote->vat_amount" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between fw-bold"><span>{{ __('gh.credit_notes.total') }}</span><span class="hp-value num-ltr"><x-money :amount="$creditNote->total_credit" currency="AED" /></span></div>
                    <hr>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.reason_code') }}</span><span>{{ __('gh.cn_reason.'.$creditNote->reason_code) }}</span></div>
                    <p class="mb-0 mt-2">{{ $creditNote->reason_text }}</p>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.credit_notes.invoice_context') }}</h2></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['invoice_total']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.credits_issued') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['credits_issued']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.net_invoiced') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['net_invoiced']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.remaining_due') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['remaining_due']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.customer_credit') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['customer_credit']" currency="AED" /></span></div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.credit_notes.actions') }}</h2></div>
                <div class="card-body d-grid gap-2">
                    @if ($creditNote->status === 'DRAFT')
                        @can('submit', $creditNote)
                            <form method="post" action="{{ route('gh.credit-notes.submit', $creditNote) }}">@csrf<button class="btn btn-primary btn-sm">{{ __('gh.credit_notes.submit') }}</button></form>
                        @endcan
                        @can('cancel', $creditNote)
                            <form method="post" action="{{ route('gh.credit-notes.cancel', $creditNote) }}">@csrf<button class="btn btn-outline-danger btn-sm">{{ __('general.cancel') }}</button></form>
                        @endcan
                    @endif
                    @if ($creditNote->status === 'PENDING_APPROVAL')
                        @can('approve', $creditNote)
                            <form method="post" action="{{ route('gh.credit-notes.approve', $creditNote) }}">@csrf<button class="btn btn-success btn-sm">{{ __('gh.credit_notes.approve') }}</button></form>
                        @endcan
                        @can('reject', $creditNote)
                            <form method="post" action="{{ route('gh.credit-notes.reject', $creditNote) }}" class="d-grid gap-1">
                                @csrf
                                <label class="form-label" for="rejection_reason">{{ __('gh.credit_notes.rejection_reason') }}</label>
                                <input class="form-control form-control-sm" name="rejection_reason" id="rejection_reason" required>
                                <button class="btn btn-outline-danger btn-sm">{{ __('gh.credit_notes.reject') }}</button>
                            </form>
                        @endcan
                    @endif
                    @if ($creditNote->status === 'APPROVED')
                        @can('issue', $creditNote)
                            <form method="post" action="{{ route('gh.credit-notes.issue', $creditNote) }}">@csrf<button class="btn btn-primary btn-sm">{{ __('gh.credit_notes.issue') }}</button></form>
                        @endcan
                        @can('cancel', $creditNote)
                            <form method="post" action="{{ route('gh.credit-notes.cancel', $creditNote) }}">@csrf<button class="btn btn-outline-danger btn-sm">{{ __('general.cancel') }}</button></form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.credit_notes.lines') }}</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('gh.credit_notes.line') }}</th>
                            <th class="text-end">{{ __('gh.credit_notes.qty_credit') }}</th>
                            <th class="text-end">{{ __('gh.credit_notes.vat') }}</th>
                            <th class="text-end">{{ __('gh.credit_notes.total') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($creditNote->lines as $line)
                            <tr>
                                <td>
                                    {{ app()->getLocale() === 'ar' ? ($line->product_name_ar ?: $line->product_name_en) : ($line->product_name_en ?: $line->product_name_ar) }}
                                    <div class="small text-body-secondary">{{ __('gh.tax_class.'.$line->tax_class) }}</div>
                                </td>
                                <td class="text-end num-ltr">{{ $line->quantity_credited }}</td>
                                <td class="text-end"><x-money :amount="$line->line_vat" currency="AED" /></td>
                                <td class="text-end"><x-money :amount="$line->line_total" currency="AED" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.credit_notes.audit') }}</h2></div>
                <ul class="list-group list-group-flush small">
                    @foreach ($creditNote->events as $event)
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
