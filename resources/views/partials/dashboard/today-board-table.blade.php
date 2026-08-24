@php
    $statusFilters = [
        'IN' => __('dashboard.hours.statuses.IN'),
        'OUT' => __('dashboard.hours.statuses.OUT'),
        'NONE' => __('dashboard.hours.statuses.NONE'),
    ];
@endphp

<div data-table-filter-root>
    <x-table-toolbar
        id="today-board"
        :search-placeholder="__('dashboard.hours.search_name')"
        :filters="[[
            'name' => 'status',
            'label' => __('dashboard.hours.status'),
            'options' => $statusFilters,
        ]]"
    />

    <div class="table-responsive">
        <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
            <thead>
                <tr>
                    <th scope="col">{{ __('shifts.employee') }}</th>
                    <th scope="col">{{ __('dashboard.hours.status') }}</th>
                    <th scope="col">{{ __('clock.photos.title') }} / {{ __('clock.location.title') }}</th>
                    <th scope="col" class="col-num">{{ __('dashboard.hours.should_spend') }}</th>
                    <th scope="col" class="col-num">{{ __('dashboard.hours.spent') }}</th>
                    <th scope="col">{{ __('attendance_review.comparison.title') }}</th>
                    <th scope="col" class="col-num">{{ __('dashboard.hours.pct') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($todayStatus as $row)
                    @php
                        $name = app()->getLocale() === 'ar' ? $row['user']->name_ar : $row['user']->name_en;
                    @endphp
                    <tr data-filter-text="{{ $name }} {{ $row['user']->name_ar }} {{ $row['user']->name_en }}"
                        data-filter-status="{{ $row['status'] }}">
                        <td class="fw-semibold">
                            <a href="{{ route($dashboardRoute, ['cal' => $calCursor, 'user_id' => $row['user']->id]) }}">
                                {{ $name }}
                            </a>
                        </td>
                        <td>
                            <span @class([
                                'badge',
                                'text-bg-success' => $row['status'] === 'IN',
                                'text-bg-secondary' => $row['status'] === 'OUT',
                                'text-bg-light' => $row['status'] === 'NONE',
                            ])>
                                {{ __('dashboard.hours.statuses.'.$row['status']) }}
                            </span>
                        </td>
                        <td>
                            @if (($row['photo_count'] ?? 0) > 0)
                                <x-attendance-photos :photos="$row['photos']" compact />
                            @endif
                            @if (! empty($row['attendance']))
                                <x-attendance-location :attendance="$row['attendance']" compact class="mt-1" />
                            @endif
                            @if (($row['photo_count'] ?? 0) === 0 && empty($row['attendance']?->clock_in_lat) && empty($row['attendance']?->clock_out_lat))
                                <span class="small text-body-secondary">{{ __('clock.photos.none') }}</span>
                            @endif
                        </td>
                        <td class="col-num"><span class="num-ltr">{{ number_format($row['planned'], 2) }}h</span></td>
                        <td class="col-num"><span class="num-ltr">{{ number_format($row['actual'], 2) }}h</span></td>
                        <td>
                            @foreach (($row['attendances'] ?? collect()) as $attendanceRow)
                                <div class="mb-1">
                                    <span class="small text-body-secondary">{{ __('clock.shifts.'.$attendanceRow->shift_type) }}</span>
                                    <x-attendance-time-metrics :attendance="$attendanceRow" />
                                </div>
                            @endforeach
                            @if (empty($row['attendances']) && ! empty($row['attendance']))
                                <x-attendance-time-metrics :attendance="$row['attendance']" />
                            @elseif (empty($row['attendances']) && empty($row['attendance']))
                                <span class="small text-body-secondary">—</span>
                            @endif
                        </td>
                        <td class="col-num">
                            <span class="num-ltr">
                                {{ $row['pct'] === null ? '—' : number_format($row['pct'], 1).'%' }}
                            </span>
                        </td>
                    </tr>
                @endforeach
                <tr data-table-empty-row class="d-none">
                    <td colspan="7" class="text-body-secondary text-center py-3">
                        {{ __('general.no_filter_results') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
