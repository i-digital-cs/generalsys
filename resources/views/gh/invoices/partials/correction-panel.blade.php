@php
    $creditNotesEnabled = \App\Support\GeneralHouse\GhLaunchConfig::creditNotesEnabled();
    $debitAdjustmentsEnabled = \App\Support\GeneralHouse\GhLaunchConfig::debitAdjustmentsEnabled();
@endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent">
        <h2 class="h6 card-title mb-0">{{ __('gh.invoice_corrections.title') }}</h2>
    </div>
    <div class="card-body small">
        <p class="mb-2">{{ __('gh.invoice_corrections.issued_immutable') }}</p>
        <p class="mb-2">{{ __('gh.invoice_corrections.decrease_via_credit_note') }}</p>
        <p class="mb-3">{{ __('gh.invoice_corrections.increase_via_debit') }}</p>
        <div class="d-flex flex-wrap gap-2">
            @if ($debitAdjustmentsEnabled)
                @can('create', \App\Models\GeneralHouse\GhDebitAdjustment::class)
                    <a href="{{ route('gh.debit-adjustments.create', $invoice) }}" class="btn btn-outline-primary btn-sm">
                        {{ __('gh.invoice_corrections.create_debit_adjustment') }}
                    </a>
                @endcan
            @endif
            @if ($creditNotesEnabled)
                @can('create', \App\Models\GeneralHouse\GhCreditNote::class)
                    <a href="{{ route('gh.credit-notes.create', $invoice) }}" class="btn btn-outline-warning btn-sm">
                        {{ __('gh.invoice_corrections.create_credit_note') }}
                    </a>
                @endcan
            @endif
        </div>
    </div>
</div>
