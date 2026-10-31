@extends('layouts.app')

@section('title', $order->reference)

@section('content')
<div class="container-fluid py-3" style="max-width:40rem">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 num-ltr">{{ $order->reference }}</h1>
            <p class="mb-0">
                <span class="badge text-bg-primary">{{ __('gh.order_status.'.$order->status) }}</span>
                <span class="text-body-secondary small">· {{ __('gh.driver.task_pickup') }}</span>
            </p>
        </div>
        <a href="{{ route('gh.driver.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver.back') }}</a>
    </div>

    @include('gh.driver.partials.address-block', ['order' => $order])

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.pickup_window') }}</h2></div>
        <div class="card-body small">
            <span class="hp-value num-ltr">
                {{ $order->pickup_starts_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}
                –
                {{ $order->pickup_ends_at?->timezone($displayTz)->translatedFormat('H:i') }}
            </span>
            @if ($order->scheduling_notes)
                <p class="mt-2 mb-0">{{ $order->scheduling_notes }}</p>
            @endif
            @if ($order->operational_notes)
                <p class="mt-2 mb-0 text-body-secondary">{{ $order->operational_notes }}</p>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.lines') }}</h2></div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @foreach ($order->lines as $line)
                    <li class="list-group-item">
                        <div class="fw-semibold">{{ app()->getLocale()==='ar' ? $line->product_name_ar : ($line->product_name_en ?: $line->product_name_ar) }}</div>
                        <div class="small text-body-secondary">{{ app()->getLocale()==='ar' ? $line->treatment_name_ar : ($line->treatment_name_en ?: $line->treatment_name_ar) }}</div>
                        <div class="small">{{ __('gh.orders.requested_qty') }}: <span class="hp-value num-ltr">{{ $line->quantity }}</span></div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    @if ($canConfirm)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.confirm_pickup') }}</h2></div>
            <div class="card-body">
                <p class="small text-body-secondary">{{ __('gh.orders.confirm_pickup_hint') }}</p>
                <form method="post" action="{{ route('gh.orders.confirm-pickup', $order) }}" id="gh-driver-pickup-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="pickup_occurred_at">{{ __('gh.orders.actual_pickup_at') }}</label>
                        <input id="pickup_occurred_at" type="datetime-local" name="occurred_at" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pickup_notes">{{ __('gh.orders.pickup_notes') }}</label>
                        <textarea id="pickup_notes" name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                    </div>

                    <h3 class="h6">{{ __('gh.orders.received_qty') }}</h3>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle" id="gh-recv-table">
                            <thead>
                            <tr>
                                <th>{{ __('gh.fields.product') }}</th>
                                <th style="width:6rem">{{ __('gh.orders.requested_qty') }}</th>
                                <th style="width:7rem">{{ __('gh.orders.received_qty') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($order->lines as $i => $line)
                                <tr>
                                    <td>
                                        <div class="small fw-semibold">{{ app()->getLocale()==='ar' ? $line->product_name_ar : ($line->product_name_en ?: $line->product_name_ar) }}</div>
                                        <div class="small text-body-secondary">{{ app()->getLocale()==='ar' ? $line->treatment_name_ar : ($line->treatment_name_en ?: $line->treatment_name_ar) }}</div>
                                        <input type="hidden" name="received[{{ $i }}][order_line_id]" value="{{ $line->id }}">
                                        <input type="hidden" name="received[{{ $i }}][offering_id]" value="{{ $line->offering_id }}">
                                    </td>
                                    <td class="num-ltr hp-value">{{ $line->quantity }}</td>
                                    <td>
                                        <input type="number" min="0" max="9999" class="form-control form-control-lg"
                                               name="received[{{ $i }}][quantity]"
                                               value="{{ old('received.'.$i.'.quantity', $line->quantity) }}" required>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($offerings->isNotEmpty())
                        <div class="mb-3">
                            <label class="form-label" for="extra_offering">{{ __('gh.driver.add_received') }}</label>
                            <div class="input-group">
                                <select id="extra_offering" class="form-select">
                                    <option value="">{{ __('gh.driver.select_offering') }}</option>
                                    @foreach ($offerings as $row)
                                        @php $o = $row['offering']; @endphp
                                        <option value="{{ $o->id }}"
                                                data-label="{{ app()->getLocale()==='ar' ? ($o->product?->name_ar.' / '.$o->treatment?->name_ar) : (($o->product?->name_en ?: $o->product?->name_ar).' / '.($o->treatment?->name_en ?: $o->treatment?->name_ar)) }}">
                                            {{ app()->getLocale()==='ar' ? ($o->product?->name_ar.' / '.$o->treatment?->name_ar) : (($o->product?->name_en ?: $o->product?->name_ar).' / '.($o->treatment?->name_en ?: $o->treatment?->name_ar)) }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-primary" id="gh-add-extra">{{ __('gh.invoices.add') }}</button>
                            </div>
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary btn-lg w-100" id="gh-confirm-pickup-btn">{{ __('gh.orders.confirm_pickup') }}</button>
                </form>
            </div>
        </div>
    @elseif ($order->status === \App\Support\GeneralHouse\GhOrderStatus::WORKING)
        <div class="alert alert-success">{{ __('gh.flash.pickup_confirmed') }}</div>
    @endif

    @if ($canFail)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.failed_pickup') }}</h2></div>
            <div class="card-body">
                <form method="post" action="{{ route('gh.orders.failed-pickup', $order) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="fail_reason">{{ __('gh.driver.fail_reason') }}</label>
                        <select id="fail_reason" name="reason_code" class="form-select" required>
                            <option value="">{{ __('gh.driver.select_reason') }}</option>
                            @foreach ($failReasons as $code)
                                <option value="{{ $code }}">{{ __('gh.attempt_reason.'.$code) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="fail_notes">{{ __('gh.fields.notes') }}</label>
                        <textarea id="fail_notes" name="notes" rows="2" class="form-control"></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-warning btn-lg w-100">{{ __('gh.orders.record_failed_pickup') }}</button>
                </form>
            </div>
        </div>
    @endif

    @if ($order->attempts->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.attempts') }}</h2></div>
            <ul class="list-group list-group-flush small">
                @foreach ($order->attempts as $attempt)
                    <li class="list-group-item">
                        <span class="badge text-bg-warning">{{ $attempt->attempt_type }}</span>
                        <span class="num-ltr">{{ $attempt->occurred_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}</span>
                        @if (!empty($attempt->payload['reason_code']))
                            · {{ __('gh.attempt_reason.'.$attempt->payload['reason_code']) }}
                        @endif
                        @if ($attempt->notes) — {{ $attempt->notes }} @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

@if ($canConfirm)
@push('scripts')
<script>
(() => {
  const form = document.getElementById('gh-driver-pickup-form');
  const btn = document.getElementById('gh-confirm-pickup-btn');
  form?.addEventListener('submit', () => { if (btn) { btn.disabled = true; } });
  const tbody = document.querySelector('#gh-recv-table tbody');
  const addBtn = document.getElementById('gh-add-extra');
  const sel = document.getElementById('extra_offering');
  addBtn?.addEventListener('click', () => {
    const opt = sel?.selectedOptions?.[0];
    if (!opt?.value) return;
    const i = tbody.querySelectorAll('tr').length;
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><div class="small fw-semibold">${opt.dataset.label || opt.textContent}</div>
      <input type="hidden" name="received[${i}][offering_id]" value="${opt.value}"></td>
      <td class="small text-body-secondary">—</td>
      <td><input type="number" min="1" max="9999" class="form-control form-control-lg" name="received[${i}][quantity]" value="1" required></td>`;
    tbody.appendChild(tr);
    sel.value = '';
  });
})();
</script>
@endpush
@endif
@endsection
