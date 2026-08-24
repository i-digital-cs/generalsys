@php
    $maxMb = $photoMaxMb ?? (int) round(((int) config('attendance.photos.max_kilobytes', 51200)) / 1024);
    $inputId = $id ?? 'photos';
    $photosRequired = (bool) ($photosRequired ?? false);
    $useTitle = (bool) ($useTitle ?? false);
    $photosLabel = $photosLabel ?? __('clock.photos.label');
    $photosHint = $photosHint ?? ($photosRequired
        ? __('clock.photos.hint_required', ['mb' => $maxMb])
        : __('clock.photos.hint', ['mb' => $maxMb]));
    $captionLabel = $useTitle ? __('clock.photos.title_label') : __('clock.photos.description');
    $captionPlaceholder = $useTitle
        ? __('clock.photos.title_placeholder_required')
        : ($photosRequired
            ? __('clock.photos.description_placeholder_required')
            : __('clock.photos.description_placeholder'));
@endphp

<div class="mb-3" data-clock-photos @if($photosRequired) data-required="1" @endif>
    <label for="{{ $inputId }}" class="form-label">
        {{ $photosLabel }}
        @if($photosRequired)
            <span class="text-danger">*</span>
            <span class="text-body-secondary fw-normal">({{ __('clock.photos.required') }})</span>
        @else
            <span class="text-body-secondary fw-normal">({{ __('clock.photos.optional') }})</span>
        @endif
    </label>
    <input type="file"
           id="{{ $inputId }}"
           name="photos[]"
           class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror"
           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
           multiple
           data-clock-photos-input
           @if($photosRequired) required @endif
           autocomplete="off">
    @error('photos')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('photos.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <div class="form-text mb-2">
        {{ $photosHint }}
    </div>
    <div class="d-none" data-clock-photos-captions
         data-label="{{ $captionLabel }}"
         data-placeholder="{{ $captionPlaceholder }}"
         data-file-label="{{ __('clock.photos.file_n') }}"
         @if($photosRequired) data-required="1" @endif>
    </div>
    @error('photo_descriptions')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('photo_descriptions.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
