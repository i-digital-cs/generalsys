@extends('layouts.app')

@section('title', __('gh.customers.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.customers._mode-cards')
    @else
        @include('gh.customers._mode-legacy')
    @endif
@endsection
