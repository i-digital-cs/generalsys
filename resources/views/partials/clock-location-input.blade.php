@php
    $gpsId = $id ?? 'clock-gps';
@endphp

<div class="mb-3 clock-location" data-clock-location>
    <input type="hidden" name="latitude" value="{{ old('latitude') }}" data-clock-lat>
    <input type="hidden" name="longitude" value="{{ old('longitude') }}" data-clock-lng>
    <input type="hidden" name="accuracy" value="{{ old('accuracy') }}" data-clock-acc>

    <div class="small text-body-secondary mb-1">
        <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ __('clock.location.required_hint') }}
    </div>
    <div class="small text-success d-none" data-clock-loc-ok>{{ __('clock.location.ready') }}</div>
    <div class="small text-danger d-none" data-clock-loc-err role="alert"></div>

    @error('latitude')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('longitude')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
