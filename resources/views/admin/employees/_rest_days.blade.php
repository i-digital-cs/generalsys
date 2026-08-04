{{-- Rest days must sit OUTSIDE the employee update form (HTML forbids nested forms). --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('employees.rest_days.title') }}</div>
    <div class="card-body">
        <p class="small text-body-secondary">{{ __('employees.rest_days.hint') }}</p>

        <form method="POST" action="{{ route('admin.employees.rest-days.store', $employee) }}"
              class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-auto">
                <label for="rest_day_date" class="form-label">{{ __('employees.rest_days.fields.date') }}</label>
                <input type="date" name="date" id="rest_day_date" dir="ltr"
                       class="form-control @error('date') is-invalid @enderror"
                       value="{{ old('date') }}" required>
                @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('employees.rest_days.add') }}</button>
            </div>
        </form>

        @php $restDays = $employee->restDays()->orderBy('date')->get(); @endphp
        @if ($restDays->isEmpty())
            <p class="text-body-secondary mb-0">{{ __('employees.rest_days.empty') }}</p>
        @else
            <ul class="list-group list-group-flush">
                @foreach ($restDays as $restDay)
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span dir="ltr">{{ $restDay->date->toDateString() }}</span>
                        <form method="POST"
                              action="{{ route('admin.employees.rest-days.destroy', [$employee, $restDay]) }}"
                              onsubmit="return confirm(@json(__('employees.rest_days.remove').'?'))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                {{ __('employees.rest_days.remove') }}
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
