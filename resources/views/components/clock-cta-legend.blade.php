@props([])

<p class="small text-body-secondary mb-0 mt-2" {{ $attributes }}>
    <span class="d-inline-flex flex-wrap gap-2 align-items-center">
        <span class="clock-cta-swatch clock-cta-swatch--green" aria-hidden="true"></span>{{ __('clock.cta.green') }}
        <span class="clock-cta-swatch clock-cta-swatch--yellow" aria-hidden="true"></span>{{ __('clock.cta.yellow') }}
        <span class="clock-cta-swatch clock-cta-swatch--orange" aria-hidden="true"></span>{{ __('clock.cta.orange') }}
        <span class="clock-cta-swatch clock-cta-swatch--red" aria-hidden="true"></span>{{ __('clock.cta.red') }}
    </span>
</p>
