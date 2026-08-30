@extends('layouts.app')

@section('title', $item->code)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $item->name }}</h1>
            <p class="text-body-secondary small mb-0">
                <span class="num-ltr">{{ $item->code }}</span>
                · {{ __('gross.statuses.'.$item->status) }}
                @if ($item->isLowStock())
                    · <span class="text-warning">{{ __('gross.fields.low_stock') }}</span>
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gross.items.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.items.title') }}</a>
            @can('update', $item)
                <a href="{{ route('gross.items.edit', $item) }}" class="btn btn-outline-primary btn-sm">{{ __('general.edit') }}</a>
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('gross.fields.images') }}</h2>
                    @if ($item->images->isEmpty())
                        <x-empty-state :title="__('gross.fields.images_empty')" icon="bi-image" />
                    @else
                        <div class="row g-2">
                            @foreach ($item->images as $image)
                                <div class="col-6">
                                    <a href="{{ route('gross.items.images.show', [$item, $image]) }}" target="_blank" rel="noopener">
                                        <img src="{{ route('gross.items.images.show', [$item, $image]) }}"
                                             alt="{{ $item->name }}"
                                             class="img-fluid rounded border w-100"
                                             style="aspect-ratio: 1 / 1; object-fit: cover">
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('gross.items.details') }}</h2>
                    <dl class="row mb-0 small">
                        @if ($item->category)
                            <dt class="col-sm-4">{{ __('gross.fields.category') }}</dt>
                            <dd class="col-sm-8">{{ $item->category->localizedName() }}</dd>
                        @endif
                        @if ($item->subcategory)
                            <dt class="col-sm-4">{{ __('gross.fields.subcategory') }}</dt>
                            <dd class="col-sm-8">{{ $item->subcategory->localizedName() }}</dd>
                        @endif
                        <dt class="col-sm-4">{{ __('gross.fields.color') }}</dt>
                        <dd class="col-sm-8">{{ $item->colorOption?->localizedName() ?? $item->color ?? '—' }}</dd>
                        <dt class="col-sm-4">{{ __('gross.fields.size') }}</dt>
                        <dd class="col-sm-8">{{ $item->sizeOption?->localizedName() ?? $item->size ?? '—' }}</dd>
                        <dt class="col-sm-4">{{ __('gross.fields.model') }}</dt>
                        <dd class="col-sm-8">{{ $item->modelOption?->localizedName() ?? $item->model ?? '—' }}</dd>
                        <dt class="col-sm-4">{{ __('gross.fields.default_sale_price') }}</dt>
                        <dd class="col-sm-8">
                            @if ($item->default_sale_price !== null)
                                <x-money :amount="$item->default_sale_price" />
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-sm-4">{{ __('gross.fields.reorder_level') }}</dt>
                        <dd class="col-sm-8"><span class="num-ltr">{{ $item->reorder_level }}</span></dd>
                        <dt class="col-sm-4">{{ __('gross.fields.status') }}</dt>
                        <dd class="col-sm-8">{{ __('gross.statuses.'.$item->status) }}</dd>
                    </dl>
                    @if (filled($item->description))
                        <hr>
                        <h3 class="h6">{{ __('gross.fields.description') }}</h3>
                        <p class="mb-0 text-body-secondary">{{ $item->description }}</p>
                    @endif
                </div>
            </div>
            <div class="row g-2">
                <div class="col-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body py-3 text-center">
                            <div class="small text-body-secondary">{{ __('gross.fields.on_hand') }}</div>
                            <div class="fs-5 fw-semibold num-ltr">{{ $item->balance?->qty_on_hand_pieces ?? 0 }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body py-3 text-center">
                            <div class="small text-body-secondary">{{ __('gross.fields.with_courier') }}</div>
                            <div class="fs-5 fw-semibold num-ltr">{{ $item->balance?->qty_with_courier_pieces ?? 0 }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body py-3 text-center">
                            <div class="small text-body-secondary">{{ __('gross.fields.lost') }}</div>
                            <div class="fs-5 fw-semibold num-ltr">{{ $item->balance?->qty_lost_pieces ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
