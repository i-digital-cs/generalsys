@extends('layouts.app')

@section('title', __('gh.financial_corrections.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.financial-corrections._mode-cards')
    @else
        @include('gh.financial-corrections._mode-legacy')
    @endif
@endsection
