@extends('layouts.app')

@section('title', __('shifts.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('shifts.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('shifts.subtitle') }}</p>
        </div>
    </div>

    {{-- My Team roster --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 mb-0">{{ __('shifts.my_team') }}</h2>
                <form method="GET" action="{{ route('team-leader.shifts') }}" class="d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label for="roster_date" class="form-label visually-hidden">{{ __('shifts.date') }}</label>
                        <input type="date" id="roster_date" name="date" value="{{ $date }}" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('shifts.filter') }}</button>
                </form>
            </div>

            @if ($teamRoster->isEmpty())
                <x-empty-state :title="__('shifts.no_team_title')" :text="__('shifts.no_team_text')" icon="bi-people" />
            @else
                <div data-table-filter-root>
                    <x-table-toolbar
                        id="tl-shifts-roster"
                        :search-placeholder="__('dashboard.hours.search_name')"
                    />
                    <div class="table-responsive">
                        <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('shifts.employee') }}</th>
                                    <th scope="col">{{ __('shifts.today_shifts') }}</th>
                                    <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($teamRoster as $row)
                                    @php
                                        $member = $row['employee'];
                                        $name = app()->getLocale() === 'ar' ? $member->name_ar : $member->name_en;
                                    @endphp
                                    <tr data-filter-text="{{ $name }} {{ $member->name_ar }} {{ $member->name_en }}">
                                        <td class="fw-semibold">{{ $name }}</td>
                                        <td>
                                            @if ($row['assignments']->isEmpty())
                                                <span class="text-body-secondary">{{ __('shifts.no_shift_today') }}</span>
                                            @else
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach ($row['assignments'] as $asg)
                                                        <a href="{{ route('team-leader.shifts.edit', $asg) }}"
                                                           class="badge text-bg-primary text-decoration-none">
                                                            {{ __('shifts.types.'.$asg->shift_type) }}
                                                            <span class="num-ltr">({{ $asg->expected_start_time }}–{{ $asg->expected_end_time }})</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                        <a href="{{ route('team-leader.shifts', ['date' => $date, 'user_id' => $member->id]) }}#shift-assign-form"
                                           class="btn btn-sm btn-outline-primary">
                                            {{ __('shifts.assign_for') }}
                                        </a>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr data-table-empty-row class="d-none">
                                    <td colspan="3" class="text-body-secondary text-center py-3">
                                        {{ __('general.no_filter_results') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('shifts.assign_form_title') }}</h2>
                    @if ($employees->isEmpty())
                        <x-empty-state :title="__('shifts.no_team_title')" :text="__('shifts.no_team_text')" icon="bi-people" />
                    @else
                        <form method="POST" action="{{ route('team-leader.shifts.store') }}" id="shift-assign-form">
                            @csrf
                            <div class="mb-3">
                                <label for="user_id" class="form-label">{{ __('shifts.employee') }}</label>
                                <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                                    <option value="">{{ __('general.none') }}</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}"
                                                data-start="{{ $employee->work_start_time ?? '10:00' }}"
                                                data-end="{{ $employee->work_end_time ?? '18:00' }}"
                                                @selected((string) old('user_id', $preselectUserId) === (string) $employee->id)>
                                            {{ app()->getLocale() === 'ar' ? $employee->name_ar : $employee->name_en }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="assign_date" class="form-label">{{ __('shifts.date') }}</label>
                                <input type="date" id="assign_date" name="date"
                                       value="{{ old('date', $date) }}"
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
                                        <option value="{{ $type }}" @selected(old('shift_type') === $type)>
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
                                           value="{{ old('expected_start_time', '10:00') }}"
                                           class="form-control @error('expected_start_time') is-invalid @enderror" required>
                                    @error('expected_start_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6">
                                    <label for="expected_end_time" class="form-label">{{ __('shifts.end_time') }}</label>
                                    <input type="time" id="expected_end_time" name="expected_end_time"
                                           value="{{ old('expected_end_time', '18:00') }}"
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
                                           name="expected_hours" value="{{ old('expected_hours', '8') }}"
                                           class="form-control @error('expected_hours') is-invalid @enderror" required>
                                    @error('expected_hours')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6">
                                    <label for="multiplier" class="form-label">{{ __('shifts.multiplier') }}</label>
                                    <input type="number" step="0.1" min="0.5" max="5" id="multiplier"
                                           name="multiplier" value="{{ old('multiplier', '1') }}"
                                           class="form-control @error('multiplier') is-invalid @enderror">
                                    @error('multiplier')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">{{ __('shifts.notes') }}</label>
                                <textarea id="notes" name="notes" rows="2"
                                          class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>{{ __('shifts.assign') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('shifts.list_title', ['date' => $date]) }}</h2>

                    @if ($assignments->isEmpty())
                        <x-empty-state :title="__('shifts.empty_title')" :text="__('shifts.empty_text')" icon="bi-calendar3" />
                    @else
                        <div data-table-filter-root>
                            <x-table-toolbar
                                id="tl-shifts-assignments"
                                :search-placeholder="__('dashboard.hours.search_name')"
                                :filters="[[
                                    'name' => 'status',
                                    'label' => __('shifts.status'),
                                    'options' => [
                                        'SCHEDULED' => __('shifts.statuses.SCHEDULED'),
                                        'COMPLETED' => __('shifts.statuses.COMPLETED'),
                                        'CANCELLED' => __('shifts.statuses.CANCELLED'),
                                    ],
                                ]]"
                            />
                            <div class="table-responsive">
                                <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('shifts.employee') }}</th>
                                            <th scope="col">{{ __('shifts.shift_type') }}</th>
                                            <th scope="col" class="col-num">{{ __('shifts.start_time') }}</th>
                                            <th scope="col" class="col-num">{{ __('shifts.end_time') }}</th>
                                            <th scope="col" class="col-num">{{ __('shifts.expected_hours') }}</th>
                                            <th scope="col">{{ __('shifts.status') }}</th>
                                            <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($assignments as $row)
                                            @php
                                                $name = app()->getLocale() === 'ar' ? $row->user?->name_ar : $row->user?->name_en;
                                            @endphp
                                            <tr data-filter-text="{{ $name }} {{ $row->user?->name_ar }} {{ $row->user?->name_en }}"
                                                data-filter-status="{{ $row->status }}">
                                                <td>{{ $name }}</td>
                                                <td>{{ __('shifts.types.'.$row->shift_type) }}</td>
                                                <td class="col-num"><span class="num-ltr">{{ $row->expected_start_time }}</span></td>
                                                <td class="col-num"><span class="num-ltr">{{ $row->expected_end_time }}</span></td>
                                                <td class="col-num"><span class="num-ltr">{{ $row->expected_hours }}</span></td>
                                                <td>
                                                @php
                                                    $badge = match ($row->status) {
                                                        'SCHEDULED' => 'primary',
                                                        'COMPLETED' => 'success',
                                                        default => 'secondary',
                                                    };
                                                @endphp
                                                <span class="badge text-bg-{{ $badge }}">
                                                    {{ __('shifts.statuses.'.$row->status) }}
                                                </span>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                @if ($row->status === 'SCHEDULED')
                                                    <a href="{{ route('team-leader.shifts.edit', $row) }}"
                                                       class="btn btn-sm btn-outline-primary">
                                                        {{ __('general.edit') }}
                                                    </a>
                                                    <form method="POST"
                                                          action="{{ route('team-leader.shifts.cancel', $row) }}"
                                                          class="d-inline"
                                                          onsubmit="return confirm(@js(__('shifts.confirm_cancel')))">
                                                        @csrf
                                                        <input type="hidden" name="date" value="{{ $date }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            {{ __('shifts.cancel_action') }}
                                                        </button>
                                                    </form>
                                                @else
                                                    {{ __('general.not_available') }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr data-table-empty-row class="d-none">
                                        <td colspan="7" class="text-body-secondary text-center py-3">
                                            {{ __('general.no_filter_results') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                        <div class="mt-3">
                            {{ $assignments->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('shift-assign-form');
    if (!form) return;

    const userSelect = document.getElementById('user_id');
    const typeSelect = document.getElementById('shift_type');
    const startInput = document.getElementById('expected_start_time');
    const endInput = document.getElementById('expected_end_time');
    const hoursInput = document.getElementById('expected_hours');
    const multiplierInput = document.getElementById('multiplier');

    const defaults = {
        FULL_TIME: { hours: 8, multiplier: 1 },
        ONLINE_HALF: { hours: 4, multiplier: 1 },
        ONLINE_FULL: { hours: 12, multiplier: 1 },
        EXTRA_SHIFT: { hours: 4, multiplier: 1.5 },
        OVERTIME: { hours: 4, multiplier: 1.5 }
    };

    function applyDefaults() {
        const type = typeSelect.value;
        const preset = defaults[type] || defaults.FULL_TIME;
        const option = userSelect.options[userSelect.selectedIndex];
        const start = (option && option.dataset.start) ? option.dataset.start : '10:00';
        const end = (option && option.dataset.end) ? option.dataset.end : '18:00';

        if (type === 'FULL_TIME' || type === 'ONLINE_HALF' || type === 'ONLINE_FULL') {
            startInput.value = start;
            if (type === 'ONLINE_HALF') {
                endInput.value = '14:00';
            } else if (type === 'ONLINE_FULL') {
                endInput.value = '22:00';
            } else {
                endInput.value = end;
            }
        } else {
            startInput.value = '18:00';
            endInput.value = '22:00';
        }

        hoursInput.value = preset.hours;
        multiplierInput.value = preset.multiplier;
    }

    typeSelect.addEventListener('change', applyDefaults);
    userSelect.addEventListener('change', applyDefaults);
})();
</script>
@endpush
