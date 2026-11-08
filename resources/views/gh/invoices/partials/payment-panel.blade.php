@php
    use App\Support\GeneralHouse\GhPaymentStatus;

    $status = (string) ($balance['payment_status'] ?? $invoice->payment_status ?? 'UNPAID');
    $payBadge = match ($status) {
        'PAID' => 'success',
        'PARTIAL' => 'warning',
        default => 'secondary',
    };

    $confirmedPayments = $invoice->payments
        ->where('status', GhPaymentStatus::CONFIRMED)
        ->sortBy('occurred_at')
        ->values();

    $methods = $confirmedPayments
        ->pluck('method')
        ->unique()
        ->filter()
        ->values();

    $latestPaymentAt = $confirmedPayments
        ->map(fn ($p) => $p->confirmed_at ?? $p->occurred_at)
        ->filter()
        ->sortDesc()
        ->first();

    $displayTz = (string) config('general_house.timezone', 'Asia/Dubai');
@endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 card-title mb-0">{{ __('gh.invoices.payment_section') }}</h2>
        <span class="badge text-bg-{{ $payBadge }}">{{ __('gh.payment_status.'.$status) }}</span>
    </div>
    <div class="card-body">
        @if ($confirmedPayments->isEmpty())
            <p class="mb-0 text-body-secondary">{{ __('gh.invoices.payment_at_delivery_hint') }}</p>
        @else
            <dl class="row mb-0 small">
                <dt class="col-sm-4">{{ __('gh.invoices.payment_method_label') }}</dt>
                <dd class="col-sm-8">
                    @foreach ($methods as $method)
                        <span class="badge text-bg-light border me-1">{{ __('gh.payment_method.'.$method) }}</span>
                    @endforeach
                </dd>
                @if ($latestPaymentAt)
                    <dt class="col-sm-4">{{ __('gh.invoices.payment_date_label') }}</dt>
                    <dd class="col-sm-8 hp-value num-ltr">
                        {{ $latestPaymentAt->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}
                    </dd>
                @endif
                @if ($status === 'PARTIAL')
                    <dt class="col-sm-4">{{ __('gh.invoices.remaining_label') }}</dt>
                    <dd class="col-sm-8 hp-value num-ltr">
                        <x-money :amount="$balance['remaining_due'] ?? $balance['remaining']" currency="AED" />
                    </dd>
                @endif
            </dl>
        @endif
    </div>
</div>
