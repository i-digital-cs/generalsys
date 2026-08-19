@extends('layouts.app')

@section('title', __('portal.'.$module))

@section('content')
    <h1 class="h4 mb-2">{{ __('portal.'.$module) }}</h1>
    <p class="text-body-secondary small mb-3">{{ __('portal.module_home_text') }}</p>

    @include('valuation.sheets._month-stats', ['stats' => $stats])

    <a class="btn btn-primary" href="{{ route('valuation.sheets.index', $module) }}">
        <i class="bi bi-clipboard-data me-1" aria-hidden="true"></i>{{ __('nav.valuation') }}
    </a>
@endsection
