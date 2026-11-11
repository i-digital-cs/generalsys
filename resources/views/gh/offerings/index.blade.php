@extends('layouts.app')

@section('title', __('gh.offerings.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.offerings._mode-cards')
    @else
        @include('gh.offerings._mode-legacy')
    @endif
@endsection
