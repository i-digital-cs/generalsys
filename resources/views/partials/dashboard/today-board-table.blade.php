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
                    <th scope="col">{{ __('clock.photos.title') }}</th>
                    <th scope="col" class="col-num">{{ __('dashboard.hours.should_spend') }}</th>
                    <th scope="col" class="col-num">{{ __('dashboard.hours.spent') }}</th>
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
                            @else
                                <span class="small text-body-secondary">{{ __('clock.photos.none') }}</span>
                            @endif
                        </td>
                        <td class="col-num"><span class="num-ltr">{{ number_format($row['planned'], 2) }}h</span></td>
                        <td class="col-num"><span class="num-ltr">{{ number_format($row['actual'], 2) }}h</span></td>
                        <td class="col-num">
                            <span class="num-ltr">
                                {{ $row['pct'] === null ? '—' : number_format($row['pct'], 1).'%' }}
                            </span>
                        </td>
                    </tr>
                @endforeach
                <tr data-table-empty-row class="d-none">
                    <td colspan="6" class="text-body-secondary text-center py-3">
                        {{ __('general.no_filter_results') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
