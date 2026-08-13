@php
    $maxMb = $photoMaxMb ?? (int) round(((int) config('attendance.photos.max_kilobytes', 51200)) / 1024);
@endphp

<div class="mb-3">
    <label for="{{ $id ?? 'photos' }}" class="form-label">
        {{ __('clock.photos.label') }}
        <span class="text-body-secondary fw-normal">({{ __('clock.photos.optional') }})</span>
    </label>
    <input type="file"
           id="{{ $id ?? 'photos' }}"
           name="photos[]"
           class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror"
           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
           multiple>
    @error('photos')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('photos.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <div class="form-text">
        {{ __('clock.photos.hint', ['mb' => $maxMb]) }}
    </div>
</div>
