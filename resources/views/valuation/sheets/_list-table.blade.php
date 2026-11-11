@php
    $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
@endphp
@if ($listViewIsCards)
    @include('valuation.sheets._list-table-cards')
@else
    @include('valuation.sheets._list-table-legacy')
@endif
