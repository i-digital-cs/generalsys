@extends('layouts.app')

@section('title', __('clock.title'))

@section('content')
    @php
        $tz = config('app.timezone');
        $startLabel = $expectedStart
            ? $expectedStart->timezone($tz)->format('H:i')
            : null;
        $endLabel = $expectedEnd
            ? $expectedEnd->timezone($tz)->format('H:i')
            : null;
    @endphp

    <h1 class="h4 mb-3">{{ __('clock.title') }}</h1>

    @if ($missingReportDate)
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span>
                <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                {{ __('clock.report_missing', ['date' => $missingReportDate]) }}
            </span>
            <a href="{{ route('employee.report', ['date' => $missingReportDate]) }}" class="btn btn-sm btn-warning">
                {{ __('clock.report_write') }}
            </a>
        </div>
    @endif

    @if ($todayAutoClockOut)
        <div class="alert alert-secondary d-flex flex-wrap align-items-center gap-2">
            <span class="badge text-bg-dark">{{ __('clock.flags.auto_clock_out') }}</span>
            <span>
                {{ __('clock.auto_closed_notice', [
                    'time' => $todayAutoClockOut->clock_out?->timezone($tz)->format('H:i'),
                    'shift' => __('clock.shifts.'.$todayAutoClockOut->shift_type),
                ]) }}
            </span>
        </div>
    @endif

    <div class="card border-0 shadow-sm employee-mobile-stack">
        <div class="card-body p-3 p-md-4">
            @if ($open)
                <div class="text-center mb-4">
                    <span class="badge text-bg-success fs-6 px-3 py-2">{{ __('clock.status_open') }}</span>
                </div>

                @if ($startLabel || $lateMinutes > 0)
                    <div @class([
                        'alert mb-4',
                        'alert-warning' => $lateMinutes > 0,
                        'alert-success' => $lateMinutes === 0,
                    ])>
                        @if ($startLabel)
                            <div class="fw-semibold">{{ __('clock.should_start_at', ['time' => $startLabel]) }}</div>
                        @endif
                        @if ($lateMinutes > 0)
                            <div class="mt-1">{{ __('clock.late_by', ['minutes' => $lateMinutes]) }}</div>
                        @else
                            <div class="mt-1">{{ __('clock.on_time') }}</div>
                        @endif
                    </div>
                @endif

                <dl class="row mb-4">
                    <dt class="col-sm-4">{{ __('clock.shift_type') }}</dt>
                    <dd class="col-sm-8">{{ __('clock.shifts.'.$open->shift_type) }}</dd>
                    <dt class="col-sm-4">{{ __('clock.clocked_at') }}</dt>
                    <dd class="col-sm-8" dir="ltr">{{ $open->clock_in?->timezone($tz)->format('Y-m-d H:i') }}</dd>
                    @if ($startLabel)
                        <dt class="col-sm-4">{{ __('clock.expected_start') }}</dt>
                        <dd class="col-sm-8" dir="ltr">{{ $startLabel }}</dd>
                    @endif
                    <dt class="col-sm-4">{{ __('clock.late_minutes') }}</dt>
                    <dd class="col-sm-8">
                        @if ($lateMinutes > 0)
                            <span class="badge text-bg-warning">{{ __('clock.late_by', ['minutes' => $lateMinutes]) }}</span>
                        @else
                            <span class="badge text-bg-success">{{ __('clock.on_time') }}</span>
                        @endif
                    </dd>
                    <dt class="col-sm-4">{{ __('clock.planned_hours') }}</dt>
                    <dd class="col-sm-8" dir="ltr">{{ $open->planned_hours }}</dd>
                    <dt class="col-sm-4">{{ __('clock.elapsed') }}</dt>
                    <dd class="col-sm-8 font-monospace fs-4" dir="ltr" id="clock-elapsed"
                        data-clock-in="{{ $open->clock_in?->toIso8601String() }}">--:--:--</dd>
                </dl>

                <div class="mb-4">
                    <x-attendance-flags :attendance="$open" class="mb-0 justify-content-center" />
                </div>

                <form method="POST" action="{{ route('employee.clock.out') }}">
                    @csrf
                    @error('clock_out')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    <button type="submit" class="btn btn-danger btn-lg w-100 clock-cta">
                        <i class="bi bi-box-arrow-left me-2" aria-hidden="true"></i>{{ __('clock.clock_out') }}
                    </button>
                </form>
            @else
                <div class="text-center mb-4">
                    <span class="badge text-bg-secondary fs-6 px-3 py-2">{{ __('clock.status_closed') }}</span>
                </div>

                @if ($startLabel || $endLabel)
                    <div class="alert alert-info mb-4">
                        @if ($startLabel)
                            <div class="fw-semibold">{{ __('clock.should_start_at', ['time' => $startLabel]) }}</div>
                        @endif
                        @if ($endLabel)
                            <div class="small mt-1">{{ __('clock.should_end_at', ['time' => $endLabel]) }}</div>
                        @endif
                        @if ($previewLateMinutes > 0)
                            <div class="mt-2 text-warning-emphasis">
                                {{ __('clock.preview_late', ['minutes' => $previewLateMinutes]) }}
                            </div>
                        @elseif ($startLabel)
                            <div class="mt-2 small text-success">{{ __('clock.preview_on_time') }}</div>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('employee.clock.in') }}" class="mb-3">
                    @csrf
                    @if ($todaysAssignment)
                        <div class="alert alert-info py-2">
                            {{ __('clock.assigned_today', ['shift' => __('clock.shifts.'.$todaysAssignment->shift_type)]) }}
                        </div>
                    @endif
                    @if ($shiftTypes === [])
                        <div class="alert alert-warning">{{ __('clock.no_shifts_left') }}</div>
                    @else
                        <div class="mb-3">
                            <label for="shift_type" class="form-label">{{ __('clock.shift_type') }}</label>
                            <select id="shift_type" name="shift_type"
                                    class="form-select form-select-lg @error('shift_type') is-invalid @enderror" required>
                                @foreach ($shiftTypes as $type)
                                    <option value="{{ $type }}" @selected(old('shift_type', $preferredShift) === $type)>
                                        {{ __('clock.shifts.'.$type) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('shift_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">{{ __('clock.rotation_hint') }}</div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 clock-cta">
                            <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>{{ __('clock.clock_in') }}
                        </button>
                    @endif
                </form>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const el = document.getElementById('clock-elapsed');
    if (!el || !el.dataset.clockIn) return;

    const start = Date.parse(el.dataset.clockIn);

    function tick() {
        const secs = Math.max(0, Math.floor((Date.now() - start) / 1000));
        const h = String(Math.floor(secs / 3600)).padStart(2, '0');
        const m = String(Math.floor((secs % 3600) / 60)).padStart(2, '0');
        const s = String(secs % 60).padStart(2, '0');
        el.textContent = h + ':' + m + ':' + s;
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
@endpush
