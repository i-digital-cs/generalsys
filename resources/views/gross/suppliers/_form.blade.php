@php
    $supplier = $supplier ?? null;
    $nameSuggestions = $nameSuggestions ?? [];
@endphp
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label" for="name">{{ __('gross.fields.name') }}</label>
        <input id="name" name="name" list="gross-suggest-supplier-name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $supplier?->name) }}" required autocomplete="off"
               placeholder="{{ __('gross.suppliers.name_suggest_hint') }}">
        <datalist id="gross-suggest-supplier-name">
            @foreach ($nameSuggestions as $value)
                <option value="{{ $value }}"></option>
            @endforeach
        </datalist>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">{{ __('gross.fields.phone') }}</label>
        <input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $supplier?->phone) }}" required>
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="address">{{ __('gross.fields.address') }}</label>
        <textarea id="address" name="address" class="form-control" rows="2">{{ old('address', $supplier?->address) }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label" for="notes">{{ __('gross.fields.notes') }}</label>
        <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $supplier?->notes) }}</textarea>
    </div>
</div>
