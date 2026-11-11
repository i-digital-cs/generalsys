@extends('layouts.app')

@section('title', __('gh.products.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.products._mode-cards')
    @else
        @include('gh.products._mode-legacy')
    @endif
@endsection
