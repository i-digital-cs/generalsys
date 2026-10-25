@extends('layouts.app')

@section('title', __('gh.contracts.show'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $contract->reference }}</h1>
            <p class="small text-body-secondary mb-0">
                {{ $contract->customer?->localizedName() }} · {{ __('gh.contract_status.'.$contract->status) }}
                · {{ $contract->effective_from->toDateString() }}
                → {{ $contract->effective_to?->toDateString() ?? __('gh.contracts.open_ended') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            @can('update', $contract)
                <a href="{{ route('gh.contracts.edit', $contract) }}" class="btn btn-outline-primary btn-sm">{{ __('general.edit') }}</a>
            @endcan
            <a href="{{ route('gh.contracts.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.contracts.back') }}</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6">{{ __('gh.contracts.offerings') }}</h2>
            @can('update', $contract)
                <form method="post" action="{{ route('gh.contracts.offerings.store', $contract) }}" class="row g-2 align-items-end mb-3 pb-3 border-bottom">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label mb-1">{{ __('gh.fields.offering') }}</label>
                        <select name="offering_id" class="form-select form-select-sm" required>
                            <option value="">—</option>
                            @foreach ($offerings as $o)
                                <option value="{{ $o->id }}">
                                    {{ $o->product?->code }} · {{ $o->product?->localizedName() }} / {{ $o->treatment?->localizedName() }}
                                    ({{ number_format((float) $o->standard_price, 2) }} AED)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">{{ __('gh.fields.contract_price') }}</label>
                        <input name="contract_price" type="number" step="0.01" min="0" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">{{ __('gh.fields.status') }}</label>
                        <select name="status" class="form-select form-select-sm">
                            @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                                <option value="{{ $st }}" @selected($st === 'ACTIVE')>{{ __('gh.status.'.$st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-auto"><button class="btn btn-primary btn-sm">{{ __('gh.contracts.add_offering') }}</button></div>
                </form>
            @endcan

            @if ($contract->contractOfferings->isEmpty())
                <p class="text-body-secondary small mb-0">—</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hr align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('gh.fields.offering') }}</th>
                            <th>{{ __('gh.fields.standard_price') }}</th>
                            <th>{{ __('gh.fields.contract_price') }}</th>
                            <th>{{ __('gh.fields.status') }}</th>
                            <th></th>
                        </tr></thead>
                        <tbody>
                        @foreach ($contract->contractOfferings as $row)
                            <tr>
                                <td>{{ $row->offering?->product?->localizedName() }} / {{ $row->offering?->treatment?->localizedName() }}</td>
                                <td class="hp-value num-ltr">{{ number_format((float) ($row->offering->standard_price ?? 0), 2) }}</td>
                                <td>
                                    @can('update', $contract)
                                        <form method="post" action="{{ route('gh.contracts.offerings.update', [$contract, $row]) }}" class="d-flex gap-1 align-items-center">
                                            @csrf @method('PUT')
                                            <input type="number" step="0.01" min="0" name="contract_price" class="form-control form-control-sm" style="max-width:7rem" value="{{ $row->contract_price }}" required>
                                            <select name="status" class="form-select form-select-sm" style="max-width:8rem">
                                                @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                                                    <option value="{{ $st }}" @selected($row->status === $st)>{{ __('gh.status.'.$st) }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-outline-primary btn-sm">{{ __('general.save') }}</button>
                                        </form>
                                    @else
                                        <span class="hp-value num-ltr">{{ number_format((float) $row->contract_price, 2) }}</span>
                                    @endcan
                                </td>
                                <td>@include('gh.partials.status-badge', ['status' => $row->status])</td>
                                <td class="text-end">
                                    @can('update', $contract)
                                        <form method="post" action="{{ route('gh.contracts.offerings.destroy', [$contract, $row]) }}" onsubmit="return confirm(@json(__('general.delete')))">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
