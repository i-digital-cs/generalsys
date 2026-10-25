@extends('layouts.app')

@section('title', __('gh.customers.show'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $customer->localizedName() }}</h1>
            <p class="small text-body-secondary mb-0">
                <span class="badge text-bg-secondary">{{ __('gh.customer_type.'.$customer->type) }}</span>
                @include('gh.partials.status-badge', ['status' => $customer->status])
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('update', $customer)
                <a href="{{ route('gh.customers.edit', $customer) }}" class="btn btn-outline-primary btn-sm">{{ __('general.edit') }}</a>
            @endcan
            <a href="{{ route('gh.customers.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.customers.back') }}</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">{{ __('gh.customers.show') }}</h2>
                    <dl class="row small mb-0">
                        <dt class="col-5">{{ __('gh.fields.phone') }}</dt><dd class="col-7 hp-value">{{ $customer->phone ?: '—' }}</dd>
                        <dt class="col-5">{{ __('gh.fields.email') }}</dt><dd class="col-7 hp-value">{{ $customer->email ?: '—' }}</dd>
                        <dt class="col-5">{{ __('gh.fields.tax_registration_number') }}</dt><dd class="col-7 hp-value">{{ $customer->tax_registration_number ?: '—' }}</dd>
                        <dt class="col-5">{{ __('gh.fields.billing_name') }}</dt><dd class="col-7">{{ $customer->billing_name ?: '—' }}</dd>
                        <dt class="col-5">{{ __('gh.fields.notes') }}</dt><dd class="col-7">{{ $customer->notes ?: '—' }}</dd>
                    </dl>
                    @if ($customer->isB2b())
                        <a href="{{ route('gh.contracts.create', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-outline-primary mt-2">{{ __('gh.contracts.create') }}</a>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6">{{ __('gh.customers.addresses') }}</h2>
                    @can('update', $customer)
                        <form method="post" action="{{ route('gh.customers.addresses.store', $customer) }}" class="row g-2 align-items-end mb-3 pb-3 border-bottom">
                            @csrf
                            <div class="col-md-3"><label class="form-label mb-1">{{ __('gh.fields.label') }}</label>
                                <input name="label" class="form-control form-control-sm" required maxlength="80" value="{{ old('label') }}"></div>
                            <div class="col-md-3"><label class="form-label mb-1">{{ __('gh.fields.zone') }}</label>
                                <select name="zone_id" class="form-select form-select-sm" required>
                                    <option value="">—</option>
                                    @foreach ($zones as $z)
                                        <option value="{{ $z->id }}" @selected((string) old('zone_id') === (string) $z->id)>{{ $z->localizedName() }}</option>
                                    @endforeach
                                </select></div>
                            <div class="col-md-2"><label class="form-label mb-1">{{ __('gh.fields.building') }}</label>
                                <input name="building" class="form-control form-control-sm" value="{{ old('building') }}"></div>
                            <div class="col-md-2"><label class="form-label mb-1">{{ __('gh.fields.street') }}</label>
                                <input name="street" class="form-control form-control-sm" value="{{ old('street') }}"></div>
                            <div class="col-md-2"><label class="form-label mb-1">{{ __('gh.fields.area') }}</label>
                                <input name="area" class="form-control form-control-sm" value="{{ old('area') }}"></div>
                            <div class="col-md-4"><label class="form-label mb-1">{{ __('gh.fields.directions') }}</label>
                                <input name="directions" class="form-control form-control-sm" value="{{ old('directions') }}"></div>
                            <div class="col-md-2"><label class="form-label mb-1">{{ __('gh.fields.latitude') }}</label>
                                <input name="latitude" class="form-control form-control-sm" value="{{ old('latitude') }}"></div>
                            <div class="col-md-2"><label class="form-label mb-1">{{ __('gh.fields.longitude') }}</label>
                                <input name="longitude" class="form-control form-control-sm" value="{{ old('longitude') }}"></div>
                            <div class="col-md-2 form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" @checked(old('is_default'))>
                                <label class="form-check-label" for="is_default">{{ __('gh.fields.is_default') }}</label>
                            </div>
                            <div class="col-md-auto"><button class="btn btn-primary btn-sm">{{ __('general.save') }}</button></div>
                        </form>
                    @endcan

                    @forelse ($customer->addresses as $address)
                        <div class="border rounded p-2 mb-2 small">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <strong>{{ $address->label }}</strong>
                                    @if ($address->is_default)<span class="badge text-bg-primary">{{ __('gh.customers.default_badge') }}</span>@endif
                                    — {{ $address->zone?->localizedName() }}
                                    <div class="text-body-secondary">{{ collect([$address->building, $address->street, $address->area])->filter()->implode(' · ') }}</div>
                                </div>
                                @can('update', $customer)
                                    <div class="text-nowrap">
                                        @unless ($address->is_default)
                                            <form method="post" action="{{ route('gh.customers.addresses.default', [$customer, $address]) }}" class="d-inline">@csrf
                                                <button class="btn btn-link btn-sm p-0">{{ __('gh.customers.set_default') }}</button></form>
                                        @endunless
                                        <form method="post" action="{{ route('gh.customers.addresses.destroy', [$customer, $address]) }}" class="d-inline" onsubmit="return confirm(@json(__('general.delete')))">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <p class="text-body-secondary small mb-0">—</p>
                    @endforelse
                </div>
            </div>

            @if ($customer->isB2b())
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6">{{ __('gh.customers.contracts') }}</h2>
                        @forelse ($customer->contracts as $contract)
                            <div class="d-flex justify-content-between border-bottom py-2 small">
                                <a href="{{ route('gh.contracts.show', $contract) }}">{{ $contract->reference }}</a>
                                <span>{{ __('gh.contract_status.'.$contract->status) }} · {{ $contract->effective_from->toDateString() }}</span>
                            </div>
                        @empty
                            <p class="text-body-secondary small mb-0">—</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
