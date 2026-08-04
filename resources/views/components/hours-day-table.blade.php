@props([
    'days',
    'totals',
    'title' => null,
])

@php
    $title = $title ?? __('dashboard.hours.day_list_title');
@endphp

<div class="card border-0 shadow-sm h-100">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h6 mb-0">{{ $title }}</h2>
            <div class="small text-body-secondary">
                {{ __('dashboard.hours.month_planned') }}:
                <strong dir="ltr">{{ number_format($totals['planned'], 2) }}h</strong>
                ·
                {{ __('dashboard.hours.month_actual') }}:
                <strong dir="ltr">{{ number_format($totals['actual'], 2) }}h</strong>
                ·
                {{ __('dashboard.hours.pct') }}:
                <strong dir="ltr">
                    {{ $totals['pct'] === null ? '—' : number_format($totals['pct'], 1).'%' }}
                </strong>
            </div>
        </div>

        <div class="table-responsive" style="max-height: 28rem;">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="sticky-top bg-body">
                    <tr>
                        <th scope="col">{{ __('dashboard.hours.date') }}</th>
                        <th scope="col">{{ __('dashboard.hours.should_spend') }}</th>
                        <th scope="col">{{ __('dashboard.hours.spent') }}</th>
                        <th scope="col" class="text-end">{{ __('dashboard.hours.pct') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($days as $day)
                        <tr @class(['table-active' => $day['date'] === now()->toDateString()])>
                            <td class="text-nowrap">
                                <span dir="ltr">{{ $day['date'] }}</span>
                                @if (! empty($day['is_vacation']))
                                    <div>
                                        <span class="badge text-bg-info">{{ __('dashboard.hours.vacation') }}</span>
                                    </div>
                                @elseif (! empty($day['is_rest_day']))
                                    <div>
                                        <span class="badge text-bg-warning text-dark">{{ __('dashboard.hours.rest_day') }}</span>
                                    </div>
                                @elseif ($day['from_profile'])
                                    <div class="small text-body-secondary">{{ __('dashboard.hours.from_profile') }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold" dir="ltr">{{ number_format($day['planned_hours'], 2) }}h</div>
                                <ul class="list-unstyled small text-body-secondary mb-0">
                                    @foreach ($day['planned_intervals'] as $interval)
                                        <li dir="ltr">
                                            {{ $interval['label'] }}
                                            @if ($interval['start'] && $interval['end'])
                                                {{ $interval['start'] }}–{{ $interval['end'] }}
                                            @endif
                                            ({{ number_format($interval['hours'], 2) }}h)
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>
                                <div class="fw-semibold" dir="ltr">{{ number_format($day['actual_hours'], 2) }}h</div>
                                @if ($day['actual_intervals'] === [])
                                    <span class="small text-body-secondary">{{ __('dashboard.hours.no_sessions') }}</span>
                                @else
                                    <ul class="list-unstyled small text-body-secondary mb-0">
                                        @foreach ($day['actual_intervals'] as $interval)
                                            <li class="mb-1">
                                                <span dir="ltr">
                                                    {{ $interval['label'] }}
                                                    {{ $interval['start'] ?? '—' }}→{{ $interval['end'] ?? __('dashboard.hours.open') }}
                                                    @if (! $interval['open'])
                                                        ({{ number_format($interval['hours'], 2) }}h)
                                                    @endif
                                                </span>
                                                <x-attendance-flags
                                                    :friday="$interval['is_friday_work'] ?? false"
                                                    :rest-day="$interval['is_day_off_work'] ?? false"
                                                    :holiday="$interval['is_official_holiday_work'] ?? false"
                                                    class="mt-1"
                                                />
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="text-end" dir="ltr">
                                {{ $day['pct'] === null ? '—' : number_format($day['pct'], 1).'%' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
