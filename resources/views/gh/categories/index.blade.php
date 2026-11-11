@extends('layouts.app')

@section('title', __('gh.categories.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.categories._mode-cards')
    @else
        @include('gh.categories._mode-legacy')
    @endif
@endsection
