@props([
    'at' => null,
    'mode' => 'datetime',
    'badge' => true,
    'empty' => '—',
])
@php
    use App\Support\GeneralHouse\GhDateTimePresenter;
    $text = GhDateTimePresenter::format($at, $mode);
    $showBadge = $badge && $mode !== 'date' && $text !== null;
@endphp
@if ($text === null)
    <span {{ $attributes }}>{{ $empty }}</span>
@else
    <span {{ $attributes->class(['gh-dubai-time', 'text-nowrap']) }}>
        <span class="num-ltr">{{ $text }}</span>
        @if ($showBadge)
            <span class="gh-uae-badge" title="{{ __('gh.time.uae_badge_title', ['tz' => GhDateTimePresenter::timezone()]) }}">{{ GhDateTimePresenter::uaeBadge() }}</span>
        @endif
    </span>
@endif
