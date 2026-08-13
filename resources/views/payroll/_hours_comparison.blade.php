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
                    <div class="text-body-secondary small">{{ __('payroll.hours.required') }}</div>
                    <div class="fs-5"><span class="num-ltr">{{ number_format((float) $hoursMonth['totals']['planned'], 2) }}</span></div>
                </div>
                <div class="col-4">
                    <div class="text-body-secondary small">{{ __('payroll.hours.existing') }}</div>
                    <div class="fs-5"><span class="num-ltr">{{ number_format((float) $hoursMonth['totals']['actual'], 2) }}</span></div>
                </div>
                <div class="col-4">
                    <div class="text-body-secondary small">{{ __('payroll.hours.absent') }}</div>
                    <div class="fs-5">
                        <span @class(['num-ltr', 'text-danger' => ((float) $hoursMonth['totals']['planned'] - (float) $hoursMonth['totals']['actual']) > 0.01])>
                            {{ number_format(max(0, (float) $hoursMonth['totals']['planned'] - (float) $hoursMonth['totals']['actual']), 2) }}
                        </span>
                    </div>
                </div>
            </div>

            @php
                $breakdown = $hoursMonth['shift_breakdown'] ?? null;
            @endphp
            @if (is_array($breakdown) && isset($breakdown['shifts']))
                <div class="border rounded p-3 mb-3 bg-body-tertiary">
                    <div class="fw-semibold small mb-2">{{ __('payroll.hours.by_shift_title') }}</div>
                    <div class="small text-body-secondary mb-2">
                        {{ __('payroll.hours.work_days') }}:
                        <span class="num-ltr fw-semibold">{{ (int) ($breakdown['work_days'] ?? 0) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hr table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('payroll.hours.shift') }}</th>
                                    <th scope="col" class="col-num">{{ __('payroll.hours.required') }}</th>
                                    <th scope="col" class="col-num">{{ __('payroll.hours.existing') }}</th>
                                    <th scope="col" class="col-num">{{ __('payroll.hours.absent') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($breakdown['shifts'] as $type => $row)
                                    @continue((float) $row['required'] <= 0 && (float) $row['actual'] <= 0)
                                    <tr>
                                        <td>
                                            <div>{{ $row['label'] }}</div>
                                            @if ($type === 'ONLINE_HALF')
                                                <div class="small text-body-secondary">
                                                    {{ __('payroll.hours.period_morning') }}
                                                    · <span class="num-ltr">{{ __('employees.shifts.windows.ONLINE_HALF') }}</span>
                                                </div>
                                            @elseif ($type === 'FULL_TIME')
                                                <div class="small text-body-secondary">
                                                    {{ __('payroll.hours.period_day') }}
                                                    · <span class="num-ltr">{{ __('employees.shifts.windows.FULL_TIME') }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="col-num"><span class="num-ltr">{{ number_format((float) $row['required'], 2) }}</span></td>
                                        <td class="col-num"><span class="num-ltr">{{ number_format((float) $row['actual'], 2) }}</span></td>
                                        <td class="col-num">
                                            <span @class(['num-ltr', 'text-danger' => (float) $row['absent'] > 0])>
                                                {{ number_format((float) $row['absent'], 2) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @php
                $dayRows = collect($hoursMonth['days'])->filter(function (array $day) {
                    return ((float) $day['planned_hours'] > 0.01)
                        || ((float) $day['actual_hours'] > 0.01)
                        || (! empty($day['shift_lines']));
                })->values();
            @endphp

            <div class="fw-semibold small mb-2">{{ __('payroll.hours.by_day_title') }}</div>
            @if ($dayRows->isEmpty())
                <p class="text-body-secondary small mb-0">{{ __('payroll.hours.no_days') }}</p>
            @else
                <div data-table-filter-root>
                    <x-table-toolbar
                        id="payroll-hours-days"
                        :search-placeholder="__('dashboard.hours.search_date')"
                    />
                    <div class="table-responsive" style="max-height: 28rem;">
                        <table class="table table-hr table-sm align-middle mb-0" data-table-filterable>
                            <thead class="sticky-top bg-body">
                                <tr>
                                    <th scope="col">{{ __('payroll.hours.date') }}</th>
                                    <th scope="col">{{ __('payroll.hours.shift') }}</th>
                                    <th scope="col" class="col-num">{{ __('payroll.hours.required') }}</th>
                                    <th scope="col" class="col-num">{{ __('payroll.hours.existing') }}</th>
                                    <th scope="col" class="col-num">{{ __('payroll.hours.absent') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dayRows as $day)
                                    @php
                                        $lines = $day['shift_lines'] ?? [];
                                        if ($lines === []) {
                                            $lines = [[
                                                'label' => collect($day['planned_intervals'] ?? [])->pluck('label')->filter()->implode(' · ')
                                                    ?: __('general.not_available'),
                                                'required' => (float) $day['planned_hours'],
                                                'actual' => (float) $day['actual_hours'],
                                                'absent' => max(0, (float) $day['planned_hours'] - (float) $day['actual_hours']),
                                            ]];
                                        }
                                        $filterText = $day['date'].' '.collect($lines)->pluck('label')->implode(' ');
                                    @endphp
                                    @foreach ($lines as $lineIndex => $line)
                                        <tr data-filter-text="{{ $filterText }}">
                                            <td class="text-nowrap">
                                                @if ($lineIndex === 0)
                                                    <span class="num-ltr">{{ $day['date'] }}</span>
                                                    @if (! empty($day['is_vacation']))
                                                        <div><span class="badge text-bg-info">{{ __('dashboard.hours.vacation') }}</span></div>
                                                    @elseif (! empty($day['is_rest_day']))
                                                        <div><span class="badge text-bg-warning text-dark">{{ __('dashboard.hours.rest_day') }}</span></div>
                                                    @endif
                                                @endif
                                            </td>
                                            <td>
                                                <div>{{ $line['label'] }}</div>
                                                @php $lineType = $line['shift_type'] ?? null; @endphp
                                                @if ($lineType === 'ONLINE_HALF')
                                                    <div class="small text-body-secondary">
                                                        {{ __('payroll.hours.period_morning') }}
                                                        · <span class="num-ltr">{{ __('employees.shifts.windows.ONLINE_HALF') }}</span>
                                                    </div>
                                                @elseif ($lineType === 'FULL_TIME')
                                                    <div class="small text-body-secondary">
                                                        {{ __('payroll.hours.period_day') }}
                                                        · <span class="num-ltr">{{ __('employees.shifts.windows.FULL_TIME') }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="col-num"><span class="num-ltr">{{ number_format((float) $line['required'], 2) }}</span></td>
                                            <td class="col-num"><span class="num-ltr">{{ number_format((float) $line['actual'], 2) }}</span></td>
                                            <td class="col-num">
                                                <span @class(['num-ltr', 'fw-semibold', 'text-danger' => (float) $line['absent'] > 0.01])>
                                                    {{ number_format((float) $line['absent'], 2) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
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
