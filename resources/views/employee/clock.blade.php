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
        $scheduleShift = $scheduleShift ?? ($open?->shift_type ?? $preferredShift ?? null);
        $shiftLabel = $scheduleShift ? __('clock.shifts.'.$scheduleShift) : '';
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
                            <div class="fw-semibold">{{ __('clock.should_start_at', ['shift' => $shiftLabel, 'time' => $startLabel]) }}</div>
                        @endif
                        @if ($lateMinutes > 0)
                            <div class="mt-1">{{ __('clock.late_by', ['minutes' => $lateMinutes, 'shift' => $shiftLabel]) }}</div>
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
                            <span class="badge text-bg-warning">{{ __('clock.late_by', ['minutes' => $lateMinutes, 'shift' => $shiftLabel]) }}</span>
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

                @if ($shiftTypes !== [] && ($startLabel || $endLabel))
                    <div class="alert alert-info mb-4">
                        @if ($startLabel)
                            <div class="fw-semibold">{{ __('clock.should_start_at', ['shift' => $shiftLabel, 'time' => $startLabel]) }}</div>
                        @endif
                        @if ($endLabel)
                            <div class="small mt-1">{{ __('clock.should_end_at', ['shift' => $shiftLabel, 'time' => $endLabel]) }}</div>
                        @endif
                        @if ($previewLateMinutes > 0)
                            <div class="mt-2 text-warning-emphasis">
                                {{ __('clock.preview_late', ['minutes' => $previewLateMinutes, 'shift' => $shiftLabel]) }}
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
                        @if ($upcomingShiftTypes !== [])
                            <div class="alert alert-info">
                                {{ __('clock.upcoming_wait', [
                                    'shift' => $shiftLabel,
                                    'start' => $startLabel ?? '—',
                                    'end' => $endLabel ?? '—',
                                ]) }}
                            </div>
                        @elseif ($closedShiftTypes !== [])
                            <div class="alert alert-warning">
                                {{ __('clock.windows_closed', [
                                    'shifts' => collect($closedShiftTypes)
                                        ->map(fn (string $type) => __('clock.shifts.'.$type))
                                        ->implode(' · '),
                                ]) }}
                            </div>
                        @else
                            <div class="alert alert-warning">{{ __('clock.no_shifts_left') }}</div>
                        @endif
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
    @vite(['resources/js/pages/clock-photos.js'])
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
        checking: @json(__('clock.location.checking')),
        needEnable: @json(__('clock.location.need_enable')),
        ready: @json(__('clock.location.ready')),
        readyLabel: @json(__('clock.location.ready_label')),
        accuracyLabel: @json(__('clock.location.accuracy_label')),
        resolvingAddress: @json(__('clock.location.resolving_address')),
        addressUnavailable: @json(__('clock.location.address_unavailable')),
        statusUnknown: @json(__('clock.location.status_unknown')),
        statusPrompt: @json(__('clock.location.status_prompt')),
        statusGranted: @json(__('clock.location.status_granted')),
        statusDenied: @json(__('clock.location.status_denied')),
        statusReady: @json(__('clock.location.status_ready')),
        statusUnsupported: @json(__('clock.location.status_unsupported')),
    };

    const geoOpts = { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 };

    function setStatus(box, label, tone) {
        const badge = box.querySelector('[data-clock-loc-status]');
        if (!badge) return;
        badge.textContent = label;
        badge.className = 'badge ' + ({
            secondary: 'text-bg-secondary',
            warning: 'text-bg-warning text-dark',
            success: 'text-bg-success',
            danger: 'text-bg-danger',
            info: 'text-bg-info text-dark',
        }[tone] || 'text-bg-secondary');
    }

    function hideReady(box) {
        const ok = box.querySelector('[data-clock-loc-ok]');
        if (ok) ok.classList.add('d-none');
    }

    function showErr(box, message, showHelp) {
        const err = box.querySelector('[data-clock-loc-err]');
        const help = box.querySelector('[data-clock-loc-help]');
        hideReady(box);
        if (err) {
            err.textContent = message || '';
            err.classList.toggle('d-none', !message);
        }
        if (help) help.classList.toggle('d-none', !showHelp);
    }

    function formatCoords(lat, lng) {
        return Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5);
    }

    function mapsUrl(lat, lng) {
        return 'https://www.google.com/maps?q=' + encodeURIComponent(Number(lat).toFixed(7) + ',' + Number(lng).toFixed(7));
    }

    function showReady(box, lat, lng, accuracy) {
        const ok = box.querySelector('[data-clock-loc-ok]');
        const err = box.querySelector('[data-clock-loc-err]');
        const help = box.querySelector('[data-clock-loc-help]');
        const label = box.querySelector('[data-clock-loc-ready-label]');
        const addressEl = box.querySelector('[data-clock-loc-address]');
        const coordsEl = box.querySelector('[data-clock-loc-coords]');
        const accuracyEl = box.querySelector('[data-clock-loc-accuracy]');
        const mapsEl = box.querySelector('[data-clock-loc-maps]');

        if (err) err.classList.add('d-none');
        if (help) help.classList.add('d-none');
        if (!ok) return;

        if (label) label.textContent = msgs.readyLabel;
        if (coordsEl) coordsEl.textContent = formatCoords(lat, lng);
        if (accuracyEl) {
            const meters = accuracy != null && accuracy !== '' ? Math.round(Number(accuracy)) : null;
            if (meters != null && !Number.isNaN(meters)) {
                accuracyEl.textContent = msgs.accuracyLabel.replace(':m', String(meters));
                accuracyEl.classList.remove('d-none');
            } else {
                accuracyEl.classList.add('d-none');
            }
        }
        if (mapsEl) {
            mapsEl.href = mapsUrl(lat, lng);
            mapsEl.classList.remove('d-none');
        }
        if (addressEl) {
            addressEl.textContent = msgs.resolvingAddress;
            addressEl.classList.remove('d-none');
        }

        ok.classList.remove('d-none');
        setStatus(box, msgs.statusReady, 'success');
        resolveAddress(box, lat, lng);
    }

    function resolveAddress(box, lat, lng) {
        const addressEl = box.querySelector('[data-clock-loc-address]');
        if (!addressEl) return;

        const locale = (box.dataset.locale || 'ar').split('-')[0] || 'ar';
        const url = 'https://api.bigdatacloud.net/data/reverse-geocode-client'
            + '?latitude=' + encodeURIComponent(lat)
            + '&longitude=' + encodeURIComponent(lng)
            + '&localityLanguage=' + encodeURIComponent(locale);

        fetch(url)
            .then(function (res) { return res.ok ? res.json() : Promise.reject(); })
            .then(function (data) {
                const parts = [
                    data.locality || data.city || data.localityInfo?.administrative?.[0]?.name,
                    data.principalSubdivision || data.principalSubdivisionCode,
                    data.countryName,
                ].filter(Boolean);
                const line = (data.formatted || parts.join(', ') || '').trim();
                if (line) {
                    addressEl.textContent = line;
                } else {
                    addressEl.textContent = msgs.addressUnavailable;
                }
            })
            .catch(function () {
                addressEl.textContent = msgs.addressUnavailable;
            });
    }

    function clearCoords(box) {
        const lat = box.querySelector('[data-clock-lat]');
        const lng = box.querySelector('[data-clock-lng]');
        const acc = box.querySelector('[data-clock-acc]');
        if (lat) lat.value = '';
        if (lng) lng.value = '';
        if (acc) acc.value = '';
        hideReady(box);
    }

    function applyCoords(box, pos) {
        const lat = box.querySelector('[data-clock-lat]');
        const lng = box.querySelector('[data-clock-lng]');
        const acc = box.querySelector('[data-clock-acc]');
        if (lat) lat.value = String(pos.coords.latitude);
        if (lng) lng.value = String(pos.coords.longitude);
        if (acc) {
            acc.value = pos.coords.accuracy != null ? String(pos.coords.accuracy) : '';
        }
        showReady(box, pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
    }

    function errorMessage(error) {
        if (error && error.code === 1) return msgs.denied;
        if (error && error.code === 3) return msgs.timeout;
        return msgs.unavailable;
    }

    function requestPosition(box, onSuccess, onFail) {
        if (!navigator.geolocation) {
            setStatus(box, msgs.statusUnsupported, 'danger');
            showErr(box, msgs.unsupported, false);
            if (onFail) onFail();
            return;
        }

        const enableBtn = box.querySelector('[data-clock-loc-enable]');
        const checkBtn = box.querySelector('[data-clock-loc-check]');
        if (enableBtn) enableBtn.disabled = true;
        if (checkBtn) checkBtn.disabled = true;
        setStatus(box, msgs.locating, 'info');
        showErr(box, '', false);

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                applyCoords(box, pos);
                if (enableBtn) enableBtn.disabled = false;
                if (checkBtn) checkBtn.disabled = false;
                if (onSuccess) onSuccess(pos);
            },
            function (error) {
                clearCoords(box);
                const denied = error && error.code === 1;
                setStatus(box, denied ? msgs.statusDenied : msgs.statusPrompt, denied ? 'danger' : 'warning');
                showErr(box, errorMessage(error), denied);
                if (enableBtn) enableBtn.disabled = false;
                if (checkBtn) checkBtn.disabled = false;
                if (onFail) onFail(error);
            },
            geoOpts
        );
    }

    function checkPermission(box) {
        if (!navigator.geolocation) {
            setStatus(box, msgs.statusUnsupported, 'danger');
            showErr(box, msgs.unsupported, false);
            return;
        }

        const checkBtn = box.querySelector('[data-clock-loc-check]');
        if (checkBtn) {
            checkBtn.disabled = true;
            checkBtn.dataset.originalLabel = checkBtn.innerHTML;
            checkBtn.innerHTML = msgs.checking;
        }

        const finish = function () {
            if (checkBtn) {
                checkBtn.disabled = false;
                if (checkBtn.dataset.originalLabel) {
                    checkBtn.innerHTML = checkBtn.dataset.originalLabel;
                }
            }
        };

        if (navigator.permissions && navigator.permissions.query) {
            navigator.permissions.query({ name: 'geolocation' }).then(function (result) {
                if (result.state === 'granted') {
                    setStatus(box, msgs.statusGranted, 'success');
                    showErr(box, '', false);
                    // Confirm device can actually return a fix.
                    requestPosition(box, finish, finish);
                    return;
                }
                if (result.state === 'denied') {
                    clearCoords(box);
                    setStatus(box, msgs.statusDenied, 'danger');
                    showErr(box, msgs.denied, true);
                    finish();
                    return;
                }
                clearCoords(box);
                setStatus(box, msgs.statusPrompt, 'warning');
                showErr(box, msgs.needEnable, false);
                finish();
            }).catch(function () {
                // Permissions API unavailable / blocked — fall back to a live request.
                requestPosition(box, finish, finish);
            });
            return;
        }

        requestPosition(box, finish, finish);
    }

    document.querySelectorAll('[data-clock-location]').forEach(function (box) {
        const checkBtn = box.querySelector('[data-clock-loc-check]');
        const enableBtn = box.querySelector('[data-clock-loc-enable]');

        if (checkBtn) {
            checkBtn.addEventListener('click', function () {
                checkPermission(box);
            });
        }
        if (enableBtn) {
            enableBtn.addEventListener('click', function () {
                requestPosition(box);
            });
        }

        // Restore old() values after validation errors.
        const lat = box.querySelector('[data-clock-lat]');
        const lng = box.querySelector('[data-clock-lng]');
        if (lat && lng && lat.value !== '' && lng.value !== '') {
            const acc = box.querySelector('[data-clock-acc]');
            showReady(box, lat.value, lng.value, acc ? acc.value : null);
        } else if (navigator.permissions && navigator.permissions.query) {
            navigator.permissions.query({ name: 'geolocation' }).then(function (result) {
                if (result.state === 'granted') setStatus(box, msgs.statusGranted, 'success');
                else if (result.state === 'denied') setStatus(box, msgs.statusDenied, 'danger');
                else setStatus(box, msgs.statusPrompt, 'warning');
            }).catch(function () {});
        }
    });

    document.querySelectorAll('[data-clock-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const box = form.querySelector('[data-clock-location]');
            const lat = form.querySelector('[data-clock-lat]');
            const lng = form.querySelector('[data-clock-lng]');
            const btn = form.querySelector('[data-clock-submit]');

            if (!lat || !lng || !box) return;

            if (lat.value !== '' && lng.value !== '') {
                return;
            }

            event.preventDefault();
            showErr(box, msgs.needEnable, false);
            setStatus(box, msgs.statusPrompt, 'warning');

            if (btn) {
                btn.disabled = true;
                btn.dataset.originalLabel = btn.innerHTML;
                btn.innerHTML = msgs.locating;
            }

            requestPosition(box, function () {
                if (btn) {
                    btn.disabled = false;
                    if (btn.dataset.originalLabel) btn.innerHTML = btn.dataset.originalLabel;
                }
                form.submit();
            }, function () {
                if (btn) {
                    btn.disabled = false;
                    if (btn.dataset.originalLabel) btn.innerHTML = btn.dataset.originalLabel;
                }
            });
        });
    });
})();
</script>
@endpush
