@extends('layouts.app')

@section('title', __('permissions.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('permissions.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('permissions.subtitle') }}</p>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3 flex-nowrap">
        <li class="nav-item">
            <a class="nav-link @if ($tab === 'permissions') active @endif"
               href="{{ route('employee.requests', ['tab' => 'permissions']) }}">
                {{ __('permissions.tabs.permissions') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if ($tab === 'vacations') active @endif"
               href="{{ route('employee.requests', ['tab' => 'vacations']) }}">
                {{ __('permissions.tabs.vacations') }}
            </a>
        </li>
    </ul>

    @if ($tab === 'vacations')
        <div class="row g-3">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('vacations.rest_pool_title') }}</h2>
                        @if ($availableRestDays->isEmpty())
                            <p class="text-body-secondary mb-0">{{ __('vacations.rest_pool_empty') }}</p>
                        @else
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($availableRestDays as $restDay)
                                    <span class="badge text-bg-secondary py-2 px-3" dir="ltr">
                                        {{ $restDay->date->toDateString() }}
                                    </span>
                                @endforeach
                            </div>
                            <p class="small text-body-secondary mb-0 mt-2">{{ __('vacations.swap_hint') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('vacations.new') }}</h2>
                        <form method="POST" action="{{ route('employee.requests.vacations.store') }}" id="vacation-request-form">
                            @csrf
                            <div class="mb-3">
                                <label for="vac_type" class="form-label">{{ __('vacations.fields.type') }}</label>
                                <select id="vac_type" name="type"
                                        class="form-select @error('type') is-invalid @enderror" required>
                                    @foreach (['ANNUAL', 'SICK', 'EMERGENCY'] as $type)
                                        <option value="{{ $type }}" @selected(old('type', 'EMERGENCY') === $type)>
                                            {{ __('vacations.types.'.$type) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="start_date" class="form-label">{{ __('vacations.fields.off_date') }}</label>
                                <input type="date" id="start_date" name="start_date"
                                       value="{{ old('start_date', now()->toDateString()) }}"
                                       class="form-control @error('start_date') is-invalid @enderror" required>
                                <input type="hidden" name="end_date" id="end_date" value="{{ old('end_date', old('start_date', now()->toDateString())) }}">
                                <input type="hidden" name="days_requested" value="{{ old('days_requested', '1') }}">
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @error('end_date')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="replace_rest_day_id" class="form-label">{{ __('vacations.fields.replace_rest_day') }}</label>
                                <select id="replace_rest_day_id" name="replace_rest_day_id"
                                        class="form-select @error('replace_rest_day_id') is-invalid @enderror" required
                                        @disabled($availableRestDays->isEmpty())>
                                    <option value="">{{ __('vacations.fields.replace_rest_day_placeholder') }}</option>
                                    @foreach ($availableRestDays as $restDay)
                                        <option value="{{ $restDay->id }}"
                                            @selected((string) old('replace_rest_day_id') === (string) $restDay->id)>
                                            {{ $restDay->date->toDateString() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('replace_rest_day_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">{{ __('vacations.swap_hint') }}</div>
                            </div>

                            <button type="submit" class="btn btn-primary" @disabled($availableRestDays->isEmpty())>
                                {{ __('vacations.submit') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('vacations.list') }}</h2>

                        @if ($vacations->isEmpty())
                            <x-empty-state
                                :title="__('vacations.empty_title')"
                                :text="__('vacations.empty_text')"
                                icon="bi-calendar-plus"
                            />
                        @else
                            <div data-table-filter-root>
                                <x-table-toolbar
                                    id="employee-vacations-list"
                                    :search-placeholder="__('dashboard.hours.search_date')"
                                    :filters="[[
                                        'name' => 'status',
                                        'label' => __('vacations.fields.status'),
                                        'options' => [
                                            'PENDING' => __('vacations.statuses.PENDING'),
                                            'APPROVED' => __('vacations.statuses.APPROVED'),
                                            'REJECTED' => __('vacations.statuses.REJECTED'),
                                            'FORCED' => __('vacations.statuses.FORCED'),
                                        ],
                                    ]]"
                                />
                                <div class="table-responsive">
                                    <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
                                        <thead>
                                            <tr>
                                                <th scope="col" class="col-num">{{ __('vacations.fields.range') }}</th>
                                                <th scope="col" class="col-num">{{ __('vacations.fields.replace_rest_day') }}</th>
                                                <th scope="col">{{ __('vacations.fields.type') }}</th>
                                                <th scope="col">{{ __('vacations.fields.status') }}</th>
                                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($vacations as $row)
                                                <tr data-filter-text="{{ $row->start_date->toDateString() }} {{ $row->replace_rest_date?->toDateString() }} {{ __('vacations.types.'.$row->type) }}"
                                                    data-filter-status="{{ $row->status }}">
                                                    <td class="col-num text-nowrap">
                                                        <span class="num-ltr">{{ $row->start_date->toDateString() }}</span>
                                                    </td>
                                                    <td class="col-num text-nowrap">
                                                        <span class="num-ltr">{{ $row->replace_rest_date?->toDateString() ?? '—' }}</span>
                                                    </td>
                                                    <td>{{ __('vacations.types.'.$row->type) }}</td>
                                                    <td>
                                                    <span @class([
                                                        'badge',
                                                        'text-bg-warning' => $row->status === 'PENDING',
                                                        'text-bg-success' => $row->status === 'APPROVED',
                                                        'text-bg-danger' => $row->status === 'REJECTED',
                                                        'text-bg-dark' => $row->status === 'FORCED',
                                                    ])>
                                                        {{ __('vacations.statuses.'.$row->status) }}
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    @if ($row->status === 'REJECTED')
                                                        <form method="POST"
                                                              action="{{ route('employee.requests.vacations.force', $row) }}"
                                                              class="d-inline"
                                                              onsubmit="return confirm(@json(__('vacations.force_confirm')))">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-dark">
                                                                {{ __('vacations.force') }}
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-body-secondary">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr data-table-empty-row class="d-none">
                                            <td colspan="5" class="text-body-secondary text-center py-3">
                                                {{ __('general.no_filter_results') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                            <div class="mt-3">
                                {{ $vacations->withQueryString()->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
                (function () {
                    const start = document.getElementById('start_date');
                    const end = document.getElementById('end_date');
                    const days = document.getElementById('days_requested');
                    if (!start || !end || !days) return;

                    const syncDays = () => {
                        if (!start.value || !end.value) return;
                        const from = new Date(start.value + 'T00:00:00');
                        const to = new Date(end.value + 'T00:00:00');
                        const diff = Math.round((to - from) / 86400000) + 1;
                        if (diff >= 1) {
                            days.value = String(diff);
                        }
                    };

                    start.addEventListener('change', syncDays);
                    end.addEventListener('change', syncDays);
                })();
            </script>
        @endpush
    @else
        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('permissions.new') }}</h2>
                        <form method="POST" action="{{ route('employee.requests.permissions.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="type" class="form-label">{{ __('permissions.fields.type') }}</label>
                                <select id="type" name="type"
                                        class="form-select @error('type') is-invalid @enderror" required>
                                    <option value="LEAVE_PERMISSION" @selected(old('type', 'LEAVE_PERMISSION') === 'LEAVE_PERMISSION')>
                                        {{ __('permissions.types.LEAVE_PERMISSION') }}
                                    </option>
                                    <option value="LATE_PERMISSION" @selected(old('type') === 'LATE_PERMISSION')>
                                        {{ __('permissions.types.LATE_PERMISSION') }}
                                    </option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="request_date" class="form-label">{{ __('permissions.fields.request_date') }}</label>
                                <input type="date" id="request_date" name="request_date"
                                       value="{{ old('request_date', now()->toDateString()) }}"
                                       class="form-control @error('request_date') is-invalid @enderror" required>
                                @error('request_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label for="from_time" class="form-label">{{ __('permissions.fields.from_time') }}</label>
                                    <input type="time" id="from_time" name="from_time"
                                           value="{{ old('from_time') }}"
                                           class="form-control @error('from_time') is-invalid @enderror" required>
                                    @error('from_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6">
                                    <label for="to_time" class="form-label">{{ __('permissions.fields.to_time') }}</label>
                                    <input type="time" id="to_time" name="to_time"
                                           value="{{ old('to_time') }}"
                                           class="form-control @error('to_time') is-invalid @enderror" required>
                                    @error('to_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="reason" class="form-label">{{ __('permissions.fields.reason') }}</label>
                                <textarea id="reason" name="reason" rows="3"
                                          class="form-control @error('reason') is-invalid @enderror"
                                          maxlength="2000">{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">{{ __('permissions.reason_hint') }}</div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                {{ __('permissions.submit') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('permissions.list') }}</h2>

                        @if ($permissions->isEmpty())
                            <x-empty-state
                                :title="__('permissions.empty_title')"
                                :text="__('permissions.empty_text')"
                                icon="bi-inbox"
                            />
                        @else
                            <div data-table-filter-root>
                                <x-table-toolbar
                                    id="employee-permissions-list"
                                    :search-placeholder="__('dashboard.hours.search_date')"
                                    :filters="[[
                                        'name' => 'status',
                                        'label' => __('permissions.fields.status'),
                                        'options' => [
                                            'PENDING' => __('permissions.statuses.PENDING'),
                                            'ACCEPTED' => __('permissions.statuses.ACCEPTED'),
                                            'REJECTED' => __('permissions.statuses.REJECTED'),
                                            'FORCED' => __('permissions.statuses.FORCED'),
                                        ],
                                    ]]"
                                />
                                <div class="table-responsive">
                                    <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
                                        <thead>
                                            <tr>
                                                <th scope="col" class="col-num">{{ __('permissions.fields.request_date') }}</th>
                                                <th scope="col">{{ __('permissions.fields.type') }}</th>
                                                <th scope="col" class="col-num">{{ __('permissions.fields.interval') }}</th>
                                                <th scope="col">{{ __('permissions.fields.status') }}</th>
                                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($permissions as $row)
                                                @php
                                                    $filterText = implode(' ', array_filter([
                                                        $row->request_date->toDateString(),
                                                        __('permissions.types.'.$row->type),
                                                        $row->from_time,
                                                        $row->to_time,
                                                        $row->reason,
                                                    ]));
                                                @endphp
                                                <tr data-filter-text="{{ $filterText }}"
                                                    data-filter-status="{{ $row->status }}">
                                                    <td class="col-num"><span class="num-ltr">{{ $row->request_date->toDateString() }}</span></td>
                                                    <td>{{ __('permissions.types.'.$row->type) }}</td>
                                                    <td class="col-num text-nowrap">
                                                        <span class="num-ltr">{{ $row->from_time }}–{{ $row->to_time }}</span>
                                                    </td>
                                                    <td>
                                                    <span @class([
                                                        'badge',
                                                        'text-bg-warning' => $row->status === 'PENDING',
                                                        'text-bg-success' => $row->status === 'ACCEPTED',
                                                        'text-bg-danger' => $row->status === 'REJECTED',
                                                        'text-bg-dark' => $row->status === 'FORCED',
                                                    ])>
                                                        {{ __('permissions.statuses.'.$row->status) }}
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    @if ($row->status === 'REJECTED')
                                                        <form method="POST"
                                                              action="{{ route('employee.requests.permissions.force', $row) }}"
                                                              class="d-inline"
                                                              onsubmit="return confirm(@json(__('permissions.force_confirm')))">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-dark">
                                                                {{ __('permissions.force') }}
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-body-secondary">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @if (filled($row->reason))
                                                <tr class="border-0"
                                                    data-filter-text="{{ $filterText }}"
                                                    data-filter-status="{{ $row->status }}">
                                                    <td colspan="5" class="small text-body-secondary pt-0">
                                                        {{ __('permissions.fields.reason') }}: {{ $row->reason }}
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        <tr data-table-empty-row class="d-none">
                                            <td colspan="5" class="text-body-secondary text-center py-3">
                                                {{ __('general.no_filter_results') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                            <div class="mt-3">
                                {{ $permissions->withQueryString()->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
