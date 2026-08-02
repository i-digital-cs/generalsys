{{-- Rest days section (edit screen only) — monthly quota marked per date --}}
<div class="card border-0 shadow-sm my-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('employees.rest_days.title') }}</div>
    <div class="card-body">
        @if ($employee->restDays->isEmpty())
            <p class="text-body-secondary">{{ __('employees.rest_days.empty') }}</p>
        @else
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach ($employee->restDays as $restDay)
                    <span class="badge text-bg-secondary d-inline-flex align-items-center gap-2 py-2 px-3">
                        <span dir="ltr">{{ $restDay->date->translatedFormat('Y-m-d') }}</span>
                        <form method="POST" action="{{ route('admin.employees.rest-days.destroy', [$employee, $restDay]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-close btn-close-white"
                                    aria-label="{{ __('employees.rest_days.remove') }}"></button>
                        </form>
                    </span>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.employees.rest-days.store', $employee) }}"
              class="row g-2 align-items-end">
            @csrf
            <div class="col-12 col-md-4">
                <label for="rest_day_date" class="form-label">{{ __('employees.rest_days.date') }}</label>
                <input type="date" id="rest_day_date" name="date" dir="ltr"
                       class="form-control @error('date') is-invalid @enderror" required>
                @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12 col-md-3 d-grid">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('employees.rest_days.add') }}
                </button>
            </div>
        </form>
    </div>
</div>
