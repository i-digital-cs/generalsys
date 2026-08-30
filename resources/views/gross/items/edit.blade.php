@extends('layouts.app')

@section('title', __('gross.items.edit'))

@section('content')
    <h1 class="h4 mb-3">{{ __('gross.items.edit') }}</h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('gross.items.update', $item) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('gross.items._form', ['item' => $item])
                <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                <a href="{{ route('gross.items.show', $item) }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
            </form>
        </div>
    </div>
@endsection
