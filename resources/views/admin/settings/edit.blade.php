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
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <div class="fw-semibold">{{ __('settings.office_section') }}</div>
                    <p class="small text-body-secondary mb-0">{{ __('settings.office_hint') }}</p>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="office_latitude" class="form-label">{{ __('settings.office_latitude') }}</label>
                            <input type="number" step="any" id="office_latitude" name="office_latitude"
                                   class="form-control @error('office_latitude') is-invalid @enderror"
                                   value="{{ old('office_latitude', $officeLatitude) }}"
                                   placeholder="30.044420"
                                   dir="ltr">
                            @error('office_latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="office_longitude" class="form-label">{{ __('settings.office_longitude') }}</label>
                            <input type="number" step="any" id="office_longitude" name="office_longitude"
                                   class="form-control @error('office_longitude') is-invalid @enderror"
                                   value="{{ old('office_longitude', $officeLongitude) }}"
                                   placeholder="31.235712"
                                   dir="ltr">
                            @error('office_longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="office_radius_meters" class="form-label">{{ __('settings.office_radius_meters') }}</label>
                            <input type="number" min="50" max="50000" step="1" id="office_radius_meters" name="office_radius_meters"
                                   class="form-control @error('office_radius_meters') is-invalid @enderror"
                                   value="{{ old('office_radius_meters', $officeRadiusMeters) }}"
                                   dir="ltr">
                            @error('office_radius_meters') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">{{ __('settings.office_radius_hint') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <div class="fw-semibold">{{ __('settings.impersonation_section') }}</div>
                    <p class="small text-body-secondary mb-0">{{ __('settings.impersonation_hint') }}</p>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input type="hidden" name="impersonation_enabled" value="0">
                        <input type="checkbox" class="form-check-input" role="switch"
                               id="impersonation_enabled" name="impersonation_enabled" value="1"
                               @checked(old('impersonation_enabled', $impersonationEnabled))>
                        <label class="form-check-label" for="impersonation_enabled">
                            {{ __('settings.impersonation_enabled') }}
                        </label>
                    </div>
                    @error('impersonation_enabled') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="col-12">
            <a class="btn btn-outline-primary" href="{{ route('valuation.assignments.index') }}">{{ __('valuation.assign') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
        </div>
    </form>
@endsection
