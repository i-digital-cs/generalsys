@extends('layouts.app')

@section('title', __('permissions.tl.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('team-leader.approvals._mode-cards')
    @else
        @include('team-leader.approvals._mode-legacy')
    @endif
@endsection
