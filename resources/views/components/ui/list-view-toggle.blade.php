@props([
    'compact' => true,
])

@php
    use App\Support\Ui\ListViewMode;
    $user = auth()->user();
    $mode = ListViewMode::current($user);
    $legacyActive = $mode === ListViewMode::LEGACY;
    $cardsActive = $mode === ListViewMode::CARDS;
@endphp

@if ($user)
    <div {{ $attributes->class([
             'list-view-toggle',
             'hp-display-mode',
             'd-inline-flex',
             'flex-nowrap',
             'align-items-center',
             'gap-1',
         ]) }}
         role="group"
         aria-label="{{ __('ui.list_view.aria') }}"
         data-display-mode-toggle
         data-list-view-mode="{{ $mode }}">
        <form method="POST" action="{{ route('ui.list-view') }}" class="d-inline">
            @csrf
            <input type="hidden" name="mode" value="{{ ListViewMode::LEGACY }}">
            <button type="submit"
                    class="btn btn-sm {{ $legacyActive ? 'btn-light text-primary fw-semibold' : 'btn-outline-light' }} px-2"
                    @if ($legacyActive) aria-pressed="true" @else aria-pressed="false" @endif
                    title="{{ __('ui.list_view.legacy') }}">
                <i class="bi bi-table d-lg-none" aria-hidden="true"></i>
                <span class="d-none d-lg-inline">{{ __('ui.list_view.legacy') }}</span>
            </button>
        </form>
        <form method="POST" action="{{ route('ui.list-view') }}" class="d-inline">
            @csrf
            <input type="hidden" name="mode" value="{{ ListViewMode::CARDS }}">
            <button type="submit"
                    class="btn btn-sm {{ $cardsActive ? 'btn-light text-primary fw-semibold' : 'btn-outline-light' }} px-2"
                    @if ($cardsActive) aria-pressed="true" @else aria-pressed="false" @endif
                    title="{{ __('ui.list_view.cards') }}">
                <i class="bi bi-grid-3x3-gap d-lg-none" aria-hidden="true"></i>
                <span class="d-none d-lg-inline">{{ __('ui.list_view.cards') }}</span>
            </button>
        </form>
    </div>
@endif
