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
            @if ($vacationBalance)
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 mb-3">{{ __('vacations.credit_title', ['year' => $vacationBalanceYear]) }}</h2>
                            <div class="row g-3 text-center text-md-start">
                                <div class="col-6 col-md-3">
                                    <div class="small text-body-secondary">{{ __('vacations.credit_accrued') }}</div>
                                    <div class="fs-5 fw-semibold num-ltr">{{ number_format((float) $vacationBalance->accrued_days, 1) }}</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-body-secondary">{{ __('vacations.credit_used') }}</div>
                                    <div class="fs-5 fw-semibold num-ltr">{{ number_format((float) $vacationBalance->used_days, 1) }}</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-body-secondary">{{ __('vacations.credit_pending') }}</div>
                                    <div class="fs-5 fw-semibold num-ltr">{{ number_format((float) $vacationBalance->pending_days, 1) }}</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-body-secondary">{{ __('vacations.credit_remaining') }}</div>
                                    <div class="fs-5 fw-semibold num-ltr text-primary">{{ number_format((float) $vacationBalance->remaining_days, 1) }}</div>
                                </div>
                            </div>
                            <p class="small text-body-secondary mb-0 mt-2">{{ __('vacations.credit_hint') }}</p>
                        </div>
                    </div>
                </div>
            @endif

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
                            <p class="small text-body-secondary mb-0 mt-2">{{ __('vacations.days_hint') }}</p>
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
                                        class="form-select @error('type') is-invalid @enderror" required
                                        data-vacation-type-select>
                                    @foreach (['ANNUAL', 'SICK', 'EMERGENCY'] as $type)
                                        <option value="{{ $type }}" @selected(old('type', 'ANNUAL') === $type)>
                                            {{ __('vacations.types.'.$type) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3" data-vacation-annual-fields>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label for="annual_start_date" class="form-label">{{ __('vacations.fields.start_date') }}</label>
                                        <input type="date" id="annual_start_date"
                                               value="{{ old('type', 'ANNUAL') === 'ANNUAL' ? old('start_date', now()->toDateString()) : now()->toDateString() }}"
                                               class="form-control @error('start_date') is-invalid @enderror"
                                               data-vacation-annual-start dir="ltr">
                                    </div>
                                    <div class="col-6">
                                        <label for="annual_end_date" class="form-label">{{ __('vacations.fields.end_date') }}</label>
                                        <input type="date" id="annual_end_date"
                                               value="{{ old('type', 'ANNUAL') === 'ANNUAL' ? old('end_date', old('start_date', now()->toDateString())) : now()->toDateString() }}"
                                               class="form-control @error('end_date') is-invalid @enderror"
                                               data-vacation-annual-end dir="ltr">
                                    </div>
                                </div>
                                @error('start_date')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('end_date')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <p class="form-text mb-0 mt-2" data-vacation-annual-days></p>
                                <p class="form-text mb-0 d-none" data-vacation-hint-annual>{{ __('vacations.annual_hint') }}</p>
                                <p class="form-text mb-0 d-none" data-vacation-hint-sick>{{ __('vacations.sick_hint') }}</p>
                            </div>

                            <div class="mb-3 d-none" data-vacation-swap-fields>
                                <label for="swap_start_date" class="form-label">{{ __('vacations.fields.off_date') }}</label>
                                <input type="date" id="swap_start_date"
                                       value="{{ old('type', 'ANNUAL') !== 'ANNUAL' ? old('start_date', now()->toDateString()) : now()->toDateString() }}"
                                       class="form-control @error('start_date') is-invalid @enderror"
                                       data-vacation-swap-start dir="ltr">
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <input type="hidden" name="start_date" id="vacation_start_date"
                                   value="{{ old('start_date', now()->toDateString()) }}">
                            <input type="hidden" name="end_date" id="vacation_end_date"
                                   value="{{ old('end_date', old('start_date', now()->toDateString())) }}">
                            <input type="hidden" name="days_requested" id="days_requested"
                                   value="{{ old('days_requested', '1') }}">

                            <div class="mb-3 d-none" data-vacation-swap-fields>
                                <label for="replace_rest_day_id" class="form-label">{{ __('vacations.fields.replace_rest_day') }}</label>
                                <select id="replace_rest_day_id" name="replace_rest_day_id"
                                        class="form-select @error('replace_rest_day_id') is-invalid @enderror"
                                        data-vacation-rest-select
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

                            <button type="submit" class="btn btn-primary" data-vacation-submit>
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
                                                        <span class="num-ltr" dir="ltr">
                                                            @if ($row->start_date->equalTo($row->end_date))
                                                                {{ $row->start_date->toDateString() }}
                                                            @else
                                                                {{ $row->start_date->toDateString() }} – {{ $row->end_date->toDateString() }}
                                                            @endif
                                                        </span>
                                                        <div class="small text-body-secondary">
                                                            {{ number_format((float) $row->days_requested, 1) }} {{ __('vacations.fields.days_requested') }}
                                                        </div>
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
                                                    @if ($row->decidedBy)
                                                        <div class="small text-body-secondary mt-1">
                                                            {{ __('vacations.fields.decided_by') }}:
                                                            {{ $row->decidedBy->localizedName() }}
                                                        </div>
                                                    @endif
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
                    const form = document.getElementById('vacation-request-form');
                    if (!form) return;

                    const typeSelect = form.querySelector('[data-vacation-type-select]');
                    const annualBlocks = form.querySelectorAll('[data-vacation-annual-fields]');
                    const swapBlocks = form.querySelectorAll('[data-vacation-swap-fields]');
                    const annualStart = form.querySelector('[data-vacation-annual-start]');
                    const annualEnd = form.querySelector('[data-vacation-annual-end]');
                    const swapStart = form.querySelector('[data-vacation-swap-start]');
                    const hiddenStart = document.getElementById('vacation_start_date');
                    const hiddenEnd = document.getElementById('vacation_end_date');
                    const hiddenDays = document.getElementById('days_requested');
                    const restSelect = form.querySelector('[data-vacation-rest-select]');
                    const submitBtn = form.querySelector('[data-vacation-submit]');
                    const daysPreview = form.querySelector('[data-vacation-annual-days]');
                    const hintAnnual = form.querySelector('[data-vacation-hint-annual]');
                    const hintSick = form.querySelector('[data-vacation-hint-sick]');
                    const daysLabel = @json(__('vacations.annual_days_preview'));
                    const hasRestDays = @json($availableRestDays->isNotEmpty());
                    const balanceTypes = @json(['ANNUAL', 'SICK']);

                    const isRangeType = (type) => balanceTypes.includes(type);

                    const calcDays = (startVal, endVal) => {
                        if (!startVal || !endVal) return 0;
                        const from = new Date(startVal + 'T00:00:00');
                        const to = new Date(endVal + 'T00:00:00');
                        return Math.max(0, Math.round((to - from) / 86400000) + 1);
                    };

                    const syncAnnual = () => {
                        if (!annualStart || !annualEnd || !hiddenStart || !hiddenEnd || !hiddenDays) return;
                        hiddenStart.value = annualStart.value;
                        hiddenEnd.value = annualEnd.value;
                        const days = calcDays(annualStart.value, annualEnd.value);
                        hiddenDays.value = String(days > 0 ? days : 1);
                        if (daysPreview) {
                            daysPreview.textContent = days > 0
                                ? daysLabel.replace(':days', String(days))
                                : '';
                        }
                    };

                    const syncSwap = () => {
                        if (!swapStart || !hiddenStart || !hiddenEnd || !hiddenDays) return;
                        hiddenStart.value = swapStart.value;
                        hiddenEnd.value = swapStart.value;
                        hiddenDays.value = '1';
                    };

                    const toggleMode = () => {
                        const type = typeSelect ? typeSelect.value : 'ANNUAL';
                        const isRange = isRangeType(type);
                        annualBlocks.forEach((el) => el.classList.toggle('d-none', !isRange));
                        swapBlocks.forEach((el) => el.classList.toggle('d-none', isRange));

                        if (hintAnnual) {
                            hintAnnual.classList.toggle('d-none', type !== 'ANNUAL');
                        }
                        if (hintSick) {
                            hintSick.classList.toggle('d-none', type !== 'SICK');
                        }

                        if (restSelect) {
                            restSelect.required = !isRange;
                            restSelect.disabled = isRange || !hasRestDays;
                            if (isRange) {
                                restSelect.value = '';
                            }
                        }

                        if (submitBtn) {
                            submitBtn.disabled = !isRange && !hasRestDays;
                        }

                        if (isRange) {
                            syncAnnual();
                        } else {
                            syncSwap();
                        }
                    };

                    typeSelect?.addEventListener('change', toggleMode);
                    annualStart?.addEventListener('change', syncAnnual);
                    annualEnd?.addEventListener('change', syncAnnual);
                    swapStart?.addEventListener('change', syncSwap);

                    toggleMode();
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
                            @php
                                $isAbsenceForm = old('type') === 'ABSENCE_PERMISSION';
                            @endphp

                            <div class="mb-3">
                                <label for="type" class="form-label">{{ __('permissions.fields.type') }}</label>
                                <select id="type" name="type"
                                        class="form-select @error('type') is-invalid @enderror" required
                                        data-permission-type
                                        onchange="window.hpSyncPermissionForm && window.hpSyncPermissionForm()">
                                    <option value="LEAVE_PERMISSION" @selected(old('type', 'LEAVE_PERMISSION') === 'LEAVE_PERMISSION')>
                                        {{ __('permissions.types.LEAVE_PERMISSION') }}
                                    </option>
                                    <option value="LATE_PERMISSION" @selected(old('type') === 'LATE_PERMISSION')>
                                        {{ __('permissions.types.LATE_PERMISSION') }}
                                    </option>
                                    <option value="ABSENCE_PERMISSION" @selected(old('type') === 'ABSENCE_PERMISSION')>
                                        {{ __('permissions.types.ABSENCE_PERMISSION') }}
                                    </option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Leave / late: single day --}}
                            <div class="mb-3{{ $isAbsenceForm ? ' d-none' : '' }}" data-permission-single-date>
                                <label for="request_date" class="form-label">{{ __('permissions.fields.request_date') }}</label>
                                <input type="date" id="request_date" name="request_date"
                                       value="{{ old('request_date', now()->toDateString()) }}"
                                       class="form-control @error('request_date') is-invalid @enderror"
                                       @unless($isAbsenceForm) required @endunless
                                       data-permission-date-single
                                       @if ($isAbsenceForm) disabled @endif>
                                @error('request_date')
                                    @unless($isAbsenceForm)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @endunless
                                @enderror
                            </div>

                            {{-- Absence: from → to (shown for Employee, TL, Admin on same form) --}}
                            <div class="row g-2 mb-3{{ $isAbsenceForm ? '' : ' d-none' }}" data-permission-period>
                                <div class="col-12 col-sm-6">
                                    <label for="absence_from_date" class="form-label">{{ __('permissions.fields.from_date') }}</label>
                                    <input type="date" id="absence_from_date" name="request_date"
                                           value="{{ old('request_date', now()->toDateString()) }}"
                                           class="form-control @error('request_date') is-invalid @enderror"
                                           data-permission-date-start
                                           @if ($isAbsenceForm) required @else disabled @endif>
                                    @error('request_date')
                                        @if ($isAbsenceForm)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @endif
                                    @enderror
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label for="absence_end_date" class="form-label">{{ __('permissions.fields.end_date') }}</label>
                                    <input type="date" id="absence_end_date" name="end_date"
                                           value="{{ old('end_date', old('request_date', now()->toDateString())) }}"
                                           class="form-control @error('end_date') is-invalid @enderror"
                                           data-permission-date-end
                                           @if ($isAbsenceForm) required @else disabled @endif>
                                    @error('end_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <div class="form-text">{{ __('permissions.absence_hint') }}</div>
                                </div>
                            </div>

                            <div class="row g-2 mb-3{{ $isAbsenceForm ? ' d-none' : '' }}" data-permission-times>
                                <div class="col-6">
                                    <label for="from_time" class="form-label">{{ __('permissions.fields.from_time') }}</label>
                                    <input type="time" id="from_time" name="from_time"
                                           value="{{ old('from_time') }}"
                                           class="form-control @error('from_time') is-invalid @enderror"
                                           data-permission-from
                                           @unless($isAbsenceForm) required @endunless>
                                    @error('from_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6">
                                    <label for="to_time" class="form-label">{{ __('permissions.fields.to_time') }}</label>
                                    <input type="time" id="to_time" name="to_time"
                                           value="{{ old('to_time') }}"
                                           class="form-control @error('to_time') is-invalid @enderror"
                                           data-permission-to
                                           @unless($isAbsenceForm) required @endunless>
                                    @error('to_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <script>
                                window.hpSyncPermissionForm = function () {
                                    const typeSelect = document.querySelector('[data-permission-type]');
                                    const singleDate = document.querySelector('[data-permission-single-date]');
                                    const periodBlock = document.querySelector('[data-permission-period]');
                                    const timesBlock = document.querySelector('[data-permission-times]');
                                    const singleInput = document.querySelector('[data-permission-date-single]');
                                    const startInput = document.querySelector('[data-permission-date-start]');
                                    const endInput = document.querySelector('[data-permission-date-end]');
                                    const fromInput = document.querySelector('[data-permission-from]');
                                    const toInput = document.querySelector('[data-permission-to]');
                                    if (!typeSelect || !singleDate || !periodBlock || !timesBlock) return;

                                    const isAbsence = typeSelect.value === 'ABSENCE_PERMISSION';
                                    singleDate.classList.toggle('d-none', isAbsence);
                                    periodBlock.classList.toggle('d-none', !isAbsence);
                                    timesBlock.classList.toggle('d-none', isAbsence);

                                    if (singleInput) {
                                        singleInput.disabled = isAbsence;
                                        singleInput.required = !isAbsence;
                                    }
                                    if (startInput) {
                                        startInput.disabled = !isAbsence;
                                        startInput.required = isAbsence;
                                        if (isAbsence && !startInput.value && singleInput && singleInput.value) {
                                            startInput.value = singleInput.value;
                                        }
                                    }
                                    if (endInput) {
                                        endInput.disabled = !isAbsence;
                                        endInput.required = isAbsence;
                                        if (isAbsence && !endInput.value && startInput && startInput.value) {
                                            endInput.value = startInput.value;
                                        }
                                    }
                                    if (fromInput) {
                                        fromInput.required = !isAbsence;
                                        if (isAbsence) fromInput.value = '';
                                    }
                                    if (toInput) {
                                        toInput.required = !isAbsence;
                                        if (isAbsence) toInput.value = '';
                                    }
                                };
                                window.hpSyncPermissionForm();
                            </script>

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
                                                <th scope="col" class="col-num">{{ __('permissions.fields.date_period') }}</th>
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
                                                        $row->isAbsence()
                                                            ? ($row->end_date ?? $row->request_date)->toDateString()
                                                            : null,
                                                        __('permissions.types.'.$row->type),
                                                        $row->from_time,
                                                        $row->to_time,
                                                        $row->reason,
                                                    ]));
                                                @endphp
                                                <tr data-filter-text="{{ $filterText }}"
                                                    data-filter-status="{{ $row->status }}">
                                                    <td class="col-num">
                                                        <span class="num-ltr">
                                                            @if ($row->isAbsence())
                                                                {{ $row->request_date->toDateString() }}
                                                                –
                                                                {{ ($row->end_date ?? $row->request_date)->toDateString() }}
                                                            @else
                                                                {{ $row->request_date->toDateString() }}
                                                            @endif
                                                        </span>
                                                    </td>
                                                    <td>{{ __('permissions.types.'.$row->type) }}</td>
                                                    <td class="col-num text-nowrap">
                                                        @if ($row->isAbsence())
                                                            <span class="text-body-secondary">
                                                                {{ __('permissions.full_day') }}
                                                                @if ($row->absenceDayCount() > 1)
                                                                    <span class="num-ltr">({{ $row->absenceDayCount() }})</span>
                                                                @endif
                                                            </span>
                                                        @else
                                                            <span class="num-ltr">{{ $row->from_time }}–{{ $row->to_time }}</span>
                                                        @endif
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
                                                    @if ($row->decidedBy)
                                                        <div class="small text-body-secondary mt-1">
                                                            {{ __('permissions.fields.decided_by') }}:
                                                            {{ $row->decidedBy->localizedName() }}
                                                        </div>
                                                    @endif
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
