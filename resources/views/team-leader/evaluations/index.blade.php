@extends('layouts.app')

@section('title', __('evaluation.tl.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('team-leader.evaluations._mode-cards')
    @else
        @include('team-leader.evaluations._mode-legacy')
    @endif
@endsection
