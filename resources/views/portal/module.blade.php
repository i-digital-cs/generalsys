@extends('layouts.app')

@section('title', __('portal.'.$module))

@section('content')
    <div class="container-lg py-2 py-md-3">
        <h1 class="h3 mb-3">{{ __('portal.'.$module) }}</h1>
        <x-empty-state
            :title="__('portal.empty_title')"
            :text="__('portal.empty_text')"
            icon="bi-hourglass" />
    </div>
@endsection
