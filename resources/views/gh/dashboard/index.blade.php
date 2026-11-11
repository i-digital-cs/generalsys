@extends('layouts.app')

@section('title', __('gh.dashboard.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.dashboard._mode-cards')
    @else
        @include('gh.dashboard._mode-legacy')
    @endif
@endsection
