@extends('layouts.app')

@section('title', __('valuation.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('valuation.sheets._mode-cards')
    @else
        @include('valuation.sheets._mode-legacy')
    @endif
@endsection
