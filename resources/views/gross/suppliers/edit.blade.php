@extends('layouts.app')

@section('title', __('gross.suppliers.edit'))

@section('content')
    <h1 class="h4 mb-3">{{ __('gross.suppliers.edit') }}</h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('gross.suppliers.update', $supplier) }}">
                @csrf
                @method('PUT')
                @include('gross.suppliers._form', ['supplier' => $supplier])
                <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                <a href="{{ route('gross.suppliers.show', $supplier) }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
            </form>
        </div>
    </div>
@endsection
