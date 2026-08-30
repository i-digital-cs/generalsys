@extends('layouts.app')

@section('title', __('gross.customers.edit'))

@section('content')
    <h1 class="h4 mb-3">{{ __('gross.customers.edit') }}</h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('gross.customers.update', $customer) }}">
                @csrf
                @method('PUT')
                @include('gross.customers._form', ['customer' => $customer])
                <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                <a href="{{ route('gross.customers.show', $customer) }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
            </form>
        </div>
    </div>
@endsection
