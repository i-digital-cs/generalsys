@php
    $count = (int) ($count ?? 0);
    $items = $items ?? collect();
    $stockUrl = $stockUrl ?? route('gross.stock.index', ['low_stock' => 1]);
@endphp

@if ($count > 0)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent border-0 pt-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-warning" aria-hidden="true"></i>
                <h2 class="h6 mb-0">{{ __('gross.low_stock_alert.title') }}</h2>
                <span class="badge text-bg-warning num-ltr">{{ $count }}</span>
            </div>
            <a href="{{ $stockUrl }}" class="btn btn-sm btn-outline-warning">
                {{ __('gross.low_stock_alert.cta') }}
            </a>
        </div>
        <div class="card-body pt-0">
            <p class="text-body-secondary small mb-2">{{ __('gross.low_stock_alert.subtitle') }}</p>
            <div class="table-responsive">
                <table class="table table-hr table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 3.5rem"></th>
                            <th>{{ __('gross.fields.code') }}</th>
                            <th>{{ __('gross.fields.name') }}</th>
                            <th class="col-num">{{ __('gross.fields.on_hand') }}</th>
                            <th class="col-num">{{ __('gross.fields.reorder_level') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>
                                    <x-gross.item-thumb :item="$item" size="2.25rem" />
                                </td>
                                <td>
                                    <a href="{{ route('gross.items.show', $item) }}" class="num-ltr link-body-emphasis text-decoration-none">
                                        {{ $item->code }}
                                    </a>
                                </td>
                                <td>{{ $item->name }}</td>
                                <td class="col-num">
                                    <span class="num-ltr">{{ (int) ($item->balance?->qty_on_hand_pieces ?? 0) }}</span>
                                </td>
                                <td class="col-num">
                                    <span class="num-ltr">{{ $item->reorder_level }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($count > $items->count())
                <p class="text-body-secondary small mb-0 mt-2">
                    {{ __('gross.low_stock_alert.more', ['count' => $count - $items->count()]) }}
                </p>
            @endif
        </div>
    </div>
@endif
