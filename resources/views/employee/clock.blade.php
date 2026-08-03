@extends('layouts.app')

@section('title', __('clock.title'))

@section('content')
    <h1 class="h4 mb-3">{{ __('clock.title') }}</h1>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            @if ($open)
                <div class="text-center mb-4">
                    <span class="badge text-bg-success fs-6 px-3 py-2">{{ __('clock.status_open') }}</span>
                </div>

                <dl class="row mb-4">
                    <dt class="col-sm-4">{{ __('clock.shift_type') }}</dt>
                    <dd class="col-sm-8">{{ __('clock.shifts.'.$open->shift_type) }}</dd>
                    <dt class="col-sm-4">{{ __('clock.clocked_at') }}</dt>
                    <dd class="col-sm-8" dir="ltr">{{ $open->clock_in?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                    <dt class="col-sm-4">{{ __('clock.planned_hours') }}</dt>
                    <dd class="col-sm-8" dir="ltr">{{ $open->planned_hours }}</dd>
                    <dt class="col-sm-4">{{ __('clock.elapsed') }}</dt>
                    <dd class="col-sm-8 font-monospace fs-4" dir="ltr" id="clock-elapsed"
                        data-clock-in="{{ $open->clock_in?->toIso8601String() }}">--:--:--</dd>
                </dl>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    @if ($open->is_friday_work)
                        <span class="badge text-bg-primary">{{ __('clock.flags.friday') }}</span>
                    @endif
                    @if ($open->is_day_off_work)
                        <span class="badge text-bg-warning text-dark">{{ __('clock.flags.rest_day') }}</span>
                    @endif
                    @if ($open->is_official_holiday_work)
                        <span class="badge text-bg-info">{{ __('clock.flags.holiday') }}</span>
                    @endif
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
