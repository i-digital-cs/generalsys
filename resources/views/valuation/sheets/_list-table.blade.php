@php
    $showPerson = $showPerson ?? false;
    $toolbarId = $toolbarId ?? 'valuation-sheets-list';
    $colspan = $showPerson ? 6 : 5;
    $localeAr = app()->getLocale() === 'ar';
    $employeeOptions = [];
    $dateOptions = [];
    if ($showPerson) {
        $employeeOptions = $sheets
            ->filter(fn ($row) => $row->user !== null)
            ->mapWithKeys(function ($row) use ($localeAr) {
                $label = $localeAr ? $row->user->name_ar : $row->user->name_en;

                return [(string) $row->user_id => $label];
            })
            ->unique()
            ->sortBy(fn (string $label): string => mb_strtolower($label), SORT_NATURAL)
            ->all();
    }
    $dateOptions = $sheets
        ->mapWithKeys(fn ($row) => [$row->date->toDateString() => $row->date->toDateString()])
        ->unique()
        ->sortDesc()
        ->all();
    $filters = [];
    if ($showPerson && $employeeOptions !== []) {
        $filters[] = [
            'name' => 'employee',
            'label' => __('valuation.person'),
            'options' => $employeeOptions,
        ];
    }
    if ($dateOptions !== []) {
        $filters[] = [
            'name' => 'date',
            'label' => __('valuation.date'),
            'options' => $dateOptions,
        ];
    }
    $filters[] = [
        'name' => 'status',
        'label' => __('valuation.status'),
        'options' => [
            'DRAFT' => __('valuation.statuses.DRAFT'),
            'SUBMITTED' => __('valuation.statuses.SUBMITTED'),
        ],
    ];
    $filters[] = [
        'name' => 'kpi',
        'label' => __('valuation.kpi'),
        'options' => [
            'loss' => __('valuation.labels.loss'),
            'acceptable' => __('valuation.labels.acceptable'),
            'gain' => __('valuation.labels.gain'),
            'above' => __('valuation.labels.above'),
        ],
    ];
@endphp

<div data-table-filter-root>
    <x-table-toolbar
        :id="$toolbarId"
        :search-placeholder="__('valuation.search_placeholder')"
        :filters="$filters"
    />
    <div class="table-responsive">
        <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
            <thead>
                <tr>
                    <th scope="col" class="col-num">{{ __('valuation.date') }}</th>
                    @if ($showPerson)
                        <th scope="col">{{ __('valuation.person') }}</th>
                    @endif
                    <th scope="col">{{ __('valuation.status') }}</th>
                    <th scope="col">{{ __('valuation.kpi') }}</th>
                    <th scope="col" class="col-num">{{ __('valuation.fields.actual_amount_total') }}</th>
                    <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sheets as $row)
                    @php
                        $kpi = $row->result['kpi_label'] ?? null;
                        $amount = (float) ($row->result['actual_amount_total'] ?? 0);
                        $person = $row->user
                            ? ($localeAr ? $row->user->name_ar : $row->user->name_en)
                            : '';
                        $dateKey = $row->date->toDateString();
                        $filterText = trim($dateKey.' '.$person.' '.($row->user?->name_ar ?? '').' '.($row->user?->name_en ?? '').' '.__('valuation.statuses.'.$row->status).' '.($kpi ? __('valuation.labels.'.$kpi) : ''));
                    @endphp
                    <tr
                        data-filter-text="{{ $filterText }}"
                        data-filter-employee="{{ $row->user_id }}"
                        data-filter-date="{{ $dateKey }}"
                        data-filter-status="{{ $row->status }}"
                        data-filter-kpi="{{ $kpi ?? '' }}"
                    >
                        <td class="col-num text-nowrap">
                            <span class="num-ltr">{{ $dateKey }}</span>
                        </td>
                        @if ($showPerson)
                            <td class="fw-semibold">
                                @if ($row->user)
                                    <a href="{{ route('admin.employees.edit', $row->user) }}">{{ $person }}</a>
                                @else
                                    {{ $person !== '' ? $person : '—' }}
                                @endif
                            </td>
                        @endif
                        <td>
                            <span @class([
                                'badge',
                                'text-bg-secondary' => $row->status === 'DRAFT',
                                'text-bg-success' => $row->status === 'SUBMITTED',
                            ])>
                                {{ __('valuation.statuses.'.$row->status) }}
                            </span>
                        </td>
                        <td>
                            @if ($kpi)
                                @php $kpiTone = \App\Services\Valuation\ValuationCalculator::statusTone($kpi); @endphp
                                <span @class([
                                    'badge',
                                    'text-bg-'.$kpiTone,
                                    'text-dark' => $kpiTone === 'warning',
                                ])>
                                    {{ __('valuation.labels.'.$kpi) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="col-num">
                            <span class="num-ltr"><x-money :amount="$amount" :currency="$currency ?? 'EGP'" /></span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('valuation.sheets.show', ['module' => $module, 'sheet' => $row]) }}">{{ __('valuation.view') }}</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('valuation.sheets.export.pdf', ['module' => $module, 'sheet' => $row]) }}">{{ __('valuation.export_pdf') }}</a>
                            @if (auth()->id() === $row->user_id)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('valuation.sheets.form', ['module' => $module, 'date' => $dateKey]) }}">{{ __('valuation.edit') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr data-table-empty-row class="d-none">
                    <td colspan="{{ $colspan }}" class="text-body-secondary text-center py-3">
                        {{ __('general.no_filter_results') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
