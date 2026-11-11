@extends('layouts.app')

@section('title', __('employees.title'))

@section('content')
    @php
        $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
    @endphp

    @if ($listViewIsCards)
        @include('admin.employees._mode-cards')
    @else
        @include('admin.employees._mode-legacy')
    @endif
@endsection
