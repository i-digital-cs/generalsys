{{-- Modern card presentation (working tree) --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('salary.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('salary.subtitle') }}</p>
        </div>
    </div>

    <div class="eval-card-ui" data-eval-card-ui="hr-salary">
        <h2 class="h6 mb-2" style="color: var(--gh-board-navy)">{{ __('salary.list') }}</h2>
        @if ($records->isEmpty())
            <div class="eval-card-ui__empty">
                <x-empty-state
                    :title="__('salary.empty_title')"
                    :text="__('salary.empty_text')"
                    icon="bi-wallet2"
                />
            </div>
        @else
            <div data-table-filter-root>
                <div class="eval-card-ui__toolbar">
                    <x-table-toolbar
                        id="employee-salary-list"
                        :search-placeholder="__('general.search_placeholder')"
                        :filters="[[
                            'name' => 'status',
                            'label' => __('salary.fields.status'),
                            'options' => [
                                'APPROVED' => __('payroll.status.APPROVED'),
                                'PAID' => __('payroll.status.PAID'),
                            ],
                        ]]"
                    />
                </div>
                <div class="eval-card-ui__grid">
                    @foreach ($records as $row)
                        @php
                            $period = \Carbon\Carbon::create($row->year, $row->month, 1)->translatedFormat('F Y');
                        @endphp
                        <div
                            data-filterable-item
                            data-filter-text="{{ $period }} {{ $row->year }} {{ $row->month }}"
                            data-filter-status="{{ $row->status }}"
                            class="h-100"
                        >
                            <x-gh.record-card
                                :title="$period"
                                :title-href="route('employee.salary.show', $row)"
                            >
                                <x-slot:badges>
                                    <span @class([
                                        'gh-record-card__badge',
                                        'gh-record-card__badge--status' => in_array($row->status, ['APPROVED', 'PAID'], true),
                                    ])>
                                        {{ __('payroll.status.'.$row->status) }}
                                    </span>
                                </x-slot:badges>
                                <x-slot:meta>
                                    <div class="gh-record-card__cell">
                                        <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-cash"></i></span>
                                        <span class="gh-record-card__value"><x-money :amount="$row->gross_pay" :currency="$row->currency" /></span>
                                    </div>
                                    <div class="gh-record-card__cell">
                                        <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
                                        <span class="gh-record-card__value fw-bold"><x-money :amount="$row->net_pay" :currency="$row->currency" /></span>
                                    </div>
                                </x-slot:meta>
                                <x-slot:actions>
                                    <a href="{{ route('employee.salary.show', $row) }}" class="gh-board-btn">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                        <span>{{ __('salary.open') }}</span>
                                    </a>
                                </x-slot:actions>
                            </x-gh.record-card>
                        </div>
                    @endforeach
                    <div data-table-empty-msg class="d-none eval-card-ui__empty text-body-secondary text-center">
                        {{ __('general.no_filter_results') }}
                    </div>
                </div>
            </div>
            <div class="eval-card-ui__pager">{{ $records->links() }}</div>
        @endif
    </div>
