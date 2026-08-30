@php
    $customer = $customer ?? null;
    $nameSuggestions = $nameSuggestions ?? [];
@endphp
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label" for="name">{{ __('gross.fields.name') }}</label>
        <input id="name" name="name" list="gross-suggest-customer-name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $customer?->name) }}" required autocomplete="off"
               placeholder="{{ __('gross.customers.name_suggest_hint') }}">
        <datalist id="gross-suggest-customer-name">
            @foreach ($nameSuggestions as $value)
                <option value="{{ $value }}"></option>
            @endforeach
        </datalist>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">{{ __('gross.fields.phone') }}</label>
        <input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $customer?->phone) }}" required>
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="address">{{ __('gross.fields.address') }}</label>
        <textarea id="address" name="address" class="form-control" rows="2">{{ old('address', $customer?->address) }}</textarea>
    </div>
</div>
