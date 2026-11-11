@extends('layouts.app')

@section('title', __('gh.credit_notes.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.credit-notes._mode-cards')
    @else
        @include('gh.credit-notes._mode-legacy')
    @endif
@endsection
