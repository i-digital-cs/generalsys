@extends('layouts.app')

@section('title', __('penalties.edit'))

@section('content')
    <h1 class="h4 mb-3">{{ __('penalties.edit') }}</h1>

    <div class="card border-0 shadow-sm" style="max-width: 40rem;">
        <div class="card-body">
            <p class="small text-body-secondary mb-3">
                {{ __('penalties.types.'.$rule->type) }} · {{ __('employees.currencies.'.$rule->currency) }}
            </p>

            <form method="POST" action="{{ route('admin.config.penalties.update', $rule) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">{{ __('penalties.fields.name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $rule->name) }}"
                           class="form-control @error('name') is-invalid @enderror" required maxlength="150">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="amount_per_unit" class="form-label">{{ __('penalties.fields.amount_per_unit') }}</label>
                    <input type="number" step="0.01" min="0" id="amount_per_unit" name="amount_per_unit"
                           value="{{ old('amount_per_unit', $rule->amount_per_unit) }}"
                           class="form-control @error('amount_per_unit') is-invalid @enderror" required dir="ltr">
                    @error('amount_per_unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="unit" class="form-label">{{ __('penalties.fields.unit') }}</label>
                    <select id="unit" name="unit" class="form-select @error('unit') is-invalid @enderror" required>
                        @foreach (['MINUTE', 'HOUR', 'DAY'] as $unit)
                            <option value="{{ $unit }}" @selected(old('unit', $rule->unit) === $unit)>
                                {{ __('penalties.units.'.$unit) }}
                            </option>
                        @endforeach
                    </select>
                    @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="double_penalty_multiplier" class="form-label">{{ __('penalties.fields.double_penalty_multiplier') }}</label>
                    <input type="number" step="0.01" min="1" id="double_penalty_multiplier" name="double_penalty_multiplier"
                           value="{{ old('double_penalty_multiplier', $rule->double_penalty_multiplier) }}"
                           class="form-control @error('double_penalty_multiplier') is-invalid @enderror" required dir="ltr">
                    @error('double_penalty_multiplier') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-check mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                           @checked(old('is_active', $rule->is_active))>
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
