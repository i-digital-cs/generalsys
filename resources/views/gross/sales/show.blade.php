@extends('layouts.app')

@section('title', $invoice->invoice_number)

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 d-print-none">
        <a href="{{ route('gross.sales.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.back') }}</a>

        <div class="ms-auto overflow-x-auto">
            <div class="d-flex flex-nowrap align-items-center gap-2 pb-1">
            @if ($invoice->isDraft())
                <a href="{{ route('gross.sales.excel', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_excel') }}">
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_excel') }}</span>
                </a>
                <a href="{{ route('gross.sales.pdf', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_pdf') }}">
                    <i class="bi bi-filetype-pdf" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_pdf') }}</span>
                </a>
                @can('update', $invoice)
                    <a href="{{ route('gross.sales.edit', $invoice) }}" class="btn btn-outline-primary btn-sm flex-shrink-0">{{ __('general.edit') }}</a>
                @endcan
                @can('cancel', $invoice)
                    <form method="post" action="{{ route('gross.sales.cancel', $invoice) }}" class="d-inline-flex mb-0 flex-shrink-0">@csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('gross.sales.cancel') }}</button>
                    </form>
                @endcan
                @can('confirm', $invoice)
                    <form method="post" action="{{ route('gross.sales.confirm', $invoice) }}" class="d-inline-flex mb-0 flex-shrink-0">@csrf
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('gross.sales.confirm') }}</button>
                    </form>
                @endcan
            @endif

            @if ($invoice->isConfirmed())
                <a href="{{ route('gross.sales.excel', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_excel') }}">
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_excel') }}</span>
                </a>
                <a href="{{ route('gross.sales.pdf', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_pdf') }}">
                    <i class="bi bi-filetype-pdf" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_pdf') }}</span>
                </a>
                @can('markDelivered', $invoice)
                    <form method="post" action="{{ route('gross.sales.deliver', $invoice) }}" class="d-inline-flex mb-0 flex-shrink-0">@csrf
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('gross.sales.mark_delivered') }}</button>
                    </form>
                @endcan
                @can('collectCod', $invoice)
                    <span class="d-inline-flex mb-0 flex-shrink-0">
                        @include('gross.partials.collect-cod-modal', [
                            'invoice' => $invoice,
                            'treasuryAccounts' => $treasuryAccounts ?? collect(),
                            'canPostTreasury' => $canPostTreasury ?? false,
                        ])
                    </span>
                @endcan
                @can('refuse', $invoice)
                    <form method="post" action="{{ route('gross.sales.refuse', $invoice) }}"
                          class="d-inline-flex flex-nowrap align-items-center mb-0 flex-shrink-0">
                        @csrf
                        <input type="text" name="refuse_reason"
                               class="form-control form-control-sm"
                               style="width: 9.5rem"
                               placeholder="{{ __('gross.fields.refuse_reason') }}">
                        <button type="submit" class="btn btn-warning btn-sm">{{ __('gross.sales.refuse') }}</button>
                    </form>
                @endcan
                @can('cancel', $invoice)
                    <form method="post" action="{{ route('gross.sales.cancel', $invoice) }}" class="d-inline-flex mb-0 flex-shrink-0">@csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('gross.sales.cancel') }}</button>
                    </form>
                @endcan
            @endif

            @if ($invoice->isRefused())
                <div class="d-inline-flex flex-nowrap align-items-center gap-2 mb-0 flex-shrink-0">
                    @can('receiveRefused', $invoice)
                        <form method="post" action="{{ route('gross.sales.receive-refused', $invoice) }}" class="d-inline-flex mb-0">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">
                                {{ __('gross.sales.receive_refused_pieces') }}
                                <span class="badge text-bg-light ms-1 num-ltr">{{ $invoice->refusedPiecesPendingHandover() }}</span>
                            </button>
                        </form>
                    @endcan
                </div>
            @endif

            @if (! $invoice->isDraft() && ! $invoice->isConfirmed())
                <a href="{{ route('gross.sales.excel', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_excel') }}">
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_excel') }}</span>
                </a>
                <a href="{{ route('gross.sales.pdf', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_pdf') }}">
                    <i class="bi bi-filetype-pdf" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_pdf') }}</span>
                </a>
            @endif
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger d-print-none">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border shadow-sm mb-3" data-gross-invoice-doc>
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-4 pb-3 border-bottom">
                <div>
                    <div class="text-body-secondary small">{{ config('app.name') }} · {{ __('gross.sales.title') }}</div>
                    <h1 class="h3 mb-1"><span class="num-ltr">{{ $invoice->invoice_number }}</span></h1>
                    <div class="small text-body-secondary">
                        {{ $invoice->created_at?->translatedFormat('d M Y H:i') }}
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge text-bg-secondary fs-6">{{ __('gross.statuses.'.$invoice->status) }}</span>
                    <div class="mt-2 small">{{ __('gross.fields.currency') }}: <strong>{{ $invoice->currency }}</strong></div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <h2 class="h6 text-body-secondary">{{ __('gross.fields.customer') }}</h2>
                    <div class="fw-semibold">
                        @if ($invoice->customer_id)
                            <a href="{{ route('gross.customers.show', $invoice->customer_id) }}" class="link-body-emphasis text-decoration-none">
                                {{ $invoice->customer_name }}
                            </a>
                        @else
                            {{ $invoice->customer_name }}
                        @endif
                    </div>
                    <div class="num-ltr">{{ $invoice->customer_phone }}</div>
                    @if ($invoice->customer_address)
                        <div class="small text-body-secondary">{{ $invoice->customer_address }}</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <h2 class="h6 text-body-secondary">{{ __('gross.sales.invoice_meta') }}</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5">{{ __('gross.fields.sales_person') }}</dt>
                        <dd class="col-7">{{ $invoice->salesUser?->localizedName() ?: '—' }}</dd>
                        <dt class="col-5">{{ __('gross.fields.shipping_company') }}</dt>
                        <dd class="col-7">
                            @if ($invoice->shipping_company_id)
                                <a href="{{ route('gross.shipping.show', $invoice->shipping_company_id) }}" class="link-body-emphasis text-decoration-none">
                                    {{ $invoice->shipping_company_name ?: '—' }}
                                </a>
                            @else
                                {{ $invoice->shipping_company_name ?: '—' }}
                            @endif
                        </dd>
                        <dt class="col-5">{{ __('gross.fields.notes') }}</dt>
                        <dd class="col-7">{{ $invoice->notes ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            @if (! empty($display['refused_progress']))
                @php
                    $rp = $display['refused_progress'];
                @endphp
                <div class="card border-warning mb-4 d-print-none">
                    <div class="card-body">
                        <h2 class="h6 mb-1">{{ __('gross.sales.refused_progress_title') }}</h2>
                        <p class="small text-body-secondary mb-3">{{ __('gross.sales.refused_progress_subtitle') }}</p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach ($rp['steps'] as $step)
                                <span @class([
                                    'badge',
                                    'text-bg-success' => $step['done'] && $step['key'] === 'received',
                                    'text-bg-warning' => $step['current'] ?? false,
                                    'text-bg-secondary' => ! ($step['done'] || ($step['current'] ?? false)),
                                ])>
                                    @if ($step['done'] && $step['key'] !== 'with_courier')
                                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                                    @endif
                                    {{ __($step['label_key']) }}
                                </span>
                            @endforeach
                        </div>
                        @if ($rp['pending_handover'] > 0)
                            <div class="alert alert-warning py-2 small mb-2">
                                {{ __('gross.sales.refused_pending_handover', ['count' => $rp['pending_handover']]) }}
                            </div>
                        @else
                            <div class="alert alert-success py-2 small mb-2">
                                {{ __('gross.sales.refused_handover_complete') }}
                            </div>
                        @endif
                        @if ($rp['delivery_fee_owed'] > 0)
                            <div class="small">
                                <span class="text-body-secondary">{{ __('gross.sales.delivery_fee_owed') }}:</span>
                                <span class="fw-semibold num-ltr"><x-money :amount="$rp['delivery_fee_owed']" :currency="$invoice->currency" /></span>
                                <span class="text-body-secondary">— {{ __('gross.sales.delivery_fee_owed_hint') }}</span>
                            </div>
                        @endif
                        @if ($invoice->refuse_reason)
                            <div class="small text-body-secondary mt-2">{{ __('gross.fields.refuse_reason') }}: {{ $invoice->refuse_reason }}</div>
                        @endif
                    </div>
                </div>
            @endif

            @if (! empty($display['has_tracking']))
                @php
                    $ps = $display['piece_summary'];
                @endphp
                <div class="card border-0 bg-light mb-4 d-print-none">
                    <div class="card-body">
                        <h2 class="h6 mb-1">{{ __('gross.sales.piece_tracking_title') }}</h2>
                        <p class="small text-body-secondary mb-3">{{ __('gross.sales.piece_tracking_subtitle') }}</p>
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-md-3">
                                <div class="small text-body-secondary">{{ __('gross.sales.qty_shipped') }}</div>
                                <div class="fs-5 fw-semibold num-ltr">{{ $ps['total_on_invoice'] }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="small text-body-secondary">{{ __('gross.fields.returned') }}</div>
                                <div class="fs-5 fw-semibold num-ltr text-warning">{{ $ps['total_returned'] }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="small text-body-secondary">{{ __('gross.sales.returned_with_courier') }}</div>
                                <div class="fs-5 fw-semibold num-ltr text-warning">{{ $ps['total_returned_with_courier'] }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="small text-body-secondary">{{ __('gross.sales.awaiting_delivery') }}</div>
                                <div class="fs-5 fw-semibold num-ltr text-primary">{{ $ps['total_awaiting_delivery'] }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="small text-body-secondary">{{ __('gross.fields.with_courier') }}</div>
                                <div class="fs-5 fw-semibold num-ltr">{{ $ps['total_with_courier'] }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="small text-body-secondary">{{ __('gross.fields.delivered') }}</div>
                                <div class="fs-5 fw-semibold num-ltr">{{ $ps['total_delivered'] }}</div>
                            </div>
                        </div>
                        <div class="small num-ltr">
                            {{ __('gross.sales.piece_tracking_equation', [
                                'total' => $ps['total_on_invoice'],
                                'returned' => $ps['total_returned_with_courier'],
                                'courier' => $ps['total_with_courier'],
                                'awaiting' => $ps['total_awaiting_delivery'],
                                'delivered' => $ps['total_delivered'],
                            ]) }}
                            @if ($ps['total_lost'] > 0)
                                + <span class="num-ltr">{{ $ps['total_lost'] }}</span> {{ __('gross.fields.lost') }}
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('gross.fields.code') }}</th>
                            <th>{{ __('gross.fields.name') }}</th>
                            <th>{{ __('gross.fields.size') }}</th>
                            @if (! empty($display['has_tracking']))
                                <th class="col-num">{{ __('gross.sales.qty_shipped') }}</th>
                                <th class="col-num">{{ __('gross.fields.returned') }}</th>
                                <th class="col-num">{{ __('gross.sales.awaiting_delivery') }}</th>
                                <th class="col-num">{{ __('gross.fields.delivered') }}</th>
                            @else
                                <th class="col-num">{{ __('gross.fields.qty') }}</th>
                            @endif
                            <th class="col-num">{{ __('gross.fields.unit_price') }}</th>
                            <th class="col-num">{{ __('gross.fields.line_discount') }}</th>
                            <th class="col-num">{{ __('gross.fields.line_total') }}</th>
                            @if (! empty($display['has_tracking']))
                                <th class="col-num">{{ __('gross.sales.returned_amount') }}</th>
                                <th class="col-num">{{ __('gross.sales.net_line_total') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($display['has_tracking'] ?? false) ? $display['lines'] : $invoice->lines->map(fn ($line) => ['line' => $line]) as $row)
                            @php
                                $line = $row['line'] ?? $row;
                                $isTracked = ! empty($display['has_tracking']);
                            @endphp
                            <tr>
                                <td>
                                    @if ($line->item_id)
                                        <a href="{{ route('gross.items.show', $line->item_id) }}" class="num-ltr link-body-emphasis text-decoration-none">
                                            {{ $line->item_code_snapshot }}
                                        </a>
                                    @else
                                        <span class="num-ltr">{{ $line->item_code_snapshot }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($line->item_id)
                                        <a href="{{ route('gross.items.show', $line->item_id) }}" class="link-body-emphasis text-decoration-none">
                                            {{ $line->item_name_snapshot }}
                                        </a>
                                    @else
                                        {{ $line->item_name_snapshot }}
                                    @endif
                                </td>
                                <td>{{ $line->size_snapshot ?: '—' }}</td>
                                @if ($isTracked)
                                    <td class="col-num"><span class="num-ltr">{{ $row['qty_shipped'] }}</span></td>
                                    <td class="col-num">
                                        @if ($row['qty_returned'] > 0)
                                            <span class="num-ltr text-warning fw-semibold">{{ $row['qty_returned'] }}</span>
                                        @else
                                            <span class="num-ltr">0</span>
                                        @endif
                                    </td>
                                    <td class="col-num"><span class="num-ltr">{{ $row['qty_awaiting_delivery'] }}</span></td>
                                    <td class="col-num"><span class="num-ltr">{{ $row['qty_delivered'] }}</span></td>
                                @else
                                    <td class="col-num"><span class="num-ltr">{{ $line->qty }}</span></td>
                                @endif
                                <td class="col-num">
                                    <span class="num-ltr"><x-money :amount="$line->unit_price" :currency="$invoice->currency" /></span>
                                </td>
                                <td class="col-num">
                                    <span class="num-ltr"><x-money :amount="$line->discount_amount" :currency="$invoice->currency" /></span>
                                    @if ((float) $line->discount_percent > 0)
                                        <div class="small text-body-secondary num-ltr">{{ number_format((float) $line->discount_percent, 2) }}%</div>
                                    @endif
                                </td>
                                <td class="col-num fw-semibold">
                                    <span class="num-ltr"><x-money :amount="$line->line_total" :currency="$invoice->currency" /></span>
                                </td>
                                @if ($isTracked)
                                    <td class="col-num">
                                        @if ($row['returned_amount'] > 0)
                                            <span class="num-ltr text-warning">−<x-money :amount="$row['returned_amount']" :currency="$invoice->currency" /></span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="col-num fw-semibold">
                                        <span class="num-ltr"><x-money :amount="$row['net_line_total']" :currency="$invoice->currency" /></span>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (! empty($display['has_tracking']) && ($display['returned_merchandise'] ?? 0) > 0)
                <div class="alert alert-warning py-2 small mb-3 d-print-none">
                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                    {{ __('gross.sales.piece_status_hint') }}
                </div>
            @endif

            <div class="row justify-content-end">
                <div class="col-md-6">
                    <dl class="row mb-0">
                        <dt class="col-6">{{ __('gross.fields.subtotal') }}</dt>
                        <dd class="col-6 text-end"><span class="num-ltr"><x-money :amount="$invoice->subtotal" :currency="$invoice->currency" /></span></dd>
                        @if (! empty($display['has_tracking']) && ($display['returned_merchandise'] ?? 0) > 0)
                            <dt class="col-6 text-warning">{{ __('gross.sales.returned_merchandise') }}</dt>
                            <dd class="col-6 text-end text-warning">
                                <span class="num-ltr">−<x-money :amount="$display['returned_merchandise']" :currency="$invoice->currency" /></span>
                            </dd>
                            <dt class="col-6">{{ __('gross.sales.net_subtotal') }}</dt>
                            <dd class="col-6 text-end fw-semibold">
                                <span class="num-ltr"><x-money :amount="$display['net_subtotal']" :currency="$invoice->currency" /></span>
                            </dd>
                        @endif
                        <dt class="col-6">{{ __('gross.fields.invoice_discount') }}</dt>
                        <dd class="col-6 text-end">
                            <span class="num-ltr"><x-money :amount="$invoice->discount_amount" :currency="$invoice->currency" /></span>
                            @if ((float) $invoice->discount_percent > 0)
                                <span class="small text-body-secondary">({{ number_format((float) $invoice->discount_percent, 2) }}%)</span>
                            @endif
                            @if (! empty($display['has_tracking']) && ($display['returned_merchandise'] ?? 0) > 0 && ($display['net_discount'] ?? 0) != (float) $invoice->discount_amount)
                                <div class="small text-body-secondary">
                                    {{ __('gross.sales.net_discount') }}:
                                    <span class="num-ltr"><x-money :amount="$display['net_discount']" :currency="$invoice->currency" /></span>
                                </div>
                            @endif
                        </dd>
                        <dt class="col-6">{{ __('gross.fields.shipping_fee') }}</dt>
                        <dd class="col-6 text-end">
                            <span class="num-ltr"><x-money :amount="$invoice->shipping_fee" :currency="$invoice->currency" /></span>
                        </dd>
                        @if (! $invoice->isDraft())
                            <dt class="col-6">{{ __('gross.fields.cod_status') }}</dt>
                            <dd class="col-6 text-end">
                                {{ __('gross.cod_collection_status.'.$invoice->cod_collection_status) }}
                                @if ($invoice->cod_collection_status === \App\Models\Gross\GrossSalesInvoice::COD_PENDING)
                                    <div class="small fw-semibold text-primary">
                                        {{ __('gross.sales.collectible_amount') }}:
                                        <span class="num-ltr"><x-money :amount="$invoice->collectibleAmount()" :currency="$invoice->currency" /></span>
                                    </div>
                                @elseif ($invoice->cod_collection_status === \App\Models\Gross\GrossSalesInvoice::COD_COLLECTED)
                                    <div class="small text-body-secondary">
                                        <span class="num-ltr"><x-money :amount="$invoice->cod_collected_amount" :currency="$invoice->currency" /></span>
                                    </div>
                                    @if ($invoice->courierCodTreasuryVoucher)
                                        <div class="small mt-1">
                                            <a href="{{ route('gross.treasury.show', $invoice->courierCodTreasuryVoucher) }}" class="num-ltr text-decoration-none">
                                                {{ $invoice->courierCodTreasuryVoucher->voucher_number }}
                                            </a>
                                        </div>
                                    @endif
                                @endif
                            </dd>
                            @if ((float) $invoice->shipping_fee > 0)
                                <dt class="col-6">{{ __('gross.fields.shipping_fee_status') }}</dt>
                                <dd class="col-6 text-end">
                                    @if ($invoice->isRefused())
                                        {{ __('gross.shipping_fee_status.REFUSED_OWED') }}
                                    @else
                                        {{ __('gross.shipping_fee_status.'.$invoice->shipping_fee_status) }}
                                    @endif
                                </dd>
                            @endif
                        @endif
                        <dt class="col-6 border-top pt-2">{{ __('gross.fields.grand_total') }}</dt>
                        <dd class="col-6 text-end border-top pt-2 fw-bold fs-5">
                            <span class="num-ltr"><x-money :amount="$invoice->total" :currency="$invoice->currency" /></span>
                            <div class="small fw-normal text-body-secondary">{{ __('gross.sales.original_total') }}</div>
                        </dd>
                        @if (! empty($display['has_tracking']) && ($display['returned_merchandise'] ?? 0) > 0)
                            <dt class="col-6 border-top pt-2 text-primary">{{ __('gross.sales.net_collectible') }}</dt>
                            <dd class="col-6 text-end border-top pt-2 fw-bold fs-5 text-primary">
                                <span class="num-ltr"><x-money :amount="$display['merchandise_collectible']" :currency="$invoice->currency" /></span>
                                <div class="small fw-normal text-body-secondary">{{ __('gross.sales.net_collectible_hint') }}</div>
                            </dd>
                        @elseif (! $invoice->isDraft() && $invoice->codIsPending())
                            <dt class="col-6 border-top pt-2 text-primary">{{ __('gross.sales.net_collectible') }}</dt>
                            <dd class="col-6 text-end border-top pt-2 fw-bold fs-5 text-primary">
                                <span class="num-ltr"><x-money :amount="$display['merchandise_collectible'] ?? $invoice->collectibleAmount()" :currency="$invoice->currency" /></span>
                                <div class="small fw-normal text-body-secondary">{{ __('gross.sales.net_collectible_hint') }}</div>
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>

    @if ($invoice->isConfirmed())
        <div class="card border-0 shadow-sm d-print-none">
            <div class="card-header bg-transparent fw-semibold">{{ __('gross.sales.allocations') }}</div>
            <div class="card-body">
                @foreach ($invoice->lines as $line)
                    @php
                        $alloc = $line->allocation;
                    @endphp
                    @if ($alloc)
                        <div class="border rounded p-3 mb-3">
                            <div class="fw-semibold mb-2">
                                @if ($line->item_id)
                                    <a href="{{ route('gross.items.show', $line->item_id) }}" class="link-body-emphasis text-decoration-none">
                                        {{ $line->item_code_snapshot }} — {{ $line->item_name_snapshot }}
                                    </a>
                                @else
                                    {{ $line->item_code_snapshot }} — {{ $line->item_name_snapshot }}
                                @endif
                            </div>
                            <div class="small mb-2">
                                <span class="badge text-bg-light border num-ltr">
                                    {{ $alloc->qty_shipped_pieces }}
                                    {{ __('gross.sales.piece_equation_shipped') }}
                                </span>
                                <span class="mx-1">=</span>
                                <span class="num-ltr">{{ $alloc->qty_delivered_pieces }}</span> {{ __('gross.fields.delivered') }}
                                + <span class="num-ltr">{{ $alloc->qty_with_courier_pieces }}</span> {{ __('gross.fields.with_courier') }}
                                @if ($alloc->qty_lost_pieces > 0)
                                    + <span class="num-ltr">{{ $alloc->qty_lost_pieces }}</span> {{ __('gross.fields.lost') }}
                                @endif
                            </div>
                            <div class="small text-body-secondary mb-2">
                                {{ __('gross.sales.partial_return_help') }}
                                @if ($alloc->returnedPendingHandover() > 0)
                                    · {{ __('gross.sales.returned_with_courier') }}: <span class="num-ltr">{{ $alloc->returnedPendingHandover() }}</span>
                                @endif
                            </div>
                            @php
                                $lineDisplay = null;
                                foreach ($display['lines'] ?? [] as $row) {
                                    if (($row['line']->id ?? null) === $line->id) {
                                        $lineDisplay = $row;
                                        break;
                                    }
                                }
                                $stock = $lineDisplay['stock'] ?? null;
                            @endphp
                            @if ($stock)
                                <div class="border-top pt-3 mt-3">
                                    <div class="small fw-semibold mb-2">{{ __('gross.sales.stock_impact_title') }}</div>
                                    <div class="row g-2 small mb-2">
                                        <div class="col-md-3">
                                            <span class="text-body-secondary">{{ __('gross.fields.on_hand') }}:</span>
                                            <span class="fw-semibold num-ltr">{{ $stock['on_hand'] }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="text-body-secondary">{{ __('gross.fields.with_courier') }}:</span>
                                            <span class="fw-semibold num-ltr">{{ $stock['with_courier'] }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="text-body-secondary">{{ __('gross.fields.total_pieces') }}:</span>
                                            <span class="fw-semibold num-ltr">{{ $stock['total_tracked'] }}</span>
                                        </div>
                                    </div>
                                    <div class="small text-body-secondary">
                                        {{ __('gross.sales.stock_invoice_impact', [
                                            'returned' => $stock['on_invoice_returned'],
                                            'courier' => $stock['on_invoice_with_courier'],
                                            'delivered' => $stock['on_invoice_delivered'],
                                        ]) }}
                                    </div>
                                    <div class="small text-body-secondary mt-1">{{ __('gross.sales.stock_on_hand_hint') }}</div>
                                </div>
                            @endif
                            <div class="d-flex flex-wrap gap-2">
                                @can('returnLine', $invoice)
                                    <form method="post" action="{{ route('gross.sales.return', $invoice) }}" class="d-flex flex-wrap align-items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="allocation_id" value="{{ $alloc->id }}">
                                        <label class="small mb-0" for="return-qty-{{ $alloc->id }}">{{ __('gross.fields.return_qty') }}</label>
                                        <input id="return-qty-{{ $alloc->id }}" type="number"
                                               min="1" max="{{ $alloc->deliverableWithCourier() }}"
                                               name="qty_pieces" class="form-control form-control-sm" style="width:6rem"
                                               value="1" required @disabled($alloc->deliverableWithCourier() < 1)>
                                        <span class="small text-body-secondary">
                                            {{ __('gross.sales.return_max', ['max' => $alloc->deliverableWithCourier()]) }}
                                        </span>
                                        <button class="btn btn-sm btn-outline-primary" @disabled($alloc->deliverableWithCourier() < 1)>
                                            {{ __('gross.sales.return') }}
                                        </button>
                                    </form>
                                @endcan
                                @can('lossLine', $invoice)
                                    <form method="post" action="{{ route('gross.sales.loss', $invoice) }}" class="d-flex gap-2">
                                        @csrf
                                        <input type="hidden" name="allocation_id" value="{{ $alloc->id }}">
                                        <input type="number" min="1" name="qty_pieces" class="form-control form-control-sm" style="width:6rem" value="1" required>
                                        <button class="btn btn-sm btn-outline-danger">{{ __('gross.sales.loss') }}</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    @if ($invoice->isRefused())
        <div class="card border-0 shadow-sm d-print-none">
            <div class="card-header bg-transparent fw-semibold">{{ __('gross.sales.allocations') }}</div>
            <div class="card-body">
                @foreach ($invoice->lines as $line)
                    @php
                        $alloc = $line->allocation;
                    @endphp
                    @if ($alloc)
                        <div class="border rounded p-3 mb-3">
                            <div class="fw-semibold mb-2">
                                {{ $line->item_code_snapshot }} — {{ $line->item_name_snapshot }}
                            </div>
                            <div class="small mb-2">
                                <span class="num-ltr">{{ $alloc->qty_returned_pieces }}</span> {{ __('gross.fields.returned') }}
                                · <span class="num-ltr">{{ $alloc->returnedPendingHandover() }}</span> {{ __('gross.sales.returned_with_courier') }}
                                · <span class="num-ltr">{{ $alloc->qty_returned_received_pieces }}</span> {{ __('gross.sales.received_from_courier') }}
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
@endsection
