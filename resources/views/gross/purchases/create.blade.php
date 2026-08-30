@extends('layouts.app')

@section('title', __('gross.purchases.create'))

@section('content')
    <h1 class="h4 mb-3">{{ __('gross.purchases.create') }}</h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('gross.purchases.store') }}">
                @csrf
                @include('gross.purchases._form')
                <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                <a href="{{ route('gross.purchases.index') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
            </form>
        </div>
    </div>
@endsection
