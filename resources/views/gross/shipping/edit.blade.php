@extends('layouts.app')

@section('title', __('gross.shipping.edit'))

@section('content')
    <h1 class="h4 mb-3">{{ __('gross.shipping.edit') }}</h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('gross.shipping.update', $company) }}">
                @csrf
                @method('PUT')
                @include('gross.shipping._form', ['company' => $company])
                <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                <a href="{{ route('gross.shipping.show', $company) }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
            </form>
        </div>
    </div>
@endsection
