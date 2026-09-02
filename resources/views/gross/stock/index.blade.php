@extends('layouts.app')

@section('title', __('gross.stock.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.stock.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.stock.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gross.stock.movements') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.stock.movements_title') }}</a>
            <a href="{{ route('gross.items.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.items.title') }}</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($canAdjust)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">{{ __('gross.stock.adjust') }}</h2>
                <form method="post" action="{{ route('gross.stock.adjust') }}" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="unit" value="PIECE">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label mb-1" for="item_id">{{ __('gross.fields.item') }}</label>
                        <select id="item_id" name="item_id" class="form-select form-select-sm @error('item_id') is-invalid @enderror" required>
                            <option value="">—</option>
                            @foreach ($adjustItems as $item)
                                <option value="{{ $item->id }}" @selected((string) old('item_id') === (string) $item->id)>
                                    {{ $item->code }} — {{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label mb-1" for="direction">{{ __('gross.stock.direction') }}</label>
                        <select id="direction" name="direction" class="form-select form-select-sm" required>
                            <option value="in" @selected(old('direction', 'in') === 'in')>{{ __('gross.stock.direction_in') }}</option>
                            <option value="out" @selected(old('direction') === 'out')>{{ __('gross.stock.direction_out') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label mb-1" for="qty">{{ __('gross.fields.qty') }}</label>
                        <div class="input-group input-group-sm">
                            <input id="qty" name="qty" type="number" min="1"
                                   class="form-control @error('qty') is-invalid @enderror"
                                   value="{{ old('qty', 1) }}" required>
                            <span class="input-group-text">{{ __('gross.units.PIECE') }}</span>
                        </div>
                        @error('qty')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label mb-1" for="notes">{{ __('gross.fields.notes') }}</label>
                        <input id="notes" name="notes" class="form-control form-control-sm" value="{{ old('notes') }}" maxlength="1000">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label mb-1 d-none d-lg-block">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('general.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"
                           placeholder="{{ __('gross.stock.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-md-auto">
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="low_stock" value="1" id="low_stock"
                               @checked(! empty($filters['low_stock']))>
                        <label class="form-check-label" for="low_stock">{{ __('gross.fields.low_stock') }}</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if (($filters['q'] ?? '') !== '' || ! empty($filters['low_stock']))
                        <a href="{{ route('gross.stock.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            <p class="text-body-secondary small mb-3">
                {{ __('gross.stock.location_hint') }}
            </p>

            @if ($items->isEmpty())
                <x-empty-state
                    :title="(($filters['q'] ?? '') !== '' || ! empty($filters['low_stock'])) ? __('general.no_filter_results') : __('gross.stock.empty')"
                    icon="bi-boxes" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 3.5rem"></th>
                                <th>{{ __('gross.fields.code') }}</th>
                                <th>{{ __('gross.fields.name') }}</th>
                                <th class="col-num">{{ __('gross.fields.total_pieces') }}</th>
                                <th class="col-num">{{ __('gross.fields.on_hand') }}</th>
                                <th class="col-num">{{ __('gross.fields.with_courier') }}</th>
                                <th class="col-num">{{ __('gross.fields.lost') }}</th>
                                <th class="col-num">{{ __('gross.fields.reorder_level') }}</th>
                                <th class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                @php
                                    $thumb = $item->images->first();
                                    $onHand = (int) ($item->balance?->qty_on_hand_pieces ?? 0);
                                    $withCourier = (int) ($item->balance?->qty_with_courier_pieces ?? 0);
                                    $lost = (int) ($item->balance?->qty_lost_pieces ?? 0);
                                    $total = $onHand + $withCourier + $lost;
                                    $breakdown = $courierBreakdown[$item->id] ?? [];
                                    $summary = $courierSummary[$item->id] ?? [
                                        'awaiting_delivery' => 0,
                                        'refused_with_courier' => 0,
                                        'total_with_courier' => 0,
                                    ];
                                @endphp
                                <tr @class(['table-warning' => $item->isLowStock()])>
                                    <td>
                                        @if ($thumb)
                                            <img src="{{ route('gross.items.images.show', [$item, $thumb]) }}"
                                                 alt="" class="rounded border"
                                                 style="width: 2.5rem; height: 2.5rem; object-fit: cover">
                                        @else
                                            <span class="d-inline-flex align-items-center justify-content-center rounded border text-body-secondary"
                                                  style="width: 2.5rem; height: 2.5rem">
                                                <i class="bi bi-image" aria-hidden="true"></i>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="fw-semibold"><span class="num-ltr">{{ $item->code }}</span></td>
                                    <td>
                                        {{ $item->name }}
                                        @if ($item->isLowStock())
                                            <span class="badge text-bg-warning ms-1">{{ __('gross.fields.low_stock') }}</span>
                                        @endif
                                    </td>
                                    <td class="col-num fw-semibold"><span class="num-ltr">{{ $total }}</span></td>
                                    <td class="col-num"><span class="num-ltr">{{ $onHand }}</span></td>
                                    <td class="col-num">
                                        <span class="num-ltr">{{ $withCourier }}</span>
                                        @if (($summary['awaiting_delivery'] ?? 0) > 0)
                                            <div class="small text-primary">
                                                <span class="num-ltr">{{ $summary['awaiting_delivery'] }}</span> {{ __('gross.stock.waiting_client') }}
                                            </div>
                                        @endif
                                        @if (($summary['refused_with_courier'] ?? 0) > 0)
                                            <div class="small text-warning">
                                                <span class="num-ltr">{{ $summary['refused_with_courier'] }}</span> {{ __('gross.stock.refused_with_courier') }}
                                            </div>
                                        @endif
                                        @if ($breakdown !== [])
                                            @include('gross.partials.courier-breakdown-list', ['breakdown' => $breakdown])
                                        @endif
                                    </td>
                                    <td class="col-num"><span class="num-ltr">{{ $lost }}</span></td>
                                    <td class="col-num"><span class="num-ltr">{{ $item->reorder_level }}</span></td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('gross.items.show', $item) }}" class="btn btn-sm btn-outline-primary">{{ __('general.view') }}</a>
                                        <a href="{{ route('gross.stock.movements', ['item_id' => $item->id]) }}"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="{{ __('gross.stock.movements_title') }}">
                                            <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                                            <span class="visually-hidden">{{ __('gross.stock.movements_title') }}</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $items->links() }}</div>
            @endif
        </div>
    </div>
@endsection
