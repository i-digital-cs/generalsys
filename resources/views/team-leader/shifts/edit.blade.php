@extends('layouts.app')

@section('title', __('shifts.edit_title'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('shifts.edit_title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('shifts.edit_subtitle') }}</p>
        </div>
        <a href="{{ route('team-leader.shifts', ['date' => $assignment->date->toDateString()]) }}"
           class="btn btn-outline-secondary">
            {{ __('shifts.back_to_list') }}
        </a>
    </div>

    <div class="card border-0 shadow-sm" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('team-leader.shifts.update', $assignment) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="user_id" class="form-label">{{ __('shifts.employee') }}</label>
                    <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}"
                                    @selected((string) old('user_id', $assignment->user_id) === (string) $employee->id)>
                                {{ app()->getLocale() === 'ar' ? $employee->name_ar : $employee->name_en }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="date" class="form-label">{{ __('shifts.date') }}</label>
                    <input type="date" id="date" name="date"
                           value="{{ old('date', $assignment->date->toDateString()) }}"
                           class="form-control @error('date') is-invalid @enderror" required>
                    @error('date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="shift_type" class="form-label">{{ __('shifts.shift_type') }}</label>
                    <select id="shift_type" name="shift_type"
                            class="form-select @error('shift_type') is-invalid @enderror" required>
                        @foreach ($shiftTypes as $type)
                            <option value="{{ $type }}" @selected(old('shift_type', $assignment->shift_type) === $type)>
                                {{ __('shifts.types.'.$type) }}
                            </option>
                        @endforeach
                    </select>
                    @error('shift_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="expected_start_time" class="form-label">{{ __('shifts.start_time') }}</label>
                        <input type="time" id="expected_start_time" name="expected_start_time"
                               value="{{ old('expected_start_time', $assignment->expected_start_time) }}"
                               class="form-control @error('expected_start_time') is-invalid @enderror" required>
                        @error('expected_start_time')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-6">
                        <label for="expected_end_time" class="form-label">{{ __('shifts.end_time') }}</label>
                        <input type="time" id="expected_end_time" name="expected_end_time"
                               value="{{ old('expected_end_time', $assignment->expected_end_time) }}"
                               class="form-control @error('expected_end_time') is-invalid @enderror" required>
                        @error('expected_end_time')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="expected_hours" class="form-label">{{ __('shifts.expected_hours') }}</label>
                        <input type="number" step="0.25" min="0.25" max="24" id="expected_hours"
                               name="expected_hours"
                               value="{{ old('expected_hours', $assignment->expected_hours) }}"
                               class="form-control @error('expected_hours') is-invalid @enderror" required>
                        @error('expected_hours')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-6">
                        <label for="multiplier" class="form-label">{{ __('shifts.multiplier') }}</label>
                        <input type="number" step="0.1" min="0.5" max="5" id="multiplier"
                               name="multiplier"
                               value="{{ old('multiplier', $assignment->multiplier) }}"
                               class="form-control @error('multiplier') is-invalid @enderror">
                        @error('multiplier')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">{{ __('shifts.notes') }}</label>
                    <textarea id="notes" name="notes" rows="2"
                              class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $assignment->notes) }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        {{ __('general.save') }}
                    </button>
                    <a href="{{ route('team-leader.shifts', ['date' => $assignment->date->toDateString()]) }}"
                       class="btn btn-outline-secondary">
                        {{ __('general.cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
