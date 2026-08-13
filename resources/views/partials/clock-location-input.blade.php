@php
    $gpsId = $id ?? 'clock-gps';
@endphp

<div class="mb-3 clock-location border rounded p-3 bg-body-tertiary" data-clock-location
     data-locale="{{ str_replace('_', '-', app()->getLocale()) }}">
    <input type="hidden" name="latitude" value="{{ old('latitude') }}" data-clock-lat>
    <input type="hidden" name="longitude" value="{{ old('longitude') }}" data-clock-lng>
    <input type="hidden" name="accuracy" value="{{ old('accuracy') }}" data-clock-acc>

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
        <div>
            <div class="fw-semibold small mb-1">
                <i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>{{ __('clock.location.title') }}
            </div>
            <div class="small text-body-secondary">{{ __('clock.location.required_hint') }}</div>
        </div>
        <span class="badge text-bg-secondary" data-clock-loc-status>{{ __('clock.location.status_unknown') }}</span>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-clock-loc-check>
            <i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ __('clock.location.check_btn') }}
        </button>
        <button type="button" class="btn btn-sm btn-primary" data-clock-loc-enable>
            <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ __('clock.location.enable_btn') }}
        </button>
    </div>

    <div class="d-none" data-clock-loc-ok>
        <div class="small fw-semibold text-success mb-1" data-clock-loc-ready-label></div>
        <div class="small mb-1 d-none" data-clock-loc-address></div>
        <div class="small text-body-secondary mb-1" dir="ltr" data-clock-loc-coords></div>
        <div class="small text-body-secondary mb-1 d-none" data-clock-loc-accuracy></div>
        <a href="#" target="_blank" rel="noopener" class="small link-primary d-none" data-clock-loc-maps>
            <i class="bi bi-map me-1" aria-hidden="true"></i>{{ __('clock.location.maps') }}
        </a>
    </div>
    <div class="small text-danger d-none" data-clock-loc-err role="alert"></div>
    <div class="small text-body-secondary d-none" data-clock-loc-help>{{ __('clock.location.denied_help') }}</div>

    @error('latitude')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('longitude')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
