@props([
    'calendar',
    'prevUrl',
    'nextUrl',
    'title' => null,
])

@php
    $title = $title ?? __('dashboard.calendar.title');
@endphp

<div class="card border-0 shadow-sm h-100">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h6 mb-0">{{ $title }}</h2>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ $prevUrl }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('dashboard.calendar.prev') }}">
                    <i class="bi bi-chevron-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}" aria-hidden="true"></i>
                </a>
                <span class="fw-semibold small">{{ $calendar['label'] }}</span>
                <a href="{{ $nextUrl }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('dashboard.calendar.next') }}">
                    <i class="bi bi-chevron-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }}" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <div class="month-cal">
            <div class="month-cal-weekdays">
                @foreach ($calendar['weekdays'] as $weekday)
                    <div class="month-cal-weekday">{{ $weekday }}</div>
                @endforeach
            </div>
            <div class="month-cal-grid">
                @foreach ($calendar['cells'] as $cell)
                    <div @class([
                        'month-cal-cell',
                        'is-outside' => ! $cell['inMonth'],
                        'is-today' => $cell['isToday'],
                        'has-markers' => $cell['inMonth'] && $cell['markers'] !== [],
                    ])>
                        @if ($cell['inMonth'])
                            <div class="month-cal-day">{{ $cell['day'] }}</div>
                            @if ($cell['markers'] !== [])
                                <ul class="month-cal-markers">
                                    @foreach (array_slice($cell['markers'], 0, 3) as $marker)
                                        <li class="month-cal-marker month-cal-marker--{{ $marker['kind'] }}"
                                            title="{{ $marker['label'] }}">
                                            {{ $marker['label'] }}
                                        </li>
                                    @endforeach
                                    @if (count($cell['markers']) > 3)
                                        <li class="month-cal-more">+{{ count($cell['markers']) - 3 }}</li>
                                    @endif
                                </ul>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="d-flex flex-wrap gap-3 mt-3 small text-body-secondary">
            <span><span class="month-cal-legend month-cal-legend--shift"></span> {{ __('dashboard.calendar.legend_shift') }}</span>
            <span><span class="month-cal-legend month-cal-legend--holiday"></span> {{ __('dashboard.calendar.legend_holiday') }}</span>
        </div>
    </div>
</div>
