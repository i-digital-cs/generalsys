@extends('layouts.app')

@section('title', __('gross.stock.movements_title'))

@section('content')
    @php
        $hasFilters = ($filters['q'] ?? '') !== ''
            || ($filters['type'] ?? '') !== ''
            || ! empty($filters['item_id'])
            || ($filters['from'] ?? '') !== ''
            || ($filters['to'] ?? '') !== '';
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.stock.movements_title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.stock.movements_subtitle') }}</p>
        </div>
        <a href="{{ route('gross.stock.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.stock.title') }}</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"
                           placeholder="{{ __('gross.stock.movements_search') }}" autocomplete="off">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1" for="type">{{ __('gross.fields.type') }}</label>
                    <select id="type" name="type" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>
                                {{ __('gross.movement_types.'.$type) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1" for="item_id">{{ __('gross.fields.item') }}</label>
                    <select id="item_id" name="item_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" @selected((string) ($filters['item_id'] ?? '') === (string) $item->id)>
                                {{ $item->code }} — {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1" for="from">{{ __('gross.fields.date_from') }}</label>
                    <input id="from" name="from" type="date" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1" for="to">{{ __('gross.fields.date_to') }}</label>
                    <input id="to" name="to" type="date" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasFilters)
                        <a href="{{ route('gross.stock.movements') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            @if ($movements->isEmpty())
                <x-empty-state :title="__('gross.stock.movements_empty')" icon="bi-arrow-left-right" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.date') }}</th>
                                <th>{{ __('gross.fields.item') }}</th>
                                <th>{{ __('gross.fields.type') }}</th>
                                <th class="col-num">{{ __('gross.fields.qty') }}</th>
                                <th>{{ __('gross.fields.unit') }}</th>
                                <th class="col-num">{{ __('gross.fields.qty_pieces') }}</th>
                                <th>{{ __('gross.fields.actor') }}</th>
                                <th>{{ __('gross.fields.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($movements as $row)
                                <tr>
                                    <td class="small"><span class="num-ltr">{{ $row->created_at?->translatedFormat('d M Y H:i') }}</span></td>
                                    <td>
                                        <span class="num-ltr">{{ $row->item?->code }}</span>
                                        <div class="small text-body-secondary">{{ $row->item?->name }}</div>
                                    </td>
                                    <td>{{ __('gross.movement_types.'.$row->type) }}</td>
                                    <td class="col-num"><span class="num-ltr">{{ $row->qty }}</span></td>
                                    <td>{{ __('gross.units.'.$row->unit) }}</td>
                                    <td class="col-num"><span class="num-ltr">{{ $row->qty_pieces }}</span></td>
                                    <td class="small">{{ $row->creator?->localizedName() ?: '—' }}</td>
                                    <td class="small text-body-secondary">{{ $row->notes ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $movements->links() }}</div>
            @endif
        </div>
    </div>
@endsection
