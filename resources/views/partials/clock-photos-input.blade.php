@php
    $maxMb = $photoMaxMb ?? (int) round(((int) config('attendance.photos.max_kilobytes', 51200)) / 1024);
    $inputId = $id ?? 'photos';
@endphp

<div class="mb-3" data-clock-photos>
    <label for="{{ $inputId }}" class="form-label">
        {{ __('clock.photos.label') }}
        <span class="text-body-secondary fw-normal">({{ __('clock.photos.optional') }})</span>
    </label>
    <input type="file"
           id="{{ $inputId }}"
           name="photos[]"
           class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror"
           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
           multiple
           data-clock-photos-input
           autocomplete="off">
    @error('photos')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('photos.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <div class="form-text mb-2">
        {{ __('clock.photos.hint', ['mb' => $maxMb]) }}
    </div>
    <div class="d-none" data-clock-photos-captions
         data-label="{{ __('clock.photos.description') }}"
         data-placeholder="{{ __('clock.photos.description_placeholder') }}"
         data-file-label="{{ __('clock.photos.file_n') }}">
    </div>
    @error('photo_descriptions')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('photo_descriptions.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
