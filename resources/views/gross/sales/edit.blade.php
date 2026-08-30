@extends('layouts.app')

@section('title', __('gross.sales.edit'))

@section('content')
    <h1 class="h4 mb-3">{{ __('gross.sales.edit') }}</h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('gross.sales.update', $invoice) }}">
                @csrf
                @method('PUT')
                @include('gross.sales._form', ['invoice' => $invoice])
                <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                <a href="{{ route('gross.sales.show', $invoice) }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
            </form>
        </div>
    </div>
@endsection
