@extends('layouts.app')

@section('title', __('penalties.create'))

@section('content')
    <h1 class="h4 mb-3">{{ __('penalties.create') }}</h1>

    <div class="card border-0 shadow-sm" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.config.penalties.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">{{ __('penalties.fields.name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           class="form-control @error('name') is-invalid @enderror" required maxlength="150">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label">{{ __('penalties.fields.type') }}</label>
                    <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                        @foreach (\App\Models\PenaltyRule::types() as $type)
                            <option value="{{ $type }}" @selected(old('type') === $type)>
                                {{ __('penalties.types.'.$type) }}
                            </option>
                        @endforeach
                    </select>
                    @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="currency" class="form-label">{{ __('penalties.fields.currency') }}</label>
                    <select id="currency" name="currency" class="form-select @error('currency') is-invalid @enderror" required>
                        @foreach (['EGP', 'AED'] as $currency)
                            <option value="{{ $currency }}" @selected(old('currency', 'EGP') === $currency)>
                                {{ __('employees.currencies.'.$currency) }}
                            </option>
                        @endforeach
                    </select>
                    @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="amount_per_unit" class="form-label">{{ __('penalties.fields.amount_per_unit') }}</label>
                    <input type="number" step="0.01" min="0" id="amount_per_unit" name="amount_per_unit"
                           value="{{ old('amount_per_unit', '1.00') }}"
                           class="form-control @error('amount_per_unit') is-invalid @enderror" required dir="ltr">
                    @error('amount_per_unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="unit" class="form-label">{{ __('penalties.fields.unit') }}</label>
                    <select id="unit" name="unit" class="form-select @error('unit') is-invalid @enderror" required>
                        @foreach (['MINUTE', 'HOUR', 'DAY'] as $unit)
                            <option value="{{ $unit }}" @selected(old('unit', 'MINUTE') === $unit)>
                                {{ __('penalties.units.'.$unit) }}
                            </option>
                        @endforeach
                    </select>
                    @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="double_penalty_multiplier" class="form-label">{{ __('penalties.fields.double_penalty_multiplier') }}</label>
                    <input type="number" step="0.01" min="1" id="double_penalty_multiplier" name="double_penalty_multiplier"
                           value="{{ old('double_penalty_multiplier', '2.00') }}"
                           class="form-control @error('double_penalty_multiplier') is-invalid @enderror" required dir="ltr">
                    @error('double_penalty_multiplier') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-check mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                           @checked(old('is_active', true))>
                    <label class="form-check-label" for="is_active">{{ __('penalties.fields.is_active') }}</label>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                    <a href="{{ route('admin.config.penalties') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
