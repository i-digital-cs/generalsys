@props([
    'title' => '',
    'titleHref' => null,
    'titleLtr' => false,
    'imageUrl' => null,
    'imageAlt' => '',
    'imageVariant' => 'square', // square | circle
    'imagePlaceholder' => null,
])
@php
    $titleClass = 'gh-record-card__title'.($titleLtr ? ' num-ltr' : '');
    $thumbClass = 'gh-record-card__thumb gh-record-card__thumb--'.($imageVariant === 'circle' ? 'circle' : 'square');
    $hasImageSlot = filled($imageUrl) || $imagePlaceholder !== null;
@endphp
<article {{ $attributes->class('gh-record-card') }}>
    @if ($hasImageSlot)
        <div class="gh-record-card__media">
            <div class="{{ $thumbClass }}" aria-hidden="{{ filled($imageUrl) ? 'false' : 'true' }}">
                @if (filled($imageUrl))
                    <img
                        src="{{ $imageUrl }}"
                        alt="{{ $imageAlt !== '' ? $imageAlt : $title }}"
                        loading="lazy"
                        decoding="async"
                        onerror="this.classList.add('d-none'); const p=this.nextElementSibling; if(p){ p.classList.remove('d-none'); }"
                    >
                    <span class="gh-record-card__thumb-placeholder d-none" aria-hidden="true">
                        @if (is_string($imagePlaceholder) && str_starts_with($imagePlaceholder, 'bi-'))
                            <i class="bi {{ $imagePlaceholder }}"></i>
                        @else
                            {{ $imagePlaceholder ?? '—' }}
                        @endif
                    </span>
                @else
                    <span class="gh-record-card__thumb-placeholder">
                        @if (is_string($imagePlaceholder) && str_starts_with($imagePlaceholder, 'bi-'))
                            <i class="bi {{ $imagePlaceholder }}"></i>
                        @else
                            {{ $imagePlaceholder ?? '—' }}
                        @endif
                    </span>
                @endif
            </div>
            <div class="gh-record-card__media-body">
                <header class="gh-record-card__head">
                    @if ($titleHref)
                        <a href="{{ $titleHref }}" class="{{ $titleClass }}" title="{{ $title }}">{{ $title }}</a>
                    @else
                        <h3 class="{{ $titleClass }}" title="{{ $title }}">{{ $title }}</h3>
                    @endif
                    @isset($badges)
                        <div class="gh-record-card__badges">{{ $badges }}</div>
                    @endisset
                </header>

                @isset($meta)
                    <div class="gh-record-card__meta" role="group">{{ $meta }}</div>
                @endisset

                @isset($actions)
                    <footer class="gh-record-card__actions">{{ $actions }}</footer>
                @endisset
            </div>
        </div>
    @else
        <header class="gh-record-card__head">
            @if ($titleHref)
                <a href="{{ $titleHref }}" class="{{ $titleClass }}" title="{{ $title }}">{{ $title }}</a>
            @else
                <h3 class="{{ $titleClass }}" title="{{ $title }}">{{ $title }}</h3>
            @endif
            @isset($badges)
                <div class="gh-record-card__badges">{{ $badges }}</div>
            @endisset
        </header>

        @isset($meta)
            <div class="gh-record-card__meta" role="group">{{ $meta }}</div>
        @endisset

        @isset($actions)
            <footer class="gh-record-card__actions">{{ $actions }}</footer>
        @endisset
    @endif
</article>
