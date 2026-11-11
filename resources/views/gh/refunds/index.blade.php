@extends('layouts.app')

@section('title', __('gh.refunds.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.refunds._mode-cards')
    @else
        @include('gh.refunds._mode-legacy')
    @endif
@endsection
