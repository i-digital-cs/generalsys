@extends('layouts.app')

@section('title', $invoice->invoice_number)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3 d-print-none">
        <div>
            <a href="{{ route('gross.purchases.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.back') }}</a>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if (! $invoice->isDraft())
                <a href="{{ route('gross.purchases.excel', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm"
                   title="{{ __('gross.purchases.export_excel') }}">
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.purchases.export_excel') }}</span>
                </a>
                <a href="{{ route('gross.purchases.pdf', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm"
                   title="{{ __('gross.purchases.export_pdf') }}">
                    <i class="bi bi-filetype-pdf" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.purchases.export_pdf') }}</span>
                </a>
            @endif
            @can('update', $invoice)
                <a href="{{ route('gross.purchases.edit', $invoice) }}" class="btn btn-outline-primary btn-sm">{{ __('general.edit') }}</a>
            @endcan
            @if ($invoice->isDraft())
                @can('confirm', $invoice)
                    <form method="post" action="{{ route('gross.purchases.confirm', $invoice) }}">@csrf
                        <button class="btn btn-primary btn-sm">{{ __('gross.purchases.confirm') }}</button>
                    </form>
                @endcan
            @endif
            @can('delete', $invoice)
                <form method="post" action="{{ route('gross.purchases.destroy', $invoice) }}"
                      onsubmit="return confirm(@js(__('gross.purchases.delete_confirm')))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('general.delete') }}</button>
                </form>
            @endcan
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger d-print-none">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border shadow-sm mb-3">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-4 pb-3 border-bottom">
                <div>
                    <div class="text-body-secondary small">{{ config('app.name') }} · {{ __('gross.purchases.title') }}</div>
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
                    <h2 class="h6 text-body-secondary">{{ __('gross.fields.supplier') }}</h2>
                    <div class="fw-semibold">
                        @if ($invoice->supplier_id)
                            <a href="{{ route('gross.suppliers.show', $invoice->supplier_id) }}" class="link-body-emphasis text-decoration-none">
                                {{ $invoice->supplier_name }}
                            </a>
                            <a href="{{ route('gross.suppliers.statement', $invoice->supplier_id) }}"
                               class="btn btn-link btn-sm p-0 ms-2 align-baseline"
                               title="{{ __('gross.suppliers.statement') }}">
                                <i class="bi bi-journal-text" aria-hidden="true"></i>
                                <span class="visually-hidden">{{ __('gross.suppliers.statement') }}</span>
                            </a>
                        @else
                            {{ $invoice->supplier_name }}
                        @endif
                    </div>
                    <div class="num-ltr">{{ $invoice->supplier_phone }}</div>
                    @if ($invoice->supplier_address)
                        <div class="small text-body-secondary">{{ $invoice->supplier_address }}</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <h2 class="h6 text-body-secondary">{{ __('gross.purchases.invoice_meta') }}</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5">{{ __('gross.fields.created_by') }}</dt>
                        <dd class="col-7">{{ $invoice->creator?->localizedName() ?: '—' }}</dd>
                        <dt class="col-5">{{ __('gross.fields.notes') }}</dt>
                        <dd class="col-7">{{ $invoice->notes ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width: 3.5rem"></th>
                            <th>{{ __('gross.fields.code') }}</th>
                            <th>{{ __('gross.fields.name') }}</th>
                            <th>{{ __('gross.fields.size') }}</th>
                            <th class="col-num">{{ __('gross.fields.qty') }}</th>
                            <th class="col-num">{{ __('gross.fields.qty_returned') }}</th>
                            <th class="col-num">{{ __('gross.fields.qty_remaining') }}</th>
                            <th class="col-num">{{ __('gross.fields.buy_price') }}</th>
                            <th class="col-num">{{ __('gross.fields.sale_price') }}</th>
                            <th class="col-num">{{ __('gross.fields.line_total') }}</th>
                            @if ($display['has_returns'] ?? false)
                                <th class="col-num">{{ __('gross.purchases.returned_amount') }}</th>
                                <th class="col-num">{{ __('gross.purchases.net_line_total') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($display['has_returns'] ?? false) ? $display['lines'] : $invoice->lines->map(fn ($line) => ['line' => $line]) as $row)
                            @php $line = $row['line']; @endphp
                            <tr>
                                <td>
                                    <x-gross.item-thumb :item="$line->item" size="2.5rem" :alt="$line->item_name_snapshot" />
                                </td>
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
                                <td class="col-num"><span class="num-ltr">{{ ($display['has_returns'] ?? false) ? $row['qty_purchased'] : $line->qty_pieces }}</span></td>
                                <td class="col-num">
                                    @if (($display['has_returns'] ?? false) && $row['qty_returned'] > 0)
                                        <span class="num-ltr text-warning fw-semibold">{{ $row['qty_returned'] }}</span>
                                    @else
                                        <span class="num-ltr">{{ ($display['has_returns'] ?? false) ? $row['qty_returned'] : $line->qty_returned_pieces }}</span>
                                    @endif
                                </td>
                                <td class="col-num"><span class="num-ltr">{{ ($display['has_returns'] ?? false) ? $row['qty_remaining'] : $line->remainingPieces() }}</span></td>
                                <td class="col-num"><span class="num-ltr"><x-money :amount="$line->buy_price" :currency="$invoice->currency" /></span></td>
                                <td class="col-num"><span class="num-ltr"><x-money :amount="$line->sale_price" :currency="$invoice->currency" /></span></td>
                                <td class="col-num fw-semibold"><span class="num-ltr"><x-money :amount="$line->line_total" :currency="$invoice->currency" /></span></td>
                                @if ($display['has_returns'] ?? false)
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

            @if (! empty($display['has_returns']) && ($display['returned_merchandise'] ?? 0) > 0)
                <div class="alert alert-warning py-2 small mb-3 d-print-none">
                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                    {{ __('gross.purchases.return_totals_hint') }}
                </div>
            @endif

            <div class="row justify-content-end">
                <div class="col-md-6">
                    <dl class="row mb-0 small">
                        <dt class="col-6">{{ __('gross.fields.subtotal') }}</dt>
                        <dd class="col-6 text-end num-ltr"><x-money :amount="$invoice->subtotal" :currency="$invoice->currency" /></dd>
                        @if (! empty($display['has_returns']) && ($display['returned_merchandise'] ?? 0) > 0)
                            <dt class="col-6 text-warning">{{ __('gross.purchases.returned_merchandise') }}</dt>
                            <dd class="col-6 text-end text-warning num-ltr">
                                −<x-money :amount="$display['returned_merchandise']" :currency="$invoice->currency" />
                            </dd>
                            <dt class="col-6">{{ __('gross.purchases.net_subtotal') }}</dt>
                            <dd class="col-6 text-end fw-semibold num-ltr">
                                <x-money :amount="$display['net_subtotal']" :currency="$invoice->currency" />
                            </dd>
                        @endif
                        <dt class="col-6">{{ __('gross.fields.invoice_discount') }}</dt>
                        <dd class="col-6 text-end num-ltr">
                            <x-money :amount="$invoice->discount_amount" :currency="$invoice->currency" />
                            @if ((float) $invoice->discount_percent > 0)
                                <span class="text-body-secondary">({{ number_format((float) $invoice->discount_percent, 2) }}%)</span>
                            @endif
                            @if (! empty($display['has_returns']) && ($display['returned_merchandise'] ?? 0) > 0 && ($display['net_discount'] ?? 0) != (float) $invoice->discount_amount)
                                <div class="text-body-secondary">
                                    {{ __('gross.purchases.net_discount') }}:
                                    <x-money :amount="$display['net_discount']" :currency="$invoice->currency" />
                                </div>
                            @endif
                        </dd>
                        <dt class="col-6 border-top pt-2 fw-semibold">{{ __('gross.fields.grand_total') }}</dt>
                        <dd class="col-6 text-end border-top pt-2 fw-bold fs-5 num-ltr">
                            <x-money :amount="$invoice->total" :currency="$invoice->currency" />
                            @if (! empty($display['has_returns']) && ($display['returned_merchandise'] ?? 0) > 0)
                                <div class="small fw-normal text-body-secondary">{{ __('gross.purchases.original_total') }}</div>
                            @endif
                        </dd>
                        @if (! empty($display['has_returns']) && ($display['returned_merchandise'] ?? 0) > 0)
                            <dt class="col-6 border-top pt-2 text-primary">{{ __('gross.purchases.net_payable') }}</dt>
                            <dd class="col-6 text-end border-top pt-2 fw-bold fs-5 text-primary num-ltr">
                                <x-money :amount="$display['net_grand_total']" :currency="$invoice->currency" />
                                <div class="small fw-normal text-body-secondary">{{ __('gross.purchases.net_payable_hint') }}</div>
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>

    @can('returnLines', $invoice)
        @php($returnable = $invoice->lines->filter(fn ($l) => $l->remainingPieces() > 0))
        @if ($returnable->isNotEmpty())
            <div class="card border-0 shadow-sm mb-3 d-print-none">
                <div class="card-body">
                    <h2 class="h6 mb-1">{{ __('gross.purchases.return_title') }}</h2>
                    <p class="small text-body-secondary mb-3">{{ __('gross.purchases.return_help') }}</p>
                    <form method="post" action="{{ route('gross.purchases.return', $invoice) }}">
                        @csrf
                        <div class="table-responsive mb-3">
                            <table class="table table-hr table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('gross.fields.item') }}</th>
                                        <th class="col-num">{{ __('gross.fields.qty_remaining') }}</th>
                                        <th style="width: 8rem">{{ __('gross.fields.return_qty') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($returnable as $i => $line)
                                        @php($remainingQty = (int) floor($line->remainingPieces() / max(1, (int) $line->conversion_factor_snapshot)))
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <x-gross.item-thumb :item="$line->item" size="2.25rem" :alt="$line->item_name_snapshot" />
                                                    <div>
                                                        <span class="num-ltr">{{ $line->item_code_snapshot }}</span>
                                                        — {{ $line->item_name_snapshot }}
                                                        <div class="small text-body-secondary">{{ __('gross.units.'.$line->unit) }}</div>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="lines[{{ $i }}][purchase_invoice_line_id]" value="{{ $line->id }}">
                                            </td>
                                            <td class="col-num"><span class="num-ltr">{{ $remainingQty }}</span></td>
                                            <td>
                                                <input type="number" min="0" max="{{ $remainingQty }}"
                                                       name="lines[{{ $i }}][qty]"
                                                       class="form-control form-control-sm"
                                                       value="{{ old('lines.'.$i.'.qty', 0) }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="return_notes">{{ __('gross.fields.notes') }}</label>
                            <textarea id="return_notes" name="notes" class="form-control form-control-sm" rows="2">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm">{{ __('gross.purchases.return') }}</button>
                    </form>
                </div>
            </div>
        @endif
    @endcan

    @if ($invoice->returns->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">{{ __('gross.purchases.returns_history') }}</h2>
                @foreach ($invoice->returns as $ret)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                            <div>
                                <span class="fw-semibold num-ltr">{{ $ret->return_number }}</span>
                                <span class="small text-body-secondary ms-2">
                                    {{ $ret->created_at?->translatedFormat('d M Y H:i') }}
                                    · {{ $ret->creator?->localizedName() }}
                                </span>
                            </div>
                            @if ($ret->notes)
                                <div class="small text-body-secondary">{{ $ret->notes }}</div>
                            @endif
                        </div>
                        <ul class="small mb-0 ps-3">
                            @foreach ($ret->lines as $rl)
                                <li class="d-flex align-items-center gap-2 mb-1">
                                    <x-gross.item-thumb :item="$rl->item ?? $rl->invoiceLine?->item" size="1.75rem" />
                                    <span>
                                        <span class="num-ltr">{{ $rl->invoiceLine?->item_code_snapshot ?? $rl->item?->code }}</span>
                                        — {{ __('gross.fields.qty') }}:
                                        <span class="num-ltr">{{ $rl->qty_pieces }}</span>
                                        · <x-money :amount="$rl->line_total" :currency="$invoice->currency" />
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
