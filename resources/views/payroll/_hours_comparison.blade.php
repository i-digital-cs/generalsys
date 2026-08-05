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
                    <div class="fs-5"><span class="num-ltr">{{ number_format((float) $hoursMonth['totals']['planned'], 2) }}</span></div>
                </div>
                <div class="col-4">
                    <div class="text-body-secondary small">{{ __('payroll.hours.actual') }}</div>
                    <div class="fs-5"><span class="num-ltr">{{ number_format((float) $hoursMonth['totals']['actual'], 2) }}</span></div>
                </div>
                <div class="col-4">
                    <div class="text-body-secondary small">{{ __('payroll.hours.shortfall') }}</div>
                    <div class="fs-5">
                        <span class="num-ltr">
                            {{ number_format(max(0, (float) $hoursMonth['totals']['planned'] - (float) $hoursMonth['totals']['actual']), 2) }}
                        </span>
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
                <div data-table-filter-root>
                    <x-table-toolbar
                        id="payroll-hours-gaps"
                        :search-placeholder="__('dashboard.hours.search_date')"
                    />
                    <div class="table-responsive">
                        <table class="table table-hr table-sm align-middle mb-0" data-table-filterable>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('payroll.hours.date') }}</th>
                                    <th scope="col">{{ __('payroll.hours.shift') }}</th>
                                    <th scope="col" class="col-num">{{ __('payroll.hours.planned') }}</th>
                                    <th scope="col" class="col-num">{{ __('payroll.hours.actual') }}</th>
                                    <th scope="col" class="col-num">{{ __('payroll.hours.shortfall') }}</th>
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
                                    <tr data-filter-text="{{ $day['date'] }} {{ $labels }} {{ $times }}">
                                        <td><span class="num-ltr">{{ $day['date'] }}</span></td>
                                        <td>
                                            <div>{{ $labels !== '' ? $labels : __('general.not_available') }}</div>
                                            @if ($times !== '')
                                                <div class="small text-body-secondary"><span class="num-ltr">{{ $times }}</span></div>
                                            @endif
                                        </td>
                                        <td class="col-num"><span class="num-ltr">{{ number_format((float) $day['planned_hours'], 2) }}</span></td>
                                        <td class="col-num"><span class="num-ltr">{{ number_format((float) $day['actual_hours'], 2) }}</span></td>
                                        <td class="col-num fw-semibold"><span class="num-ltr">{{ number_format($short, 2) }}</span></td>
                                    </tr>
                                @endforeach
                                <tr data-table-empty-row class="d-none">
                                    <td colspan="5" class="text-body-secondary text-center py-3">
                                        {{ __('general.no_filter_results') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
