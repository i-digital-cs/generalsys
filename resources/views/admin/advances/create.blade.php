@extends('layouts.app')

@section('title', __('advances.create'))

@section('content')
    <div class="mb-3">
        <h1 class="h4 mb-1">{{ __('advances.create') }}</h1>
        <p class="text-body-secondary small mb-0">{{ __('advances.subtitle') }}</p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.advances.store') }}" class="row g-3">
                @csrf
                <div class="col-12 col-md-6">
                    <label for="user_id" class="form-label">{{ __('advances.fields.employee') }}</label>
                    <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                        <option value="">{{ __('general.none') }}</option>
                        @foreach ($employees as $emp)
                            @php
                                $label = app()->getLocale() === 'ar' ? $emp->name_ar : $emp->name_en;
                            @endphp
                            <option value="{{ $emp->id }}" @selected((int) old('user_id') === $emp->id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="date_given" class="form-label">{{ __('advances.fields.date_given') }}</label>
                    <input type="date" id="date_given" name="date_given"
                           class="form-control @error('date_given') is-invalid @enderror"
                           value="{{ old('date_given', now()->toDateString()) }}" required>
                    @error('date_given')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-6 col-md-4">
                    <label for="amount" class="form-label">{{ __('advances.fields.amount') }}</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                           class="form-control @error('amount') is-invalid @enderror"
                           value="{{ old('amount') }}" required>
                    @error('amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-6 col-md-4">
                    <label for="deduction_amount_monthly" class="form-label">{{ __('advances.fields.deduction_amount_monthly') }}</label>
                    <input type="number" step="0.01" min="0.01" id="deduction_amount_monthly" name="deduction_amount_monthly"
                           class="form-control @error('deduction_amount_monthly') is-invalid @enderror"
                           value="{{ old('deduction_amount_monthly') }}" required>
                    @error('deduction_amount_monthly')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                    <a href="{{ route('admin.advances.index') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
