@extends('layouts.app')

@section('title', __('media.history.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('media.history.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('media.history.subtitle') }}</p>
        </div>
        <a href="{{ route('employee.clock') }}" class="btn btn-outline-primary">
            <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ __('nav.clock') }}
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('employee.media') }}" class="row g-2 align-items-end">
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="from" class="form-label">{{ __('media.history.from') }}</label>
                    <input type="date" id="from" name="from" value="{{ $from }}"
                           class="form-control @error('from') is-invalid @enderror" required>
                    @error('from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="to" class="form-label">{{ __('media.history.to') }}</label>
                    <input type="date" id="to" name="to" value="{{ $to }}"
                           class="form-control @error('to') is-invalid @enderror" required>
                    @error('to') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="kind" class="form-label">{{ __('media.history.kind') }}</label>
                    <select id="kind" name="kind" class="form-select">
                        @foreach (['all', 'FIELD', 'CLOCK_IN', 'CLOCK_OUT'] as $option)
                            <option value="{{ $option }}" @selected($kind === $option)>
                                {{ __('media.history.kinds.'.$option) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="location" class="form-label">{{ __('media.history.location') }}</label>
                    <select id="location" name="location" class="form-select">
                        @foreach (['all', 'with_gps', 'near', 'outside', 'no_gps'] as $option)
                            <option value="{{ $option }}" @selected($location === $option)>
                                {{ __('media.history.locations.'.$option) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label for="q" class="form-label">{{ __('media.history.search') }}</label>
                    <input type="search" id="q" name="q" value="{{ $q }}" class="form-control"
                           placeholder="{{ __('media.history.search_placeholder') }}">
                </div>
                <div class="col-12 col-xl-1 d-grid">
                    <button type="submit" class="btn btn-primary">{{ __('media.history.apply') }}</button>
                </div>
                <div class="col-12">
                    <a href="{{ route('employee.media') }}" class="btn btn-sm btn-outline-secondary">
                        {{ __('media.history.clear') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex flex-wrap justify-content-between gap-2">
            <span class="fw-semibold">{{ __('media.history.list_title') }}</span>
            <span class="small text-body-secondary">
                {{ __('media.history.count', ['count' => $photos->total()]) }}
            </span>
        </div>
        <div class="card-body">
            @if ($photos->isEmpty())
                <x-empty-state
                    :title="__('media.history.empty_title')"
                    :text="__('media.history.empty_text')"
                    icon="bi-images"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('clock.photos.label') }}</th>
                                <th scope="col">{{ __('media.history.title_col') }}</th>
                                <th scope="col">{{ __('media.history.kind') }}</th>
                                <th scope="col">{{ __('media.history.date') }}</th>
                                <th scope="col">{{ __('media.history.shift') }}</th>
                                <th scope="col">{{ __('media.history.uploaded_at') }}</th>
                                <th scope="col">{{ __('media.history.coords') }}</th>
                                <th scope="col">{{ __('media.history.distance') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($photos as $photo)
                                @php
                                    $caption = trim((string) ($photo->description ?? ''));
                                    $title = $caption !== '' ? $caption : __('media.history.no_title');
                                    $attendance = $photo->attendance;
                                    $hasGps = $photo->lat !== null && $photo->lng !== null;
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('attendance.photos.show', $photo) }}"
                                           target="_blank"
                                           rel="noopener"
                                           class="attendance-photo-thumb border rounded overflow-hidden bg-body-secondary d-block"
                                           title="{{ $title }}"
                                           style="width: 3.5rem; height: 3.5rem;">
                                            <img src="{{ route('attendance.photos.show', $photo) }}"
                                                 alt="{{ $title }}"
                                                 loading="lazy"
                                                 class="attendance-photo-img">
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-break">{{ $title }}</div>
                                        @if ($photo->original_name)
                                            <div class="small text-body-secondary text-break">{{ $photo->original_name }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge text-bg-light border text-dark">
                                            {{ __('media.history.kinds.'.$photo->kind) }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="num-ltr">
                                            {{ $attendance?->date?->translatedFormat('d M Y') ?? __('general.not_available') }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($attendance?->shift_type)
                                            {{ __('clock.shifts.'.$attendance->shift_type) }}
                                        @else
                                            {{ __('general.not_available') }}
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="num-ltr">
                                            {{ $photo->uploaded_at?->translatedFormat('d M Y H:i') ?? __('general.not_available') }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($hasGps)
                                            <div class="d-flex flex-column gap-1">
                                                <a href="{{ \App\Services\Attendance\GeofenceService::googleMapsUrl((float) $photo->lat, (float) $photo->lng) }}"
                                                   target="_blank"
                                                   rel="noopener"
                                                   class="link-primary small"
                                                   dir="ltr">
                                                    <i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>
                                                    {{ number_format((float) $photo->lat, 5) }}, {{ number_format((float) $photo->lng, 5) }}
                                                </a>
                                                <span @class([
                                                    'badge align-self-start',
                                                    'text-bg-warning text-dark' => (bool) $photo->outside_geofence,
                                                    'text-bg-success' => ! (bool) $photo->outside_geofence,
                                                ])>
                                                    {{ $photo->outside_geofence ? __('clock.location.outside') : __('clock.location.inside') }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="small text-body-secondary">{{ __('media.history.no_gps') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($photo->distance_m !== null)
                                            <span class="num-ltr small">{{ __('clock.location.distance', ['m' => $photo->distance_m]) }}</span>
                                        @else
                                            <span class="small text-body-secondary">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('attendance.photos.show', $photo) }}"
                                           target="_blank"
                                           rel="noopener"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>
                                            {{ __('media.history.open') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                    <p class="small text-body-secondary mb-0">
                        {{ __('media.history.showing', [
                            'from' => $photos->firstItem() ?? 0,
                            'to' => $photos->lastItem() ?? 0,
                            'total' => $photos->total(),
                        ]) }}
                    </p>
                    <nav class="overflow-auto" aria-label="{{ __('media.history.title') }}">
                        {{ $photos->onEachSide(1)->links() }}
                    </nav>
                </div>
            @endif
        </div>
    </div>
@endsection
