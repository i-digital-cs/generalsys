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
        $clockInPhotos = $open?->photos?->where('kind', \App\Models\AttendancePhoto::KIND_CLOCK_IN) ?? collect();
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

                @if ($clockInPhotos->isNotEmpty())
                    <div class="mb-4">
                        <x-attendance-photos :photos="$clockInPhotos" kind="CLOCK_IN" />
                    </div>
                @endif

                <div class="mb-4">
                    <x-attendance-location :attendance="$open" kind="CLOCK_IN" />
                </div>

                <div class="mb-4">
                    <x-attendance-flags :attendance="$open" class="mb-0 justify-content-center" />
                </div>

                <form method="POST" action="{{ route('employee.clock.out') }}" enctype="multipart/form-data" data-clock-form>
                    @csrf
                    @error('clock_out')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    @include('partials.clock-location-input', ['id' => 'clock-out-gps'])
                    @include('partials.clock-photos-input', ['id' => 'clock-out-photos'])
                    <button type="submit" class="btn btn-danger btn-lg w-100 clock-cta" data-clock-submit>
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

                <form method="POST" action="{{ route('employee.clock.in') }}" class="mb-3" enctype="multipart/form-data" data-clock-form>
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

                        @include('partials.clock-location-input', ['id' => 'clock-in-gps'])
                        @include('partials.clock-photos-input', ['id' => 'clock-in-photos'])

                        <button type="submit" class="btn btn-primary btn-lg w-100 clock-cta" data-clock-submit>
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
    if (el && el.dataset.clockIn) {
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
    }

    const msgs = {
        unsupported: @json(__('clock.location.unsupported')),
        denied: @json(__('clock.location.denied')),
        unavailable: @json(__('clock.location.unavailable')),
        timeout: @json(__('clock.location.timeout')),
        locating: @json(__('clock.location.locating')),
    };

    document.querySelectorAll('[data-clock-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const lat = form.querySelector('[data-clock-lat]');
            const lng = form.querySelector('[data-clock-lng]');
            const acc = form.querySelector('[data-clock-acc]');
            const err = form.querySelector('[data-clock-loc-err]');
            const ok = form.querySelector('[data-clock-loc-ok]');
            const btn = form.querySelector('[data-clock-submit]');

            if (!lat || !lng) return;

            if (lat.value !== '' && lng.value !== '') {
                return;
            }

            event.preventDefault();

            if (!navigator.geolocation) {
                if (err) {
                    err.textContent = msgs.unsupported;
                    err.classList.remove('d-none');
                }
                return;
            }

            if (err) {
                err.classList.add('d-none');
                err.textContent = '';
            }
            if (ok) {
                ok.classList.add('d-none');
            }
            if (btn) {
                btn.disabled = true;
                btn.dataset.originalLabel = btn.innerHTML;
                btn.innerHTML = msgs.locating;
            }

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    lat.value = String(pos.coords.latitude);
                    lng.value = String(pos.coords.longitude);
                    if (acc) {
                        acc.value = pos.coords.accuracy != null ? String(pos.coords.accuracy) : '';
                    }
                    if (ok) {
                        ok.classList.remove('d-none');
                    }
                    if (btn) {
                        btn.disabled = false;
                        if (btn.dataset.originalLabel) {
                            btn.innerHTML = btn.dataset.originalLabel;
                        }
                    }
                    form.submit();
                },
                function (error) {
                    let message = msgs.unavailable;
                    if (error && error.code === 1) message = msgs.denied;
                    if (error && error.code === 3) message = msgs.timeout;
                    if (err) {
                        err.textContent = message;
                        err.classList.remove('d-none');
                    }
                    if (btn) {
                        btn.disabled = false;
                        if (btn.dataset.originalLabel) {
                            btn.innerHTML = btn.dataset.originalLabel;
                        }
                    }
                },
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
            );
        });
    });
})();
</script>
@endpush
