@props([
    'totals',
    'ym' => null,
    'name' => null,
    'formAction' => null,
    'userId' => null,
])

@php
    $monthLabel = $ym
        ? \Carbon\Carbon::createFromFormat('Y-m', $ym)->startOfMonth()->translatedFormat('F Y')
        : null;
    $title = $name
        ? __('dashboard.hours.stats_for', ['name' => $name, 'month' => $monthLabel ?? ''])
        : __('dashboard.hours.stats_title');
@endphp

<div {{ $attributes->merge(['class' => 'card border-0 shadow-sm']) }}>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h6 mb-0">{{ $title }}</h2>
            @if ($formAction)
                <form method="GET" action="{{ $formAction }}" class="d-flex flex-wrap align-items-end gap-2">
                    @if ($userId !== null)
                        <input type="hidden" name="user_id" value="{{ $userId }}">
                    @endif
                    <div>
                        <label for="hours_stats_cal" class="form-label mb-1">{{ __('dashboard.hours.select_month') }}</label>
                        <input
                            id="hours_stats_cal"
                            type="month"
                            name="cal"
                            class="form-control form-control-sm num-ltr"
                            value="{{ $ym }}"
                            required
                        >
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('dashboard.hours.apply') }}</button>
                </form>
            @elseif ($monthLabel)
                <span class="small text-body-secondary num-ltr">{{ $monthLabel }}</span>
            @endif
        </div>

        <div class="row g-3">
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat-card
                    :title="__('dashboard.hours.stats.worked_days')"
                    :value="(string) (int) ($totals['worked_days'] ?? 0)"
                    icon="bi-calendar-check"
                    color="primary"
                />
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat-card
                    :title="__('dashboard.hours.stats.rest_days')"
                    :value="(string) (int) ($totals['rest_days'] ?? 0)"
                    icon="bi-cup-hot"
                    color="warning"
                />
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat-card
                    :title="__('dashboard.hours.stats.planned')"
                    :value="number_format((float) ($totals['planned'] ?? 0), 2).'h'"
                    icon="bi-bullseye"
                    color="info"
                />
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat-card
                    :title="__('dashboard.hours.stats.raw')"
                    :value="number_format((float) ($totals['raw'] ?? 0), 2).'h'"
                    icon="bi-stopwatch"
                    color="success"
                />
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat-card
                    :title="__('dashboard.hours.stats.credited')"
                    :value="number_format((float) ($totals['credited'] ?? $totals['actual'] ?? 0), 2).'h'"
                    icon="bi-check2-circle"
                    color="primary"
                />
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat-card
                    :title="__('dashboard.hours.stats.shortfall_credited')"
                    :value="number_format((float) ($totals['shortfall_credited'] ?? 0), 2).'h'"
                    icon="bi-exclamation-triangle"
                    color="danger"
                />
            </div>
        </div>

        <div class="row g-2 mt-2 small">
            <div class="col-12 col-md-4">
                <span class="text-body-secondary">{{ __('dashboard.hours.stats.shortfall_raw') }}:</span>
                <strong class="num-ltr hp-value">{{ number_format((float) ($totals['shortfall_raw'] ?? 0), 2) }}h</strong>
            </div>
            <div class="col-12 col-md-4">
                <span class="text-body-secondary">{{ __('dashboard.hours.stats.late') }}:</span>
                <strong class="num-ltr hp-value">{{ (int) ($totals['late_minutes'] ?? 0) }}</strong>
                <span class="text-body-secondary">({{ number_format(((int) ($totals['late_minutes'] ?? 0)) / 60, 2) }}h)</span>
            </div>
            <div class="col-12 col-md-4">
                <span class="text-body-secondary">{{ __('dashboard.hours.stats.early') }}:</span>
                <strong class="num-ltr hp-value">{{ (int) ($totals['early_minutes'] ?? 0) }}</strong>
                <span class="text-body-secondary">({{ number_format(((int) ($totals['early_minutes'] ?? 0)) / 60, 2) }}h)</span>
            </div>
        </div>
        <p class="small text-body-secondary mb-0 mt-2">{{ __('dashboard.hours.stats.hint') }}</p>
    </div>
</div>
