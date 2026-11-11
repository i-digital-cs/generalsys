@extends('layouts.app')

@section('title', __('gh.invoices.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.invoices._mode-cards')
    @else
        @include('gh.invoices._mode-legacy')
    @endif
@endsection
