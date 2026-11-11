{{-- Modern card presentation (working tree) --}}
<div class="mb-3">
        <h1 class="h4 mb-1">{{ __('advances.my_title') }}</h1>
        <p class="text-body-secondary small mb-0">{{ __('advances.my_subtitle') }}</p>
    </div>

    <div class="eval-card-ui" data-eval-card-ui="hr-my-advances">
        @if ($advances->isEmpty())
            <div class="eval-card-ui__empty">
                <x-empty-state
                    :title="__('advances.my_empty_title')"
                    :text="__('advances.my_empty_text')"
                    icon="bi-cash-coin"
                />
            </div>
        @else
            <div data-table-filter-root>
                <div class="eval-card-ui__toolbar">
                    <x-table-toolbar
                        id="employee-advances-list"
                        :search-placeholder="__('general.search_placeholder')"
                        :filters="[[
                            'name' => 'status',
                            'label' => __('advances.fields.status'),
                            'options' => [
                                'ACTIVE' => __('advances.status.ACTIVE'),
                                'FULLY_DEDUCTED' => __('advances.status.FULLY_DEDUCTED'),
                            ],
                        ]]"
                    />
                </div>
                <div class="eval-card-ui__grid">
                    @foreach ($advances as $row)
                        @php
                            $currency = auth()->user()->currency ?? 'EGP';
                        @endphp
                        <div
                            data-filterable-item
                            data-filter-text="{{ $row->date_given->toDateString() }} {{ $row->amount }} {{ $row->remaining_amount }}"
                            data-filter-status="{{ $row->status }}"
                            class="h-100"
                        >
                            <x-gh.record-card :title="$row->date_given->translatedFormat('d M Y')">
                                <x-slot:badges>
                                    <span @class([
                                        'gh-record-card__badge',
                                        'gh-record-card__badge--status' => $row->status === 'ACTIVE',
                                        'gh-record-card__badge--muted' => $row->status === 'FULLY_DEDUCTED',
                                    ])>
                                        {{ __('advances.status.'.$row->status) }}
                                    </span>
                                </x-slot:badges>
                                <x-slot:meta>
                                    <div class="gh-record-card__cell">
                                        <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
                                        <span class="gh-record-card__value"><x-money :amount="$row->amount" :currency="$currency" /></span>
                                    </div>
                                    <div class="gh-record-card__cell">
                                        <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-calendar-month"></i></span>
                                        <span class="gh-record-card__value"><x-money :amount="$row->deduction_amount_monthly" :currency="$currency" /></span>
                                    </div>
                                    <div class="gh-record-card__cell">
                                        <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-piggy-bank"></i></span>
                                        <span class="gh-record-card__value fw-bold"><x-money :amount="$row->remaining_amount" :currency="$currency" /></span>
                                    </div>
                                </x-slot:meta>
                            </x-gh.record-card>
                        </div>
                    @endforeach
                    <div data-table-empty-msg class="d-none eval-card-ui__empty text-body-secondary text-center">
                        {{ __('general.no_filter_results') }}
                    </div>
                </div>
            </div>
            <div class="eval-card-ui__pager">{{ $advances->links() }}</div>
        @endif
    </div>
