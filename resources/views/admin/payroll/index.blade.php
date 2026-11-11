@extends('layouts.app')

@section('title', __('payroll.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('admin.payroll._mode-cards')
    @else
        @include('admin.payroll._mode-legacy')
    @endif
@endsection
