@extends('layouts.app')

@section('title', __('gross.shipping.returns_title', ['name' => $company->name]))

@section('content')
    @php
        $hasFilters = ($filters['from'] ?? '') !== '' || ($filters['to'] ?? '') !== '';
        $reportService = app(\App\Services\Gross\ShippingReturnsReportService::class);
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.shipping.returns_title', ['name' => $company->name]) }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.shipping.returns_subtitle') }}</p>
            <p class="text-body-secondary small mb-0 mt-1">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ __('gross.shipping.returns_location_hint') }}
            </p>
        </div>
        <a href="{{ route('gross.shipping.show', $company) }}" class="btn btn-outline-secondary btn-sm">{{ __('general.back') }}</a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1" for="from">{{ __('gross.fields.date_from') }}</label>
                    <input id="from" name="from" type="date" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1" for="to">{{ __('gross.fields.date_to') }}</label>
                    <input id="to" name="to" type="date" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasFilters)
                        <a href="{{ route('gross.shipping.returns', $company) }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('gross.shipping.returns_total_events') }}</div>
                    <div class="fs-5 fw-semibold num-ltr">{{ $totals['event_count'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('gross.shipping.returns_total_pieces') }}</div>
                    <div class="fs-5 fw-semibold num-ltr">{{ $totals['total_pieces'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if ($entries->isEmpty())
                <x-empty-state :title="__('gross.shipping.returns_empty')" icon="bi-arrow-return-left" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.date') }}</th>
                                <th>{{ __('gross.fields.type') }}</th>
                                <th>{{ __('gross.fields.invoice_number') }}</th>
                                <th>{{ __('gross.fields.customer') }}</th>
                                <th>{{ __('gross.fields.item') }}</th>
                                <th class="col-num">{{ __('gross.fields.qty_pieces') }}</th>
                                <th>{{ __('gross.shipping.returns_col_location') }}</th>
                                <th>{{ __('gross.shipping.returns_col_invoice_now') }}</th>
                                <th>{{ __('gross.fields.actor') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                @php
                                    $invoice = $entry->shipmentAllocation?->line?->invoice;
                                    $kind = $reportService->returnKind($entry);
                                    $location = $reportService->locationAfterReturn($entry);
                                    $snapshot = $reportService->currentAllocationSnapshot($entry);
                                @endphp
                                <tr>
                                    <td class="small">
                                        <span class="num-ltr">{{ $entry->created_at->translatedFormat('d M Y H:i') }}</span>
                                    </td>
                                    <td>{{ __('gross.shipping.return_kind.'.$kind) }}</td>
                                    <td>
                                        @if ($invoice)
                                            <a href="{{ route('gross.sales.show', $invoice) }}" class="num-ltr link-body-emphasis text-decoration-none">
                                                {{ $invoice->invoice_number }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $invoice?->customer_name ?: '—' }}</td>
                                    <td>
                                        @if ($entry->item)
                                            <div class="d-flex align-items-center gap-2">
                                                <x-gross.item-thumb :item="$entry->item" size="2.25rem" />
                                                <div>
                                                    <span>{{ $entry->item->name }}</span>
                                                    <div class="small text-body-secondary num-ltr">{{ $entry->item->code }}</div>
                                                </div>
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="col-num">
                                        <span class="num-ltr">{{ $entry->qty_pieces }}</span>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">
                                            {{ __('gross.shipping.return_location.'.$location) }}
                                        </span>
                                    </td>
                                    <td class="small">
                                        @if ($snapshot === null)
                                            —
                                        @elseif ($snapshot['with_courier'] > 0)
                                            <span class="text-warning fw-semibold">
                                                {{ __('gross.shipping.returns_invoice_with_courier', ['count' => $snapshot['with_courier']]) }}
                                            </span>
                                        @elseif ($snapshot['delivered'] > 0)
                                            {{ __('gross.shipping.returns_invoice_delivered', ['count' => $snapshot['delivered']]) }}
                                        @else
                                            {{ __('gross.shipping.returns_invoice_all_returned') }}
                                        @endif
                                    </td>
                                    <td>{{ $entry->creator?->localizedName() ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $entries->links() }}</div>
            @endif
        </div>
    </div>
@endsection
