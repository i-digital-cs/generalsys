@extends('layouts.app')

@section('title', __('advances.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('admin.advances._mode-cards')
    @else
        @include('admin.advances._mode-legacy')
    @endif
@endsection
