@extends('layouts.app')

@section('title', __('evaluation.tl.detail_title'))

@section('content')
    @php
        $name = app()->getLocale() === 'ar' ? $employee->name_ar : $employee->name_en;
        $monthLabel = \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y');
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('evaluation.tl.detail_title') }}</h1>
            <p class="text-body-secondary mb-0">
                {{ $name }} · {{ $monthLabel }}
            </p>
        </div>
        <a href="{{ route('team-leader.evaluations', ['month' => $month, 'year' => $year]) }}"
           class="btn btn-outline-secondary btn-sm">
            {{ __('evaluation.tl.back') }}
        </a>
    </div>

    <div class="eval-card-ui" data-eval-card-ui="tl-show">
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <article class="gh-record-card">
                    <header class="gh-record-card__head">
                        <h2 class="gh-record-card__title">{{ __('evaluation.tl.averages_title') }}</h2>
                        <div class="gh-record-card__badges">
                            <span class="gh-record-card__badge gh-record-card__badge--status">
                                {{ number_format((float) $scores['total_score'], 2) }}%
                            </span>
                        </div>
                    </header>
                    <p class="small mb-2" style="color: var(--gh-board-muted)">{{ __('evaluation.tl.averages_hint') }}</p>
                    <x-score-bar :label="__('evaluation.fields.performance')" :value="$scores['performance_score']" />
                    <x-score-bar :label="__('evaluation.fields.behavior')" :value="$scores['behavior_score']" />
                    <x-score-bar :label="__('evaluation.fields.productivity')" :value="$scores['productivity_score']" />
                    <x-score-bar :label="__('evaluation.fields.interest_return')" :value="$scores['interest_return_score']" />
                    <x-score-bar :label="__('evaluation.fields.attendance')" :value="$scores['attendance_score']"
                                 :hint="__('evaluation.attendance_hint')" />
                    <hr>
                    <x-score-bar :label="__('evaluation.fields.total')" :value="$scores['total_score']"
                                 :hint="__('evaluation.tl.total_hint')" />
                </article>

                @foreach ($otherEvaluations ?? [] as $other)
                    <article class="gh-record-card mt-3">
                        <header class="gh-record-card__head">
                            <h2 class="gh-record-card__title">{{ __('evaluation.tl.other_title') }}</h2>
                        </header>
                        <div class="gh-record-card__meta">
                            <x-gh.meta-cell icon="bi-person-badge" :value="$other->teamLeader?->localizedName() ?? '—'" />
                            <x-gh.meta-cell
                                icon="bi-percent"
                                :value="number_format((float) $other->total_score, 2).'%'"
                                :ltr="true"
                            />
                        </div>
                        <x-score-bar :label="__('evaluation.fields.performance')" :value="$other->performance_score" />
                        <x-score-bar :label="__('evaluation.fields.behavior')" :value="$other->behavior_score" />
                        <x-score-bar :label="__('evaluation.fields.productivity')" :value="$other->productivity_score" />
                        <x-score-bar :label="__('evaluation.fields.interest_return')" :value="$other->interest_return_score" />
                        <x-score-bar :label="__('evaluation.fields.attendance')" :value="$other->attendance_score" />
                        <hr>
                        <x-score-bar :label="__('evaluation.fields.total')" :value="$other->total_score" />
                        @if (filled($other->evaluation_text))
                            <div class="border rounded p-3 bg-body-tertiary mt-3">{{ $other->evaluation_text }}</div>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="col-12 col-lg-6">
                <article class="gh-record-card">
                    <header class="gh-record-card__head">
                        <h2 class="gh-record-card__title">{{ __('evaluation.tl.text_title') }}</h2>
                    </header>
                    <p class="small mb-3" style="color: var(--gh-board-muted)">{{ __('evaluation.tl.text_hint') }}</p>

                    @if ($saved)
                        <div class="alert alert-success py-2">{{ __('evaluation.tl.already_saved') }}</div>
                    @endif

                    <form method="POST" action="{{ route('team-leader.evaluations.store', $employee) }}">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month }}">
                        <input type="hidden" name="year" value="{{ $year }}">

                        <div class="mb-3">
                            <label for="evaluation_text" class="form-label">{{ __('evaluation.tl.text_label') }}</label>
                            <textarea id="evaluation_text" name="evaluation_text" rows="8"
                                      class="form-control @error('evaluation_text') is-invalid @enderror"
                                      maxlength="5000">{{ $evaluationText }}</textarea>
                            @error('evaluation_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="gh-board-btn w-100 justify-content-center">
                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                            <span>{{ __('evaluation.tl.save') }}</span>
                        </button>
                    </form>
                </article>
            </div>
        </div>
    </div>
@endsection
