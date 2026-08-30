@extends('layouts.app')

@section('title', $supplier->name)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ $supplier->name }}</h1>
        <div class="d-flex flex-wrap gap-2">
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
