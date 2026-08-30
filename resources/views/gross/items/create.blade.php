@extends('layouts.app')

@section('title', __('gross.items.create'))

@section('content')
    <h1 class="h4 mb-3">{{ __('gross.items.create') }}</h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('gross.items.store') }}" enctype="multipart/form-data">
                @csrf
                @include('gross.items._form')
                <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                <a href="{{ route('gross.items.index') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
            </form>
        </div>
    </div>
@endsection
