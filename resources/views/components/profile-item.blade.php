@props([
    'icon',
    'label',
    'value' => null,
    'ltr' => false,
])

<div class="col-12 col-sm-6 col-lg-4">
    <div class="d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-primary bg-opacity-10 text-primary"
              style="width: 2rem; height: 2rem;" aria-hidden="true">
            <i class="bi {{ $icon }}"></i>
        </span>
        <div class="min-w-0">
            <div class="small text-body-secondary">{{ $label }}</div>
            <div class="fw-semibold text-truncate" @if ($ltr) dir="ltr" @endif>
                {{ $value ?? __('general.not_available') }}
            </div>
        </div>
    </div>
</div>
