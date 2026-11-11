@extends('layouts.app')

@section('title', __('advances.my_title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('employee.advances._mode-cards')
    @else
        @include('employee.advances._mode-legacy')
    @endif
@endsection
