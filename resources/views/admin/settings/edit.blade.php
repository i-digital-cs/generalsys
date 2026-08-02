@extends('layouts.app')

@section('title', __('settings.title'))

@section('content')
    <h1 class="h4 mb-3">{{ __('settings.title') }}</h1>

    <div class="card border-0 shadow-sm" style="max-width: 40rem;">
        <div class="card-header bg-transparent fw-semibold">{{ __('settings.defaults_section') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')
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
                <div class="mb-3">
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
                <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
            </form>
        </div>
    </div>
@endsection
