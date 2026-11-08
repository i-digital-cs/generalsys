@php
    $isRtl = app()->getLocale() === 'ar';
    $name = $isRtl ? $group['name_ar'] : ($group['name_en'] ?: $group['name_ar']);
    $treatments = $group['treatments'] ?? [];
    $hasTreatments = count($treatments) > 0;
    $isOoc = $ooc || ! empty($group['out_of_contract']);
    $setupWarning = ! empty($group['setup_warning']) || ! $hasTreatments;
@endphp
<div class="col-6 col-md-4" data-product-card
     data-cat="{{ $group['category_id'] ?? '' }}"
     data-name="{{ strtolower(($group['code'] ?? '').' '.$group['name_ar'].' '.$group['name_en']) }}"
     data-product-name="{{ $name }}"
     data-image="{{ $group['image_url'] ?? '' }}"
     data-ooc="{{ $isOoc ? '1' : '0' }}">
    <div class="border rounded p-2 h-100 d-flex flex-column {{ $isOoc ? 'border-warning' : '' }}">
        @if (! empty($group['image_url']))
            <img class="gh-product-thumb gh-product-thumb--lg" src="{{ $group['image_url'] }}"
                 alt="" width="52" height="52" loading="lazy" decoding="async">
        @else
            <span class="gh-product-thumb gh-product-thumb--lg gh-product-thumb--fallback" aria-hidden="true">
                <i class="bi bi-image"></i>
            </span>
        @endif
        <div class="fw-semibold small">{{ $name }}</div>
        @if (! empty($group['code']))
            <div class="small text-body-secondary num-ltr">{{ $group['code'] }}</div>
        @endif
        @if ($isOoc)
            <span class="badge text-bg-warning align-self-start mb-1">{{ __('gh.invoices.out_of_contract') }}</span>
        @endif
        @if ($setupWarning)
            <p class="small text-warning mb-1">{{ __('gh.orders.product_no_treatments') }}</p>
        @else
            <label class="form-label small mb-0" for="treat-{{ ($isOoc ? 'ooc-' : '').$group['product_id'] }}">{{ __('gh.fields.treatment') }}</label>
            <select id="treat-{{ ($isOoc ? 'ooc-' : '').$group['product_id'] }}" class="form-select form-select-sm mb-2" data-treatment-select>
                @foreach ($treatments as $t)
                    <option value="{{ $t['offering_id'] }}" data-price="{{ $t['unit_price'] }}">
                        {{ $isRtl ? $t['name_ar'] : ($t['name_en'] ?: $t['name_ar']) }}
                        @unless ($isOoc) — {{ number_format((float) $t['unit_price'], 2) }} @endunless
                    </option>
                @endforeach
            </select>
            @unless ($isOoc)
                <div class="hp-value num-ltr small mb-2">
                    <x-money :amount="$treatments[0]['unit_price']" currency="AED" />
                </div>
            @endunless
        @endif
        <button type="button"
                class="btn btn-sm {{ $isOoc ? 'btn-warning' : 'btn-primary' }} mt-auto gh-add-product"
                @disabled($setupWarning)
                data-offering-id="{{ $hasTreatments ? $treatments[0]['offering_id'] : '' }}"
                data-price="{{ $hasTreatments ? $treatments[0]['unit_price'] : '0' }}"
                data-ooc="{{ $isOoc ? '1' : '0' }}">
            {{ __('gh.invoices.add') }}
        </button>
    </div>
</div>
