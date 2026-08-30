@extends('layouts.app')

@section('title', __('gross.purchases.edit'))

@section('content')
    <h1 class="h4 mb-3">{{ __('gross.purchases.edit') }} · <span class="num-ltr">{{ $invoice->invoice_number }}</span></h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('gross.purchases.update', $invoice) }}">
                @csrf
                @method('PUT')
                @include('gross.purchases._form')
                <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                <a href="{{ route('gross.purchases.show', $invoice) }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
            </form>
        </div>
    </div>
@endsection
