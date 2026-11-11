@extends('layouts.app')

@section('title', __('gh.payments.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('gh.payments._mode-cards')
    @else
        @include('gh.payments._mode-legacy')
    @endif
@endsection
