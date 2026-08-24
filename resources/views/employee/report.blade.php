@extends('layouts.app')

@section('title', __('report.title'))

@section('content')
    @php
        $isToday = $date === now()->toDateString();
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('report.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('report.subtitle') }}</p>
        </div>
        @unless ($isToday)
            <a href="{{ route('employee.report') }}" class="btn btn-outline-primary btn-sm">
                {{ __('report.back_to_today') }}
            </a>
        @endunless
    </div>

    <x-report-reminder-banner />

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="fw-semibold">
                        {{ $isToday ? __('report.today') : __('report.day_heading') }}
                    </span>
                    <span class="text-body-secondary" dir="ltr">{{ $date }}</span>
                </div>
                <div class="card-body">
                    @if ($main === null)
                        <x-empty-state
                            :title="__('report.no_attendance_title')"
                            :text="__('report.no_attendance_text')"
                            icon="bi-journal-x"
                        />
                    @elseif ($main->clock_out === null)
                        <div class="alert alert-warning mb-0">
                            {{ __('report.errors.clock_out_required') }}
                        </div>
                    @else
                        <div class="mb-3 small text-body-secondary">
                            {{ __('report.main_shift') }}:
                            <strong>{{ __('clock.shifts.'.$main->shift_type) }}</strong>
                            <span dir="ltr">
                                ({{ $main->clock_in?->format('H:i') }} → {{ $main->clock_out?->format('H:i') }})
                            </span>
                        </div>

                        @if ($main->photos->isNotEmpty())
                            <div class="mb-4">
                                <x-attendance-photos :photos="$main->photos" kind="CLOCK_IN" class="mb-3" />
                                <x-attendance-photos :photos="$main->photos" kind="CLOCK_OUT" />
                                <x-attendance-location :attendance="$main" class="mt-3" />
                            </div>
                        @endif

                        <x-attendance-time-breakdown :attendance="$main" class="mb-4" />

                        @if ($isLocked)
                            <div class="alert alert-info">{{ __('report.errors.locked') }}</div>
                            <div class="mb-3">
                                <div class="form-label">{{ __('report.body') }}</div>
                                <div class="border rounded p-3 bg-body-tertiary">{{ $main->daily_report }}</div>
                            </div>
                        @elseif ($canSubmit)
                            @php
                                $reminder = session('report_reminder');
                                $late = max(0, (int) ($reminder['late_minutes'] ?? 0));
                                $early = max(0, (int) ($reminder['early_minutes'] ?? 0));
                                $reportPlaceholder = match (true) {
                                    $late > 0 && $early > 0 => __('report.reminder.placeholder_both'),
                                    $late > 0 => __('report.reminder.placeholder_late'),
                                    $early > 0 => __('report.reminder.placeholder_early'),
                                    default => null,
                                };
                            @endphp
                            <form method="POST" action="{{ route('employee.report.store') }}">
                                @csrf
                                <input type="hidden" name="date" value="{{ $date }}">
                                <div class="mb-3">
                                    <label for="daily_report" class="form-label">{{ __('report.body') }}</label>
                                    <textarea id="daily_report" name="daily_report" rows="8"
                                              class="form-control @error('daily_report') is-invalid @enderror"
                                              required maxlength="5000"
                                              @if ($reportPlaceholder) placeholder="{{ $reportPlaceholder }}" @endif>{{ old('daily_report', $main->daily_report) }}</textarea>
                                    @error('daily_report')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">{{ __('report.hint') }}</div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    {{ __('report.save') }}
                                </button>
                            </form>
                        @endif

                        <hr class="my-4">

                        <h2 class="h6">{{ __('report.tl_response') }}</h2>
                        @if (filled($main->team_leader_response))
                            <div class="border rounded p-3 bg-body-tertiary">{{ $main->team_leader_response }}</div>
                        @else
                            <p class="text-body-secondary mb-0">{{ __('report.tl_response_empty') }}</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('report.history') }}</h2>
                    <p class="small text-body-secondary mb-3">{{ __('report.history_hint') }}</p>
                    @if ($recent->isEmpty())
                        <x-empty-state
                            :title="__('report.history_empty')"
                            :text="__('report.history_empty_text')"
                            icon="bi-clock-history"
                        />
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($recent as $row)
                                @php
                                    $rowDate = $row->date->toDateString();
                                    $active = $rowDate === $date;
                                @endphp
                                <li class="list-group-item px-0 {{ $active ? 'bg-body-tertiary rounded' : '' }}">
                                    <a href="{{ route('employee.report', ['date' => $rowDate]) }}"
                                       class="text-decoration-none d-flex justify-content-between gap-2 px-2 py-1">
                                        <span>
                                            <span class="fw-semibold">{{ $row->date->translatedFormat('D d M Y') }}</span>
                                            <span class="d-block small text-body-secondary">
                                                {{ filled($row->daily_report) ? __('report.status.submitted') : __('report.status.missing') }}
                                            </span>
                                        </span>
                                        @if (filled($row->team_leader_response))
                                            <span class="badge text-bg-success align-self-start">{{ __('report.status.replied') }}</span>
                                        @elseif (filled($row->daily_report))
                                            <span class="badge text-bg-warning text-dark align-self-start">{{ __('report.status.waiting') }}</span>
                                        @else
                                            <span class="badge text-bg-secondary align-self-start">{{ __('report.status.open') }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
