@extends('layouts.app')

@section('title', $customer->name)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ $customer->name }}</h1>
        <div class="d-flex flex-wrap gap-2">
            @can('update', $customer)
                <a href="{{ route('gross.customers.edit', $customer) }}" class="btn btn-outline-primary btn-sm">{{ __('general.edit') }}</a>
            @endcan
            @can('delete', $customer)
                <form method="post" action="{{ route('gross.customers.destroy', $customer) }}"
                      onsubmit="return confirm(@json(__('gross.customers.confirm_delete')))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('general.delete') }}</button>
                </form>
            @endcan
            <a href="{{ route('gross.customers.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.back') }}</a>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('gross.fields.phone') }}</dt>
                <dd class="col-sm-9"><span class="num-ltr">{{ $customer->phone }}</span></dd>
                <dt class="col-sm-3">{{ __('gross.fields.address') }}</dt>
                <dd class="col-sm-9">{{ $customer->address }}</dd>
            </dl>
        </div>
    </div>
@endsection
