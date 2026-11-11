@extends('layouts.app')

@section('title', __('attendance.history.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('team-leader.attendance._mode-cards')
    @else
        @include('team-leader.attendance._mode-legacy')
    @endif
@endsection
