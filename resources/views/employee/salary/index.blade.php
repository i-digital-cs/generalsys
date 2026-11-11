@extends('layouts.app')

@section('title', __('salary.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('employee.salary._mode-cards')
    @else
        @include('employee.salary._mode-legacy')
    @endif
@endsection
