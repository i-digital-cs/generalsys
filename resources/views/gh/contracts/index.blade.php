@extends('layouts.app')

@section('title', __('gh.contracts.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.contracts._mode-cards')
    @else
        @include('gh.contracts._mode-legacy')
    @endif
@endsection
