@extends('layouts.app')

@php $isEdit = $contract !== null; $title = $isEdit ? __('gh.contracts.edit') : __('gh.contracts.create'); @endphp
@section('title', $title)

@section('content')
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ $title }}</h1>
        <a href="{{ route('gh.contracts.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.contracts.back') }}</a>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ $isEdit ? route('gh.contracts.update', $contract) : route('gh.contracts.store') }}" class="row g-3">
                @csrf
                @if ($isEdit) @method('PUT') @endif
                <div class="col-md-4">
                    <label class="form-label" for="customer_id">{{ __('gh.fields.customer') }}</label>
                    <select id="customer_id" name="customer_id" class="form-select" required>
                        <option value="">—</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected((string) old('customer_id', $selectedCustomerId ?? $contract->customer_id ?? '') === (string) $c->id)>
                                {{ $c->localizedName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="reference">{{ __('gh.fields.reference') }}</label>
                    @if ($isEdit)
                        <input id="reference" class="form-control hp-value" value="{{ $contract->reference }}" readonly disabled>
                        <div class="form-text">{{ __('gh.contracts.reference_immutable') }}</div>
                    @else
                        <input id="reference" class="form-control" value="{{ __('gh.contracts.reference_auto') }}" readonly disabled>
                        <div class="form-text">{{ __('gh.contracts.reference_auto_hint') }}</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="status">{{ __('gh.fields.status') }}</label>
                    <select id="status" name="status" class="form-select" required>
                        @foreach (\App\Support\GeneralHouse\GhContractStatus::ALL as $st)
                            <option value="{{ $st }}" @selected(old('status', $contract->status ?? 'DRAFT') === $st)>{{ __('gh.contract_status.'.$st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="effective_from">{{ __('gh.fields.effective_from') }}</label>
                    <input id="effective_from" name="effective_from" type="date" class="form-control" required
                           value="{{ old('effective_from', optional($contract->effective_from ?? null)->toDateString()) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="effective_to">{{ __('gh.fields.effective_to') }}</label>
                    <input id="effective_to" name="effective_to" type="date" class="form-control"
                           value="{{ old('effective_to', optional($contract->effective_to ?? null)->toDateString()) }}">
                </div>
                <div class="col-12">
                    <label class="form-label" for="notes">{{ __('gh.fields.notes') }}</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $contract->notes ?? '') }}</textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">{{ __('general.save') }}</button>
                    <a href="{{ route('gh.contracts.index') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
