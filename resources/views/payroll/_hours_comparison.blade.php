{{-- Planned vs actual hours (facts only — Admin decides any money penalty). --}}
@php
    /** @var array{totals: array{planned: float, actual: float, pct: ?float}, days: list}|null $hoursMonth */
    $hoursMonth = $hoursMonth ?? null;
@endphp

@if ($hoursMonth !== null)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent">
            <h2 class="h6 mb-0">{{ __('payroll.hours.title') }}</h2>
            <p class="small text-body-secondary mb-0 mt-1">{{ __('payroll.hours.hint') }}</p>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-4">
                    <div class="text-body-secondary small">{{ __('payroll.hours.planned') }}</div>
                    <div class="fs-5" dir="ltr">{{ number_format((float) $hoursMonth['totals']['planned'], 2) }}</div>
                </div>
                <div class="col-4">
                    <div class="text-body-secondary small">{{ __('payroll.hours.actual') }}</div>
                    <div class="fs-5" dir="ltr">{{ number_format((float) $hoursMonth['totals']['actual'], 2) }}</div>
                </div>
                <div class="col-4">
                    <div class="text-body-secondary small">{{ __('payroll.hours.shortfall') }}</div>
                    <div class="fs-5" dir="ltr">
                        {{ number_format(max(0, (float) $hoursMonth['totals']['planned'] - (float) $hoursMonth['totals']['actual']), 2) }}
                    </div>
                </div>
            </div>

            @php
                $gaps = collect($hoursMonth['days'])->filter(function (array $day) {
                    return ((float) $day['planned_hours'] - (float) $day['actual_hours']) > 0.01;
                })->values();
            @endphp

            @if ($gaps->isEmpty())
                <p class="text-body-secondary small mb-0">{{ __('payroll.hours.no_gaps') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('payroll.hours.date') }}</th>
                                <th scope="col">{{ __('payroll.hours.shift') }}</th>
                                <th scope="col" class="text-end">{{ __('payroll.hours.planned') }}</th>
                                <th scope="col" class="text-end">{{ __('payroll.hours.actual') }}</th>
                                <th scope="col" class="text-end">{{ __('payroll.hours.shortfall') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($gaps as $day)
                                @php
                                    $short = round((float) $day['planned_hours'] - (float) $day['actual_hours'], 2);
                                    $labels = collect($day['planned_intervals'] ?? [])
                                        ->pluck('label')
                                        ->filter()
                                        ->implode(' · ');
                                    $times = collect($day['actual_intervals'] ?? [])
                                        ->map(function (array $iv) {
                                            $start = $iv['start'] ?? '—';
                                            $end = ($iv['open'] ?? false) ? '…' : ($iv['end'] ?? '—');

                                            return $start.' → '.$end;
                                        })
                                        ->implode(' · ');
                                @endphp
                                <tr>
                                    <td dir="ltr">{{ $day['date'] }}</td>
                                    <td>
                                        <div>{{ $labels !== '' ? $labels : __('general.not_available') }}</div>
                                        @if ($times !== '')
                                            <div class="small text-body-secondary" dir="ltr">{{ $times }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end" dir="ltr">{{ number_format((float) $day['planned_hours'], 2) }}</td>
                                    <td class="text-end" dir="ltr">{{ number_format((float) $day['actual_hours'], 2) }}</td>
                                    <td class="text-end fw-semibold" dir="ltr">{{ number_format($short, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
