@extends('layouts.app')

@section('title', $supplier->name)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ $supplier->name }}</h1>
        <div class="d-flex flex-wrap gap-2">
            @can('view', $supplier)
                <a href="{{ route('gross.suppliers.statement', $supplier) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-journal-text me-1" aria-hidden="true"></i>{{ __('gross.suppliers.statement') }}
                </a>
            @endcan
            @can('create', App\Models\Gross\GrossTreasuryVoucher::class)
                <a href="{{ route('gross.treasury.create', ['type' => 'PAYMENT', 'purpose' => 'SUPPLIER', 'supplier_id' => $supplier->id]) }}"
                   class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-wallet2 me-1" aria-hidden="true"></i>{{ __('gross.suppliers.pay') }}
                </a>
            @endcan
            @can('update', $supplier)
                <a href="{{ route('gross.suppliers.edit', $supplier) }}" class="btn btn-outline-primary btn-sm">{{ __('general.edit') }}</a>
            @endcan
            @can('delete', $supplier)
                <form method="post" action="{{ route('gross.suppliers.destroy', $supplier) }}"
                      onsubmit="return confirm(@json(__('gross.suppliers.confirm_delete')))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('general.delete') }}</button>
                </form>
            @endcan
            <a href="{{ route('gross.suppliers.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.back') }}</a>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('gross.fields.phone') }}</dt>
                <dd class="col-sm-9"><span class="num-ltr">{{ $supplier->phone }}</span></dd>
                <dt class="col-sm-3">{{ __('gross.fields.address') }}</dt>
                <dd class="col-sm-9">{{ $supplier->address ?: '—' }}</dd>
                <dt class="col-sm-3">{{ __('gross.fields.notes') }}</dt>
                <dd class="col-sm-9">{{ $supplier->notes ?: '—' }}</dd>
            </dl>
        </div>
    </div>
@endsection
