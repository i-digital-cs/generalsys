@extends('layouts.app')

@section('title', __('gh.treatments.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.treatments._mode-cards')
    @else
        @include('gh.treatments._mode-legacy')
    @endif
@endsection
