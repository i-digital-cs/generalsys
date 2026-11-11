{{-- Modern card presentation (working tree) --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('evaluation.me.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('evaluation.me.subtitle') }}</p>
        </div>
    </div>

    <div class="eval-card-ui" data-eval-card-ui="employee">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                <form method="GET" action="{{ route('employee.evaluation') }}" class="row g-2 align-items-end">
                    <div class="col-6 col-md-4">
                        <label for="month" class="form-label">{{ __('evaluation.tl.month') }}</label>
                        <select id="month" name="month" class="form-select" onchange="this.form.submit()">
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected((int) $month === $m)>
                                    {{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="year" class="form-label">{{ __('evaluation.tl.year') }}</label>
                        <select id="year" name="year" class="form-select" onchange="this.form.submit()">
                            @for ($y = now()->year; $y >= now()->year - 2; $y--)
                                <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-7">
                @if ($evaluations->isEmpty())
                    <div class="eval-card-ui__empty">
                        <x-empty-state
                            :title="__('evaluation.me.empty_title')"
                            :text="__('evaluation.me.empty_text')"
                            icon="bi-star"
                        />
                    </div>
                @else
                    @php
                        $monthLabel = \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y');
                    @endphp
                    <div class="eval-card-ui__grid">
                        @foreach ($evaluations as $evaluation)
                            <x-gh.record-card :title="$monthLabel">
                                <x-slot:badges>
                                    <span class="gh-record-card__badge gh-record-card__badge--status">
                                        {{ number_format((float) $evaluation->total_score, 2) }}%
                                    </span>
                                </x-slot:badges>
                                <x-slot:meta>
                                    <x-gh.meta-cell
                                        icon="bi-person-badge"
                                        :value="__('evaluation.me.by_leader', ['name' => $evaluation->teamLeader?->localizedName() ?? '—'])"
                                    />
                                    <x-gh.meta-cell icon="bi-info-circle" :value="__('evaluation.me.read_only_hint')" />
                                </x-slot:meta>
                                <x-slot:actions>
                                    <div class="w-100">
                                        <x-score-bar :label="__('evaluation.fields.performance')" :value="$evaluation->performance_score" />
                                        <x-score-bar :label="__('evaluation.fields.behavior')" :value="$evaluation->behavior_score" />
                                        <x-score-bar :label="__('evaluation.fields.productivity')" :value="$evaluation->productivity_score" />
                                        <x-score-bar :label="__('evaluation.fields.interest_return')" :value="$evaluation->interest_return_score" />
                                        <x-score-bar :label="__('evaluation.fields.attendance')" :value="$evaluation->attendance_score" />
                                        <hr>
                                        <x-score-bar :label="__('evaluation.fields.total')" :value="$evaluation->total_score"
                                                     :hint="__('evaluation.tl.total_hint')" />

                                        <h3 class="h6 mt-3">{{ __('evaluation.me.text_title') }}</h3>
                                        @if (filled($evaluation->evaluation_text))
                                            <div class="border rounded p-3 bg-body-tertiary">{{ $evaluation->evaluation_text }}</div>
                                        @else
                                            <p class="text-body-secondary mb-0">{{ __('evaluation.me.text_empty') }}</p>
                                        @endif
                                    </div>
                                </x-slot:actions>
                            </x-gh.record-card>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-12 col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('evaluation.me.history') }}</h2>
                        @if ($history->isEmpty())
                            <p class="text-body-secondary small mb-0">{{ __('evaluation.me.history_empty') }}</p>
                        @else
                            <div data-table-filter-root>
                                <x-table-toolbar
                                    id="evaluation-history"
                                    :search-placeholder="__('general.search_placeholder')"
                                />
                                <div class="d-flex flex-column gap-2 mt-2" data-eval-history-list>
                                    @foreach ($history as $row)
                                        @php
                                            $active = (int) $row->month === (int) $month && (int) $row->year === (int) $year;
                                            $label = \Carbon\Carbon::create($row->year, $row->month, 1)->translatedFormat('F Y');
                                            $leaderName = $row->teamLeader?->localizedName() ?? '—';
                                            $scoreLabel = number_format((float) $row->total_score, 2).'%';
                                        @endphp
                                        <div
                                            data-filterable-item
                                            data-filter-text="{{ $label }} {{ $leaderName }} {{ $row->month }} {{ $row->year }}"
                                            @class(['border border-warning rounded-2' => $active])
                                        >
                                            <x-gh.record-card
                                                :title="$label"
                                                :title-href="route('employee.evaluation', ['month' => $row->month, 'year' => $row->year])"
                                            >
                                                <x-slot:badges>
                                                    <span class="gh-record-card__badge gh-record-card__badge--status">{{ $scoreLabel }}</span>
                                                </x-slot:badges>
                                                <x-slot:meta>
                                                    <x-gh.meta-cell icon="bi-person-badge" :value="$leaderName" />
                                                    <x-gh.meta-cell icon="bi-percent" :value="$scoreLabel" :ltr="true" />
                                                </x-slot:meta>
                                                <x-slot:actions>
                                                    <a
                                                        href="{{ route('employee.evaluation', ['month' => $row->month, 'year' => $row->year]) }}"
                                                        class="gh-board-btn"
                                                    >
                                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                                        <span>{{ __('evaluation.tl.open') }}</span>
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
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
