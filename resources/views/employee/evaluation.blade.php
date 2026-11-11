@extends('layouts.app')

@section('title', __('evaluation.me.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('employee._mode-cards')
    @else
        @include('employee._mode-legacy')
    @endif
@endsection
