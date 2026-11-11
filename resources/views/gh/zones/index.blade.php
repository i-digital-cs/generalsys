@extends('layouts.app')

@section('title', __('gh.zones.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.zones._mode-cards')
    @else
        @include('gh.zones._mode-legacy')
    @endif
@endsection
