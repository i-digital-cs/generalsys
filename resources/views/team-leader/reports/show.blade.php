@extends('layouts.app')

@section('title', __('report.tl.detail_title'))

@section('content')
    @php
        $name = app()->getLocale() === 'ar' ? $attendance->user->name_ar : $attendance->user->name_en;
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('report.tl.detail_title') }}</h1>
            <p class="text-body-secondary mb-0">
                {{ $name }} · <span dir="ltr">{{ $attendance->date->toDateString() }}</span>
            </p>
        </div>
        <a href="{{ route('team-leader.reports', ['filter' => $isLocked ? 'replied' : 'pending']) }}"
           class="btn btn-outline-secondary btn-sm">
            {{ __('report.tl.back') }}
        </a>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="mb-3 small text-body-secondary">
                        {{ __('report.main_shift') }}:
                        <strong>{{ __('clock.shifts.'.$attendance->shift_type) }}</strong>
                        <span dir="ltr">
                            ({{ $attendance->clock_in?->format('H:i') }} → {{ $attendance->clock_out?->format('H:i') }})
                        </span>
                    </div>

                    @if ($attendance->photos->isNotEmpty())
                        <div class="mb-4">
                            <x-attendance-photos :photos="$attendance->photos" kind="CLOCK_IN" class="mb-3" />
                            <x-attendance-photos :photos="$attendance->photos" kind="CLOCK_OUT" />
                        </div>
                    @endif

                    <h2 class="h6">{{ __('report.body') }}</h2>
                    <div class="border rounded p-3 bg-body-tertiary mb-4">{{ $attendance->daily_report }}</div>

                    <h2 class="h6">{{ __('report.tl.response_label') }}</h2>
                    <form method="POST" action="{{ route('team-leader.reports.respond', $attendance) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="team_leader_response" class="form-label visually-hidden">
                                {{ __('report.tl.response_label') }}
                            </label>
                            <textarea id="team_leader_response" name="team_leader_response" rows="6"
                                      class="form-control @error('team_leader_response') is-invalid @enderror"
                                      required maxlength="5000">{{ old('team_leader_response', $attendance->team_leader_response) }}</textarea>
                            @error('team_leader_response')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">{{ __('report.tl.response_hint') }}</div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            {{ $isLocked ? __('report.tl.update_reply') : __('report.tl.send_reply') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <h2 class="h6 mb-0">{{ __('evaluation.scores_title') }}</h2>
                    <p class="small text-body-secondary mb-0 mt-1">{{ __('evaluation.scores_subtitle') }}</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('team-leader.reports.scores', $attendance) }}">
                        @csrf
                        @method('PUT')

                        @foreach ([
                            'performance_score' => 'performance',
                            'behavior_score' => 'behavior',
                            'productivity_score' => 'productivity',
                            'interest_return_score' => 'interest_return',
                        ] as $field => $labelKey)
                            <div class="mb-3">
                                <label for="{{ $field }}" class="form-label">{{ __('evaluation.fields.'.$labelKey) }}</label>
                                <input type="number" step="0.01" min="0" max="100" id="{{ $field }}" name="{{ $field }}"
                                       value="{{ old($field, $attendance->{$field}) }}"
                                       class="form-control @error($field) is-invalid @enderror"
                                       required dir="ltr">
                                @error($field)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach

                        <div class="mb-3">
                            <label class="form-label">{{ __('evaluation.fields.attendance') }}</label>
                            <input type="text" class="form-control" dir="ltr" disabled
                                   value="{{ $attendance->attendance_score !== null ? number_format((float) $attendance->attendance_score, 2) : '—' }}">
                            <div class="form-text">{{ __('evaluation.attendance_hint') }}</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            {{ __('evaluation.save_scores') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
