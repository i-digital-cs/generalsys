@extends('layouts.app')

@php
    $isEdit = $order !== null;
    $title = $isEdit ? __('gh.orders.edit') : __('gh.orders.create');
    $tz = config('general_house.timezone', 'Asia/Dubai');
    $selectedLines = old('lines', $isEdit ? $order->lines->map(fn ($l) => [
        'offering_id' => $l->offering_id,
        'quantity' => $l->quantity,
    ])->all() : []);
    $qtyByOffering = collect($selectedLines)->keyBy('offering_id');
@endphp

@section('title', $title)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $title }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.orders.subtitle') }}</p>
        </div>
        <a href="{{ $isEdit ? route('gh.orders.show', $order) : route('gh.orders.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.back') }}</a>
    </div>

    <div class="alert alert-info py-2 small" role="status">{{ __('gh.orders.estimate_banner') }}</div>

    @if ($isEdit && ! $fullEdit)
        <div class="alert alert-warning py-2 small">{{ __('gh.orders.restricted_edit') }}</div>
    @endif
    @if ($isEdit && $order->invoice_issued_at)
        <div class="alert alert-danger py-2 small">{{ __('gh.orders.locked_invoice') }}</div>
    @endif

    @if (! $isEdit)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="get" action="{{ route('gh.orders.create') }}" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label" for="customer_id_load">{{ __('gh.orders.existing_customer') }}</label>
                        <select id="customer_id_load" name="customer_id" class="form-select">
                            <option value="">{{ __('gh.orders.select_customer') }}</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" @selected(($customer?->id) === $c->id)>
                                    {{ $c->localizedName() }} ({{ __('gh.customer_type.'.$c->type) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-primary">{{ __('gh.orders.load_customer') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ $isEdit ? route('gh.orders.update', $order) : route('gh.orders.store') }}" class="row g-3">
                @csrf
                @if ($isEdit) @method('PUT') @endif

                @if (! $isEdit)
                    <div class="col-12">
                        <h2 class="h6 card-title">{{ __('gh.fields.customer') }}</h2>
                    </div>
                    @if ($customer)
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                        <div class="col-12">
                            <div class="border rounded p-3 bg-body-tertiary">
                                <span class="hp-value">{{ $customer->localizedName() }}</span>
                                <span class="badge text-bg-secondary">{{ __('gh.customer_type.'.$customer->type) }}</span>
                            </div>
                        </div>
                    @else
                        <div class="col-md-3">
                            <label class="form-label" for="new_customer_type">{{ __('gh.fields.type') }}</label>
                            <select id="new_customer_type" name="new_customer[type]" class="form-select">
                                <option value="B2C">{{ __('gh.customer_type.B2C') }}</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="new_customer_name_ar">{{ __('gh.fields.name_ar') }}</label>
                            <input id="new_customer_name_ar" name="new_customer[name_ar]" class="form-control" value="{{ old('new_customer.name_ar') }}" @disabled($customer !== null)>
                            @error('new_customer.name_ar')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_customer_phone">{{ __('gh.fields.phone') }}</label>
                            <input id="new_customer_phone" name="new_customer[phone]" class="form-control" value="{{ old('new_customer.phone') }}">
                        </div>
                    @endif
                @endif

                @if ($fullEdit)
                    <div class="col-12"><h2 class="h6 card-title">{{ __('gh.orders.select_address') }}</h2></div>
                    @if ($customer && $customer->addresses->isNotEmpty())
                        <div class="col-md-6">
                            <label class="form-label" for="address_id">{{ __('gh.orders.select_address') }}</label>
                            <select id="address_id" name="address_id" class="form-select">
                                <option value="">{{ __('gh.orders.new_address') }}</option>
                                @foreach ($customer->addresses as $addr)
                                    <option value="{{ $addr->id }}"
                                        @selected((string) old('address_id', $order?->address_id) === (string) $addr->id)>
                                        {{ $addr->label }} — {{ $addr->zone?->localizedName() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('address_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @endif
                    <div class="col-12">
                        <div class="border rounded p-3">
                            <p class="form-label mb-2">{{ __('gh.orders.new_address') }}</p>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label" for="new_address_zone">{{ __('gh.fields.zone') }}</label>
                                    <select id="new_address_zone" name="new_address[zone_id]" class="form-select">
                                        <option value="">—</option>
                                        @foreach ($zones as $zone)
                                            <option value="{{ $zone->id }}" @selected((string) old('new_address.zone_id') === (string) $zone->id)>{{ $zone->localizedName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="new_address_label">{{ __('gh.fields.label') }}</label>
                                    <input id="new_address_label" name="new_address[label]" class="form-control" value="{{ old('new_address.label') }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="new_address_building">{{ __('gh.fields.building') }}</label>
                                    <input id="new_address_building" name="new_address[building]" class="form-control" value="{{ old('new_address.building') }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="new_address_street">{{ __('gh.fields.street') }}</label>
                                    <input id="new_address_street" name="new_address[street]" class="form-control" value="{{ old('new_address.street') }}">
                                </div>
                            </div>
                            @error('new_address.label')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('new_address.zone_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="col-12">
                        <h2 class="h6 card-title">{{ __('gh.orders.offerings') }}</h2>
                        <p class="small text-body-secondary mb-2">{{ __('gh.orders.offerings_optional_hint') }}</p>
                    </div>
                    @if ($offerings->isEmpty())
                        <div class="col-12"><p class="text-body-secondary small">{{ __('gh.orders.no_offerings') }}</p></div>
                    @else
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-hr table-sm align-middle">
                                    <thead>
                                    <tr>
                                        <th>{{ __('gh.fields.category') }}</th>
                                        <th>{{ __('gh.fields.product') }}</th>
                                        <th>{{ __('gh.fields.treatment') }}</th>
                                        <th>{{ __('gh.orders.unit_estimate') }}</th>
                                        <th style="min-width:6rem">{{ __('gh.orders.quantity') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($offerings as $i => $row)
                                        @php
                                            $o = $row['offering'];
                                            $existing = $qtyByOffering->get($o->id);
                                            $qty = old('lines.'.$i.'.quantity', $existing['quantity'] ?? '');
                                        @endphp
                                        <tr>
                                            <td>{{ $o->product?->category?->localizedName() }}</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    @if ($o->product?->imageUrl())
                                                        <img class="gh-product-thumb" src="{{ $o->product->imageUrl() }}" alt="" width="40" height="40" loading="lazy" decoding="async">
                                                    @else
                                                        <span class="gh-product-thumb gh-product-thumb--fallback" aria-hidden="true"><i class="bi bi-image"></i></span>
                                                    @endif
                                                    <span>{{ $o->product?->localizedName() }}</span>
                                                </div>
                                            </td>
                                            <td>{{ $o->treatment?->localizedName() }}</td>
                                            <td class="hp-value"><x-money :amount="$row['unit_price']" currency="AED" />
                                                <span class="small text-body-secondary">({{ __('gh.price_source.'.$row['source']) }})</span>
                                            </td>
                                            <td>
                                                <input type="hidden" name="lines[{{ $i }}][offering_id]" value="{{ $o->id }}">
                                                <input type="number" min="0" max="9999" name="lines[{{ $i }}][quantity]" class="form-control form-control-sm" value="{{ $qty }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @error('lines')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div class="col-12">
                        <h2 class="h6 card-title">{{ __('gh.orders.scheduled_pickup') }}</h2>
                        <p class="small text-body-secondary mb-2">{{ __('gh.orders.driver_visibility_auto_hint') }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="scheduled_pickup_at">{{ __('gh.orders.scheduled_pickup') }}</label>
                        <input id="scheduled_pickup_at" type="datetime-local" name="scheduled_pickup_at" class="form-control" required
                               value="{{ old('scheduled_pickup_at', $order?->pickup_starts_at?->timezone($tz)->format('Y-m-d\\TH:i')) }}">
                        @error('scheduled_pickup_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('pickup_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('pickup_window_start')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <p class="small text-body-secondary mt-1 mb-0">{{ __('gh.orders.timezone_hint', ['tz' => $tz]) }}</p>
                    </div>
                @endif

                <div class="col-md-6">
                    <label class="form-label" for="pickup_driver_id">{{ __('gh.orders.assign_pickup') }}</label>
                    @if ($fullEdit)
                        <select id="pickup_driver_id" name="pickup_driver_id" class="form-select">
                            <option value="">—</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected((string) old('pickup_driver_id', $order?->pickup_driver_id) === (string) $driver->id)>
                                    {{ $driver->localizedName() }}
                                </option>
                            @endforeach
                        </select>
                        @error('pickup_driver_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @else
                        <div class="form-control-plaintext hp-value">{{ $order?->pickupDriver?->localizedName() ?? '—' }}</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="delivery_driver_id">{{ __('gh.orders.assign_delivery') }}</label>
                    <select id="delivery_driver_id" name="delivery_driver_id" class="form-select">
                        <option value="">{{ __('gh.orders.none') }}</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected((string) old('delivery_driver_id', $order?->delivery_driver_id) === (string) $driver->id)>
                                {{ $driver->localizedName() }}
                            </option>
                        @endforeach
                    </select>
                    @error('delivery_driver_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="scheduling_notes">{{ __('gh.orders.scheduling_notes') }}</label>
                    <textarea id="scheduling_notes" name="scheduling_notes" rows="2" class="form-control">{{ old('scheduling_notes', $order?->scheduling_notes) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="notes">{{ __('gh.orders.operational_notes') }}</label>
                    <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes', $order?->notes) }}</textarea>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" @disabled($isEdit && $order->invoice_issued_at)>{{ __('general.save') }}</button>
                    <a href="{{ $isEdit ? route('gh.orders.show', $order) : route('gh.orders.index') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
