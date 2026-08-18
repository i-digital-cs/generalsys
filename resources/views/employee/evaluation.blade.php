@extends('layouts.app')

@section('title', __('evaluation.me.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('evaluation.me.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('evaluation.me.subtitle') }}</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
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

            @if ($evaluations->isEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <x-empty-state
                            :title="__('evaluation.me.empty_title')"
                            :text="__('evaluation.me.empty_text')"
                            icon="bi-star"
                        />
                    </div>
                </div>
            @else
                @php
                    $monthLabel = \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y');
                @endphp
                @foreach ($evaluations as $evaluation)
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-transparent">
                            <h2 class="h6 mb-0">{{ $monthLabel }}</h2>
                            <p class="small text-body-secondary mb-0 mt-1">
                                {{ __('evaluation.me.by_leader', ['name' => $evaluation->teamLeader?->localizedName() ?? '—']) }}
                                · {{ __('evaluation.me.read_only_hint') }}
                            </p>
                        </div>
                        <div class="card-body">
                            <x-score-bar :label="__('evaluation.fields.performance')" :value="$evaluation->performance_score" />
                            <x-score-bar :label="__('evaluation.fields.behavior')" :value="$evaluation->behavior_score" />
                            <x-score-bar :label="__('evaluation.fields.productivity')" :value="$evaluation->productivity_score" />
                            <x-score-bar :label="__('evaluation.fields.interest_return')" :value="$evaluation->interest_return_score" />
                            <x-score-bar :label="__('evaluation.fields.attendance')" :value="$evaluation->attendance_score" />
                            <hr>
                            <x-score-bar :label="__('evaluation.fields.total')" :value="$evaluation->total_score"
                                         :hint="__('evaluation.tl.total_hint')" />

                            <h2 class="h6 mt-4">{{ __('evaluation.me.text_title') }}</h2>
                            @if (filled($evaluation->evaluation_text))
                                <div class="border rounded p-3 bg-body-tertiary">{{ $evaluation->evaluation_text }}</div>
                            @else
                                <p class="text-body-secondary mb-0">{{ __('evaluation.me.text_empty') }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
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
                            <div class="table-responsive">
                                <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('evaluation.tl.month') }}</th>
                                            <th scope="col">{{ __('evaluation.me.leader') }}</th>
                                            <th scope="col" class="col-num">{{ __('evaluation.fields.total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($history as $row)
                                            @php
                                                $active = (int) $row->month === (int) $month && (int) $row->year === (int) $year;
                                                $label = \Carbon\Carbon::create($row->year, $row->month, 1)->translatedFormat('F Y');
                                                $leaderName = $row->teamLeader?->localizedName() ?? '—';
                                            @endphp
                                            <tr @class(['table-active' => $active])
                                                data-filter-text="{{ $label }} {{ $leaderName }} {{ $row->month }} {{ $row->year }}">
                                                <td>
                                                    <a href="{{ route('employee.evaluation', ['month' => $row->month, 'year' => $row->year]) }}"
                                                       class="fw-semibold text-decoration-none">
                                                        {{ $label }}
                                                    </a>
                                                </td>
                                                <td>{{ $leaderName }}</td>
                                                <td class="col-num">
                                                    <span class="num-ltr">{{ number_format((float) $row->total_score, 2) }}%</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr data-table-empty-row class="d-none">
                                            <td colspan="3" class="text-body-secondary text-center py-3">
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
        </div>
    </div>
@endsection
