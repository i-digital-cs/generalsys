@php
    $showPerson = $showPerson ?? false;
    $toolbarId = $toolbarId ?? 'valuation-sheets-list';
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
    <div class="eval-card-ui__toolbar">
        <x-table-toolbar
            :id="$toolbarId"
            :search-placeholder="__('valuation.search_placeholder')"
            :filters="$filters"
        />
    </div>

    <div class="eval-card-ui__grid">
        @foreach ($sheets as $row)
            @php
                $kpi = $row->result['kpi_label'] ?? null;
                $amount = (float) ($row->result['actual_amount_total'] ?? 0);
                $person = $row->user
                    ? ($localeAr ? $row->user->name_ar : $row->user->name_en)
                    : '';
                $dateKey = $row->date->toDateString();
                $filterText = trim($dateKey.' '.$person.' '.($row->user?->name_ar ?? '').' '.($row->user?->name_en ?? '').' '.__('valuation.statuses.'.$row->status).' '.($kpi ? __('valuation.labels.'.$kpi) : ''));
                $title = $showPerson && $person !== '' ? $person : $dateKey;
                $titleHref = ($showPerson && $row->user)
                    ? route('admin.employees.edit', $row->user)
                    : route('valuation.sheets.show', ['module' => $module, 'sheet' => $row]);
            @endphp
            <div
                data-filterable-item
                data-filter-text="{{ $filterText }}"
                data-filter-employee="{{ $row->user_id }}"
                data-filter-date="{{ $dateKey }}"
                data-filter-status="{{ $row->status }}"
                data-filter-kpi="{{ $kpi ?? '' }}"
                class="h-100"
            >
                <x-gh.record-card
                    :title="$title"
                    :title-href="$titleHref"
                    :title-ltr="! $showPerson"
                >
                    <x-slot:badges>
                        <span @class([
                            'gh-record-card__badge',
                            'gh-record-card__badge--muted' => $row->status === 'DRAFT',
                            'gh-record-card__badge--status' => $row->status === 'SUBMITTED',
                        ])>
                            {{ __('valuation.statuses.'.$row->status) }}
                        </span>
                        @if ($kpi)
                            <span class="gh-record-card__badge gh-record-card__badge--muted">{{ __('valuation.labels.'.$kpi) }}</span>
                        @endif
                    </x-slot:badges>
                    <x-slot:meta>
                        <x-gh.meta-cell icon="bi-calendar3" :value="$dateKey" :ltr="true" />
                        @if ($showPerson && $person !== '')
                            <x-gh.meta-cell icon="bi-person" :value="$person" />
                        @endif
                        <div class="gh-record-card__cell">
                            <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
                            <span class="gh-record-card__value"><x-money :amount="$amount" :currency="$currency ?? 'EGP'" /></span>
                        </div>
                    </x-slot:meta>
                    <x-slot:actions>
                        <a class="gh-board-btn" href="{{ route('valuation.sheets.show', ['module' => $module, 'sheet' => $row]) }}">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                            <span>{{ __('valuation.view') }}</span>
                        </a>
                        <a class="gh-board-btn gh-board-btn--ghost" href="{{ route('valuation.sheets.export.pdf', ['module' => $module, 'sheet' => $row]) }}">
                            <i class="bi bi-filetype-pdf" aria-hidden="true"></i>
                            <span>{{ __('valuation.export_pdf') }}</span>
                        </a>
                        @if (auth()->id() === $row->user_id)
                            <a class="gh-board-btn gh-board-btn--ghost" href="{{ route('valuation.sheets.form', ['module' => $module, 'date' => $dateKey]) }}">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                <span>{{ __('valuation.edit') }}</span>
                            </a>
                        @endif
                        @can('delete', $row)
                            <form method="POST" action="{{ route('valuation.sheets.destroy', ['module' => $module, 'sheet' => $row]) }}"
                                  class="d-inline"
                                  onsubmit="return confirm(@js(__('valuation.confirm_delete')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="gh-board-btn gh-board-btn--ghost text-danger">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                    <span>{{ __('valuation.delete') }}</span>
                                </button>
                            </form>
                        @endcan
                    </x-slot:actions>
                </x-gh.record-card>
            </div>
        @endforeach
        <div data-table-empty-msg class="d-none eval-card-ui__empty text-body-secondary text-center">
            {{ __('general.no_filter_results') }}
        </div>
    </div>
</div>
