@extends('layouts.app')

@section('title', __('report.tl.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('team-leader.reports._mode-cards')
    @else
        @include('team-leader.reports._mode-legacy')
    @endif
@endsection
