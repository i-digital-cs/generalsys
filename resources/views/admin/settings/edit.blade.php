@extends('layouts.app')

@section('title', __('settings.title'))

@section('content')
    <h1 class="h4 mb-3">{{ __('settings.title') }}</h1>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="row g-3">
        @csrf
        @method('PUT')

        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold">{{ __('settings.defaults_section') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="default_language" class="form-label">{{ __('settings.default_language') }}</label>
                        <select id="default_language" name="default_language"
                                class="form-select @error('default_language') is-invalid @enderror" required>
                            @foreach (['ar', 'en'] as $option)
                                <option value="{{ $option }}" @selected(old('default_language', $defaultLanguage) === $option)>
                                    {{ __("employees.languages.{$option}") }}
                                </option>
                            @endforeach
                        </select>
                        @error('default_language') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-0">
                        <label for="default_currency" class="form-label">{{ __('settings.default_currency') }}</label>
                        <select id="default_currency" name="default_currency"
                                class="form-select @error('default_currency') is-invalid @enderror" required>
                            @foreach (['EGP', 'AED'] as $option)
                                <option value="{{ $option }}" @selected(old('default_currency', $defaultCurrency) === $option)>
                                    {{ __("employees.currencies.{$option}") }}
                                </option>
                            @endforeach
                        </select>
                        @error('default_currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <div class="fw-semibold">{{ __('settings.multipliers_section') }}</div>
                    <p class="small text-body-secondary mb-0">{{ __('settings.multipliers_hint') }}</p>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach ($multipliers as $name => $value)
                            <div class="col-6">
                                <label for="mult_{{ $name }}" class="form-label">{{ __('settings.multipliers.'.$name) }}</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="10"
                                    id="mult_{{ $name }}"
                                    name="multipliers[{{ $name }}]"
                                    class="form-control @error('multipliers.'.$name) is-invalid @enderror"
                                    value="{{ old('multipliers.'.$name, number_format((float) $value, 2, '.', '')) }}"
                                    required
                                >
                                @error('multipliers.'.$name)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
        </div>
    </form>
@endsection
