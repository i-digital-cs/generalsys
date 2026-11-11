@extends('layouts.app')

@section('title', __('shifts.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('team-leader.shifts._mode-cards')
    @else
        @include('team-leader.shifts._mode-legacy')
    @endif
@endsection
