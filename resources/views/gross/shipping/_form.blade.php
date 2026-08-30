@php
    $company = $company ?? null;
    $nameSuggestions = $nameSuggestions ?? [];
@endphp
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label" for="name">{{ __('gross.fields.name') }}</label>
        <input id="name" name="name" list="gross-suggest-shipping-name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $company?->name) }}" required autocomplete="off"
               placeholder="{{ __('gross.shipping.name_suggest_hint') }}">
        <datalist id="gross-suggest-shipping-name">
            @foreach ($nameSuggestions as $value)
                <option value="{{ $value }}"></option>
            @endforeach
        </datalist>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">{{ __('gross.fields.phone') }}</label>
        <input id="phone" name="phone" class="form-control" value="{{ old('phone', $company?->phone) }}">
    </div>
    <div class="col-12">
        <label class="form-label" for="address">{{ __('gross.fields.address') }}</label>
        <textarea id="address" name="address" class="form-control" rows="2">{{ old('address', $company?->address) }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label" for="notes">{{ __('gross.fields.notes') }}</label>
        <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $company?->notes) }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="status">{{ __('gross.fields.status') }}</label>
        <select id="status" name="status" class="form-select" required>
            @foreach (['ACTIVE', 'INACTIVE'] as $st)
                <option value="{{ $st }}" @selected(old('status', $company?->status ?? 'ACTIVE') === $st)>
                    {{ __('gross.statuses.'.$st) }}
                </option>
            @endforeach
        </select>
    </div>
</div>
