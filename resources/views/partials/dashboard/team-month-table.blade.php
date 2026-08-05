<div data-table-filter-root>
    <x-table-toolbar
        id="team-month"
        :search-placeholder="__('dashboard.hours.search_name')"
    />

    <div class="table-responsive">
        <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
            <thead>
                <tr>
                    <th scope="col">{{ __('shifts.employee') }}</th>
                    <th scope="col" class="col-num">{{ __('dashboard.hours.month_planned') }}</th>
                    <th scope="col" class="col-num">{{ __('dashboard.hours.month_actual') }}</th>
                    <th scope="col" class="col-num">{{ __('dashboard.hours.pct') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($teamSummary as $row)
                    @php
                        $name = app()->getLocale() === 'ar' ? $row['user']->name_ar : $row['user']->name_en;
                        $active = $focusUser && (int) $focusUser->id === (int) $row['user']->id;
                    @endphp
                    <tr @class(['table-active' => $active])
                        data-filter-text="{{ $name }} {{ $row['user']->name_ar }} {{ $row['user']->name_en }}">
                        <td>
                            <a href="{{ route($dashboardRoute, ['cal' => $calCursor, 'user_id' => $row['user']->id]) }}"
                               class="fw-semibold">{{ $name }}</a>
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
                    <td colspan="4" class="text-body-secondary text-center py-3">
                        {{ __('general.no_filter_results') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
