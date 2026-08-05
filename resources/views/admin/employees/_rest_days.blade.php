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
                <input type="date" name="date" id="rest_day_date"
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
            <div data-table-filter-root>
                @if ($restDays->count() > 1)
                    <x-table-toolbar
                        id="employee-rest-days"
                        :search-placeholder="__('dashboard.hours.search_date')"
                    />
                @endif
                <div class="table-responsive">
                    <table class="table table-hr table-sm align-middle mb-0" @if ($restDays->count() > 1) data-table-filterable @endif>
                        <thead>
                            <tr>
                                <th scope="col" class="col-num">{{ __('employees.rest_days.fields.date') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($restDays as $restDay)
                                <tr data-filter-text="{{ $restDay->date->toDateString() }}">
                                    <td class="col-num">
                                        <span class="num-ltr">{{ $restDay->date->toDateString() }}</span>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST"
                                              action="{{ route('admin.employees.rest-days.destroy', [$employee, $restDay]) }}"
                                              onsubmit="return confirm(@json(__('employees.rest_days.remove').'?'))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                {{ __('employees.rest_days.remove') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            @if ($restDays->count() > 1)
                                <tr data-table-empty-row class="d-none">
                                    <td colspan="2" class="text-body-secondary text-center py-3">
                                        {{ __('general.no_filter_results') }}
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
