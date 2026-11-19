@props([
    'compact' => false,
])
@php
    use App\Support\GeneralHouse\GhDateTimePresenter;
@endphp
<p {{ $attributes->class([$compact ? 'small text-body-secondary mb-0 mt-1' : 'small text-body-secondary mb-2', 'gh-uae-tz-notice']) }}>
    <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ GhDateTimePresenter::selectionNotice() }}
    <span class="gh-uae-badge">{{ GhDateTimePresenter::uaeBadge() }}</span>
</p>
