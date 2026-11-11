{{-- Modern card presentation (working tree) --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('evaluation.tl.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('evaluation.tl.subtitle') }}</p>
        </div>
    </div>

    <div class="eval-card-ui" data-eval-card-ui="tl-index">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                <form method="GET" action="{{ route('team-leader.evaluations') }}" class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label for="month" class="form-label">{{ __('evaluation.tl.month') }}</label>
                        <select id="month" name="month" class="form-select" onchange="this.form.submit()">
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected((int) $month === $m)>
                                    {{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
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

        <h2 class="h6 mb-2" style="color: var(--gh-board-navy)">{{ __('evaluation.tl.team_list') }}</h2>

        @if ($rows->isEmpty())
            <div class="eval-card-ui__empty">
                <x-empty-state
                    :title="__('evaluation.tl.empty_title')"
                    :text="__('evaluation.tl.empty_text')"
                    icon="bi-star"
                />
            </div>
        @else
            @php
                $periodLabel = \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y');
            @endphp
            <div data-table-filter-root>
                <div class="eval-card-ui__toolbar">
                    <x-table-toolbar
                        id="tl-evaluations-list"
                        :search-placeholder="__('dashboard.hours.search_name')"
                        :filters="[[
                            'name' => 'status',
                            'label' => __('evaluation.tl.status'),
                            'options' => [
                                'saved' => __('evaluation.tl.saved'),
                                'draft' => __('evaluation.tl.draft'),
                            ],
                        ]]"
                    />
                </div>
                <div class="eval-card-ui__grid">
                    @foreach ($rows as $row)
                        @php
                            $member = $row['employee'];
                            $name = app()->getLocale() === 'ar' ? $member->name_ar : $member->name_en;
                            $statusKey = $row['saved'] ? 'saved' : 'draft';
                            $scoreLabel = number_format($row['scores']['total_score'], 2).'%';
                            $photoUrl = ($member->profile?->personal_photo_path && auth()->user()?->can('view', $member))
                                ? route('employees.photo', $member)
                                : null;
                            $initial = mb_substr($name, 0, 1);
                        @endphp
                        <div
                            data-filterable-item
                            data-filter-text="{{ $name }} {{ $member->name_ar }} {{ $member->name_en }}"
                            data-filter-status="{{ $statusKey }}"
                            class="h-100"
                        >
                            <x-gh.record-card
                                :title="$name"
                                :title-href="route('team-leader.evaluations.show', ['employee' => $member, 'month' => $month, 'year' => $year])"
                                :image-url="$photoUrl"
                                :image-alt="$name"
                                image-variant="circle"
                                :image-placeholder="$initial"
                            >
                                <x-slot:badges>
                                    @if ($member->isTeamLeader())
                                        <span class="gh-record-card__badge gh-record-card__badge--muted">{{ __('employees.roles.TEAM_LEADER') }}</span>
                                    @endif
                                    <span @class([
                                        'gh-record-card__badge',
                                        'gh-record-card__badge--status' => $row['saved'],
                                        'gh-record-card__badge--muted' => ! $row['saved'],
                                    ])>
                                        {{ $row['saved'] ? __('evaluation.tl.saved') : __('evaluation.tl.draft') }}
                                    </span>
                                </x-slot:badges>
                                <x-slot:meta>
                                    <x-gh.meta-cell icon="bi-calendar3" :value="$periodLabel" />
                                    <x-gh.meta-cell icon="bi-percent" :value="$scoreLabel" :ltr="true" />
                                </x-slot:meta>
                                <x-slot:actions>
                                    <a
                                        href="{{ route('team-leader.evaluations.show', ['employee' => $member, 'month' => $month, 'year' => $year]) }}"
                                        class="gh-board-btn"
                                    >
                                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
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
