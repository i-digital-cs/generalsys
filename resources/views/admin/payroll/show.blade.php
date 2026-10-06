@extends('layouts.app')

@section('title', __('payroll.breakdown'))

@section('content')
    @php
        $emp = $payroll->user;
        $name = $emp
            ? (app()->getLocale() === 'ar' ? $emp->name_ar : $emp->name_en)
            : __('general.not_available');
        $period = \Carbon\Carbon::create($payroll->year, $payroll->month, 1)->translatedFormat('F Y');
        $earnings = [
            'full_time_pay',
            'online_half_pay',
            'online_full_pay',
            'overtime_pay',
            'friday_bonus',
            'day_off_bonus',
            'rest_day_pay',
            'official_holiday_bonus',
            'extra_pay',
        ];
        $deductions = [
            'late_penalty',
            'leave_permission_penalty',
            'late_permission_penalty',
            'excused_absence_penalty',
            'unexcused_absence_penalty',
            'forced_penalty_surcharge',
            'hours_deduction_amount',
            'advance_deductions',
        ];
        $countFieldsAttendance = [
            'full_time_days',
            'online_half_days',
            'online_full_days',
            'rest_days',
            'friday_work_days',
            'day_off_work_days',
            'official_holiday_work_days',
            'late_minutes_total',
        ];
        $countFieldsReadonly = [
            ...$countFieldsAttendance,
            'excused_absence_days',
            'unexcused_absence_days',
            'hours_deduction',
            'hours_deduction_amount',
            'extra_days',
            'extra_pay',
            'overtime_hours',
            'overtime_multiplier',
        ];
        $dayRate = $emp ? round(((float) $emp->base_salary) / 30, 2) : 0;
        $plannedHours = (float) config('payroll.planned_hours_full_time', 8);
        $otHourly = $dayRate > 0 ? round($dayRate / max($plannedHours, 1), 4) : 0;
        // EXTRA + OT attendance pay the same formula — one editable total.
        $otHoursTotal = (float) $payroll->overtime_hours + (float) $payroll->extra_shift_hours;
        $otMult = (float) $payroll->overtime_multiplier > 0
            ? (float) $payroll->overtime_multiplier
            : (float) app(\App\Services\Admin\SettingService::class)->multiplier('overtime');
        $otFormulaPay = round($otHoursTotal * $otHourly * $otMult, 2);
        $extraPayMult = (float) app(\App\Services\Admin\SettingService::class)->multiplier('extra_pay');
        $extraPayDisplay = (int) $payroll->extra_days > 0
            ? round($dayRate * (int) $payroll->extra_days * $extraPayMult, 2)
            : (float) $payroll->extra_pay;
        $hourlyDeductionRate = $dayRate > 0 ? round($dayRate / max($plannedHours, 1), 4) : 0;
        $excusedPreview = round($dayRate * (int) $payroll->excused_absence_days, 2);
        $unexcusedRuleMult = (float) (app(\App\Services\Payroll\PenaltyRuleService::class)
            ->findActive(\App\Models\PenaltyRule::TYPE_UNEXCUSED_ABSENCE, $payroll->currency ?: 'EGP')
            ?->amount_per_unit ?? 3.0);
        $unexcusedPreview = round($dayRate * (int) $payroll->unexcused_absence_days * $unexcusedRuleMult, 2);
        $hoursDeductionPreview = round($hourlyDeductionRate * (float) $payroll->hours_deduction, 2);
        $otherDeductionRows = $payroll->adjustments
            ->where('type', \App\Models\PayrollAdjustment::TYPE_OTHER_DEDUCTION)
            ->filter(fn ($adj) => abs((float) $adj->amount) >= 0.005)
            ->values();
        $activeEarnings = collect($earnings)->filter(fn ($key) => (float) $payroll->{$key} != 0.0);
        $activeDeductions = collect($deductions)->filter(fn ($key) => (float) $payroll->{$key} != 0.0);
        $defaultTab = $editable ? 'edit' : 'pay';
    @endphp

    <div class="payroll-review">
        {{-- Sticky action header --}}
        <div class="payroll-review-toolbar card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <a href="{{ route('admin.payroll.index', ['month' => $payroll->month, 'year' => $payroll->year]) }}"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}" aria-hidden="true"></i>
                                <span class="d-none d-sm-inline">{{ __('payroll.back_to_list') }}</span>
                            </a>
                            <h1 class="h5 mb-0 text-truncate">{{ $name }}</h1>
                            <span @class([
                                'badge',
                                'text-bg-secondary' => $payroll->status === 'DRAFT',
                                'text-bg-success' => $payroll->status === 'APPROVED',
                                'text-bg-primary' => $payroll->status === 'PAID',
                            ])>{{ __('payroll.status.'.$payroll->status) }}</span>
                        </div>
                        <p class="small text-body-secondary mb-0">
                            {{ $period }} · {{ __('payroll.breakdown') }}
                            @if ($emp)
                                · {{ __('employees.team_leader') }}:
                                {{ $emp->teamLeaderDisplayNames() !== '' ? $emp->teamLeaderDisplayNames() : __('general.not_available') }}
                            @endif
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('admin.payroll.export.pdf', $payroll) }}" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>{{ __('payroll.export_pdf') }}
                        </a>
                        <a href="{{ route('admin.payroll.export.review.pdf', $payroll) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-journal-richtext me-1" aria-hidden="true"></i>{{ __('payroll.export_review_pdf') }}
                        </a>
                        @if ($editable && auth()->user()?->can('approve', $payroll))
                            <form method="POST" action="{{ route('admin.payroll.approve', $payroll) }}">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>{{ __('payroll.approve') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @unless ($editable)
            <div class="alert alert-secondary py-2">{{ __('payroll.locked_note') }}</div>
        @endunless

        {{-- Net hero --}}
        <div class="payroll-review-hero card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md">
                        <div class="payroll-review-hero-label">{{ __('payroll.fields.net') }}</div>
                        <div class="payroll-review-hero-net num-ltr">
                            <x-money :amount="$payroll->net_pay" :currency="$payroll->currency" />
                        </div>
                    </div>
                    <div class="col-6 col-md-auto">
                        <div class="payroll-review-metric">
                            <span class="label">{{ __('payroll.fields.base_salary') }}</span>
                            <span class="value num-ltr"><x-money :amount="$payroll->base_salary" :currency="$payroll->currency" /></span>
                        </div>
                    </div>
                    <div class="col-6 col-md-auto">
                        <div class="payroll-review-metric">
                            <span class="label">{{ __('payroll.fields.gross') }}</span>
                            <span class="value num-ltr"><x-money :amount="$payroll->gross_pay" :currency="$payroll->currency" /></span>
                        </div>
                    </div>
                    <div class="col-6 col-md-auto">
                        <div class="payroll-review-metric">
                            <span class="label">{{ __('payroll.fields.deductions') }}</span>
                            <span class="value num-ltr"><x-money :amount="$payroll->total_deductions" :currency="$payroll->currency" /></span>
                        </div>
                    </div>
                    @if ((float) $payroll->advance_deductions > 0)
                        <div class="col-6 col-md-auto">
                            <div class="payroll-review-metric is-warn">
                                <span class="label">{{ __('payroll.fields.advance_deductions') }}</span>
                                <span class="value num-ltr"><x-money :amount="$payroll->advance_deductions" :currency="$payroll->currency" /></span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Section tabs --}}
        <ul class="nav nav-pills payroll-review-tabs flex-nowrap overflow-auto gap-1 mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link @if ($defaultTab === 'pay') active @endif" id="tab-pay"
                        data-bs-toggle="tab" data-bs-target="#pane-pay" type="button" role="tab"
                        aria-controls="pane-pay" @if ($defaultTab === 'pay') aria-selected="true" @endif>
                    {{ __('payroll.tabs.pay') }}
                </button>
            </li>
            @if ($editable)
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if ($defaultTab === 'edit') active @endif" id="tab-edit"
                            data-bs-toggle="tab" data-bs-target="#pane-edit" type="button" role="tab"
                            aria-controls="pane-edit" @if ($defaultTab === 'edit') aria-selected="true" @endif>
                        {{ __('payroll.tabs.edit') }}
                    </button>
                </li>
            @endif
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-attendance" data-bs-toggle="tab" data-bs-target="#pane-attendance"
                        type="button" role="tab" aria-controls="pane-attendance">
                    {{ __('payroll.tabs.attendance') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-evaluation" data-bs-toggle="tab" data-bs-target="#pane-evaluation"
                        type="button" role="tab" aria-controls="pane-evaluation">
                    {{ __('payroll.tabs.evaluation') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-hours" data-bs-toggle="tab" data-bs-target="#pane-hours"
                        type="button" role="tab" aria-controls="pane-hours">
                    {{ __('payroll.tabs.hours') }}
                </button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- Pay breakdown --}}
            <div class="tab-pane fade @if ($defaultTab === 'pay') show active @endif" id="pane-pay" role="tabpanel"
                 aria-labelledby="tab-pay" tabindex="0">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-transparent fw-semibold d-flex align-items-center gap-2">
                                <i class="bi bi-plus-circle text-success" aria-hidden="true"></i>
                                {{ __('payroll.earnings') }}
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hr table-sm mb-0 align-middle">
                                        <tbody>
                                            @forelse ($activeEarnings as $key)
                                                <tr>
                                                    <th scope="row" class="ps-3">{{ __('payroll.fields.'.$key) }}</th>
                                                    <td class="col-num pe-3">
                                                        <span class="num-ltr"><x-money :amount="$payroll->{$key}" :currency="$payroll->currency" /></span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="2" class="text-body-secondary text-center py-3">
                                                        {{ __('payroll.zero_earnings') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                            @if ($activeEarnings->isNotEmpty() && $activeEarnings->count() < count($earnings))
                                                <tr class="small text-body-secondary">
                                                    <td colspan="2" class="ps-3 pe-3 py-2">{{ __('payroll.hidden_zeros') }}</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-transparent fw-semibold d-flex align-items-center gap-2">
                                <i class="bi bi-dash-circle text-danger" aria-hidden="true"></i>
                                {{ __('payroll.deductions_section') }}
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hr table-sm mb-0 align-middle">
                                        <tbody>
                                            @forelse ($activeDeductions as $key)
                                                <tr @class(['table-warning' => $key === 'advance_deductions'])>
                                                    <th scope="row" class="ps-3">{{ __('payroll.fields.'.$key) }}</th>
                                                    <td class="col-num pe-3">
                                                        <span class="num-ltr"><x-money :amount="$payroll->{$key}" :currency="$payroll->currency" /></span>
                                                    </td>
                                                </tr>
                                            @empty
                                                @if ($otherDeductionRows->isEmpty())
                                                    <tr>
                                                        <td colspan="2" class="text-body-secondary text-center py-3">
                                                            {{ __('payroll.zero_deductions') }}
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforelse
                                            @foreach ($otherDeductionRows as $adj)
                                                <tr>
                                                    <th scope="row" class="ps-3">
                                                        {{ __('payroll.adjustment_types.OTHER_DEDUCTION') }}
                                                        @if (filled($adj->note))
                                                            <span class="fw-normal text-body-secondary"> — {{ $adj->note }}</span>
                                                        @endif
                                                    </th>
                                                    <td class="col-num pe-3">
                                                        <span class="num-ltr"><x-money :amount="abs((float) $adj->amount)" :currency="$payroll->currency" /></span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            <tr class="fw-semibold table-light">
                                                <th scope="row" class="ps-3">{{ __('payroll.fields.deductions') }}</th>
                                                <td class="col-num pe-3">
                                                    <span class="num-ltr"><x-money :amount="$payroll->total_deductions" :currency="$payroll->currency" /></span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @unless ($editable)
                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-header bg-transparent fw-semibold">{{ __('payroll.counts') }}</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hr table-sm mb-0 align-middle">
                                    <tbody>
                                        @foreach ($countFieldsReadonly as $field)
                                            @if ((float) $payroll->{$field} != 0.0)
                                                <tr>
                                                    <th scope="row" class="ps-3">{{ __('payroll.fields.'.$field) }}</th>
                                                    <td class="col-num pe-3"><span class="num-ltr">{{ $payroll->{$field} }}</span></td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endunless
            </div>

            {{-- Edit (draft only) --}}
            @if ($editable)
                <div class="tab-pane fade @if ($defaultTab === 'edit') show active @endif" id="pane-edit" role="tabpanel"
                     aria-labelledby="tab-edit" tabindex="0">
                    <div class="row g-3">
                        <div class="col-12 col-xl-7">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-transparent">
                                    <div class="fw-semibold">{{ __('payroll.counts') }}</div>
                                    <p class="small text-body-secondary mb-0">{{ __('payroll.counts_hint') }}</p>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('admin.payroll.update', $payroll) }}" class="row g-3">
                                        @csrf
                                        @method('PUT')

                                        <div class="col-12">
                                            <div class="fw-semibold small mb-2">{{ __('payroll.sections.attendance') }}</div>
                                            <div class="row g-2">
                                                @foreach ($countFieldsAttendance as $field)
                                                    <div class="col-6 col-md-4">
                                                        <label for="{{ $field }}" class="form-label">{{ __('payroll.fields.'.$field) }}</label>
                                                        <input
                                                            type="number"
                                                            step="{{ $field === 'hours_deduction' ? '0.01' : '1' }}"
                                                            min="0"
                                                            id="{{ $field }}"
                                                            name="{{ $field }}"
                                                            class="form-control @error($field) is-invalid @enderror"
                                                            value="{{ old($field, $payroll->{$field}) }}"
                                                            required
                                                        >
                                                        @error($field)
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="border rounded-3 p-3 bg-body-tertiary"
                                                 data-extra-pay-box
                                                 data-day-rate="{{ number_format($dayRate, 4, '.', '') }}"
                                                 data-extra-mult="{{ number_format($extraPayMult, 4, '.', '') }}">
                                                <div class="fw-semibold small mb-1">{{ __('payroll.sections.excel_extra') }}</div>
                                                <p class="small text-body-secondary mb-2">{{ __('payroll.sections.excel_extra_hint', ['rate' => number_format($dayRate, 2)]) }}</p>
                                                <div class="row g-2">
                                                    <div class="col-6 col-md-4">
                                                        <label for="extra_days" class="form-label">{{ __('payroll.fields.extra_days') }}</label>
                                                        <input type="number" step="1" min="0" id="extra_days" name="extra_days"
                                                               class="form-control @error('extra_days') is-invalid @enderror"
                                                               value="{{ old('extra_days', $payroll->extra_days) }}"
                                                               data-extra-days
                                                               required>
                                                        <div class="form-text">{{ __('payroll.field_help.extra_days') }}</div>
                                                        @error('extra_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-6 col-md-4">
                                                        <label for="extra_pay" class="form-label">{{ __('payroll.fields.extra_pay') }}</label>
                                                        <input type="number" step="0.01" min="0" id="extra_pay" name="extra_pay"
                                                               class="form-control @error('extra_pay') is-invalid @enderror"
                                                               value="{{ old('extra_pay', number_format($extraPayDisplay, 2, '.', '')) }}"
                                                               data-extra-pay
                                                               @if ((int) old('extra_days', $payroll->extra_days) > 0) readonly @endif
                                                               required>
                                                        <div class="form-text" data-extra-pay-help>
                                                            {{ __('payroll.field_help.extra_pay', ['rate' => number_format($dayRate, 2)]) }}
                                                        </div>
                                                        @error('extra_pay') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="border rounded-3 p-3">
                                                <div class="fw-semibold small mb-1">{{ __('payroll.sections.overtime') }}</div>
                                                <p class="small text-body-secondary mb-2">
                                                    {{ __('payroll.ot_formula_hint', [
                                                        'hours' => number_format($otHoursTotal, 2),
                                                        'rate' => number_format($otHourly, 2),
                                                        'mult' => number_format($otMult, 2),
                                                        'pay' => number_format($otFormulaPay, 2),
                                                    ]) }}
                                                </p>
                                                <div class="row g-2">
                                                    <div class="col-6 col-md-4">
                                                        <label for="overtime_hours" class="form-label">{{ __('payroll.fields.overtime_hours') }}</label>
                                                        <input type="number" step="0.01" min="0" id="overtime_hours" name="overtime_hours"
                                                               class="form-control @error('overtime_hours') is-invalid @enderror"
                                                               value="{{ old('overtime_hours', number_format($otHoursTotal, 2, '.', '')) }}" required>
                                                        <div class="form-text">{{ __('payroll.field_help.overtime_hours') }}</div>
                                                        @error('overtime_hours') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-6 col-md-4">
                                                        <label for="overtime_multiplier" class="form-label">{{ __('payroll.fields.overtime_multiplier') }}</label>
                                                        <input type="number" step="0.01" min="0" id="overtime_multiplier" name="overtime_multiplier"
                                                               class="form-control @error('overtime_multiplier') is-invalid @enderror"
                                                               value="{{ old('overtime_multiplier', number_format($otMult, 2, '.', '')) }}" required>
                                                        <div class="form-text">{{ __('payroll.field_help.overtime_multiplier') }}</div>
                                                        @error('overtime_multiplier') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="border rounded-3 p-3 border-danger-subtle"
                                                 data-excel-deductions
                                                 data-day-rate="{{ number_format($dayRate, 4, '.', '') }}"
                                                 data-hourly-rate="{{ number_format($hourlyDeductionRate, 4, '.', '') }}"
                                                 data-unexcused-mult="{{ number_format($unexcusedRuleMult, 2, '.', '') }}">
                                                <div class="fw-semibold small mb-1">{{ __('payroll.sections.excel_deductions') }}</div>
                                                <p class="small text-body-secondary mb-2">{{ __('payroll.sections.excel_deductions_hint', [
                                                    'rate' => number_format($dayRate, 2),
                                                    'hourly' => number_format($hourlyDeductionRate, 2),
                                                    'unexcused_mult' => number_format($unexcusedRuleMult, 2),
                                                ]) }}</p>
                                                <div class="row g-2">
                                                    <div class="col-6 col-md-4">
                                                        <label for="excused_absence_days" class="form-label">{{ __('payroll.fields.excused_absence_days') }}</label>
                                                        <input type="number" step="1" min="0" id="excused_absence_days" name="excused_absence_days"
                                                               class="form-control @error('excused_absence_days') is-invalid @enderror"
                                                               value="{{ old('excused_absence_days', $payroll->excused_absence_days) }}"
                                                               data-excused-days required>
                                                        <div class="form-text">
                                                            {{ __('payroll.field_help.excused_absence_days') }}
                                                            <span class="num-ltr d-block" data-excused-preview>{{ number_format($excusedPreview, 2) }}</span>
                                                        </div>
                                                        @error('excused_absence_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-6 col-md-4">
                                                        <label for="unexcused_absence_days" class="form-label">{{ __('payroll.fields.unexcused_absence_days') }}</label>
                                                        <input type="number" step="1" min="0" id="unexcused_absence_days" name="unexcused_absence_days"
                                                               class="form-control @error('unexcused_absence_days') is-invalid @enderror"
                                                               value="{{ old('unexcused_absence_days', $payroll->unexcused_absence_days) }}"
                                                               data-unexcused-days required>
                                                        <div class="form-text">
                                                            {{ __('payroll.field_help.unexcused_absence_days', ['mult' => number_format($unexcusedRuleMult, 2)]) }}
                                                            <span class="num-ltr d-block" data-unexcused-preview>{{ number_format($unexcusedPreview, 2) }}</span>
                                                        </div>
                                                        @error('unexcused_absence_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-6 col-md-4">
                                                        <label for="hours_deduction" class="form-label">{{ __('payroll.fields.hours_deduction') }}</label>
                                                        <input type="number" step="0.01" min="0" id="hours_deduction" name="hours_deduction"
                                                               class="form-control @error('hours_deduction') is-invalid @enderror"
                                                               value="{{ old('hours_deduction', $payroll->hours_deduction) }}"
                                                               data-hours-deduction required>
                                                        <div class="form-text">
                                                            {{ __('payroll.field_help.hours_deduction', ['hourly' => number_format($hourlyDeductionRate, 2)]) }}
                                                            <span class="num-ltr d-block" data-hours-preview>{{ number_format($hoursDeductionPreview, 2) }}</span>
                                                        </div>
                                                        @error('hours_deduction') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 pt-1 d-flex flex-wrap gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>{{ __('payroll.save_counts') }}
                                            </button>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('admin.payroll.refresh-attendance', $payroll) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm"
                                                onclick="return confirm(@json(__('payroll.refresh_attendance_confirm')))">
                                            <i class="bi bi-cloud-download me-1" aria-hidden="true"></i>{{ __('payroll.refresh_attendance') }}
                                        </button>
                                        <div class="form-text">{{ __('payroll.refresh_attendance_hint') }}</div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-transparent">
                                    <div class="fw-semibold">{{ __('payroll.adjustments') }}</div>
                                    <p class="small text-body-secondary mb-0">{{ __('payroll.adjustments_hint') }}</p>
                                </div>
                                <div class="card-body">
                                    @if ($payroll->adjustments->isEmpty())
                                        <p class="text-body-secondary small">{{ __('payroll.empty_adjustments') }}</p>
                                    @else
                                        <div class="table-responsive mb-3">
                                            <table class="table table-hr table-sm align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">{{ __('payroll.fields.type') }}</th>
                                                        <th scope="col" class="col-num">{{ __('payroll.fields.amount') }}</th>
                                                        <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($payroll->adjustments as $adj)
                                                        <tr>
                                                            <td>
                                                                <div>{{ __('payroll.adjustment_types.'.$adj->type) }}</div>
                                                                @if ($adj->note)
                                                                    <div class="small text-body-secondary">{{ $adj->note }}</div>
                                                                @endif
                                                            </td>
                                                            <td class="col-num">
                                                                <span class="num-ltr"><x-money :amount="$adj->amount" :currency="$payroll->currency" /></span>
                                                            </td>
                                                            <td class="text-end">
                                                                <form method="POST"
                                                                      action="{{ route('admin.payroll.adjustments.destroy', [$payroll, $adj]) }}"
                                                                      class="d-inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                        {{ __('payroll.remove_adjustment') }}
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('admin.payroll.adjustments.store', $payroll) }}" class="row g-2">
                                        @csrf
                                        <div class="col-12">
                                            <label for="type" class="form-label">{{ __('payroll.fields.type') }}</label>
                                            <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                                                @foreach (array_keys(__('payroll.adjustment_types')) as $type)
                                                    <option value="{{ $type }}" @selected(old('type') === $type)>
                                                        {{ __('payroll.adjustment_types.'.$type) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('type')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-6">
                                            <label for="amount" class="form-label">{{ __('payroll.fields.amount') }}</label>
                                            <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                                                   class="form-control @error('amount') is-invalid @enderror"
                                                   value="{{ old('amount') }}" required>
                                            @error('amount')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-6">
                                            <label for="note" class="form-label">{{ __('payroll.fields.note') }}</label>
                                            <input type="text" id="note" name="note" maxlength="255"
                                                   class="form-control @error('note') is-invalid @enderror"
                                                   value="{{ old('note') }}">
                                            @error('note')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-outline-primary w-100">
                                                {{ __('payroll.add_adjustment') }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Attendance --}}
            <div class="tab-pane fade" id="pane-attendance" role="tabpanel" aria-labelledby="tab-attendance" tabindex="0">
                @include('payroll._attendance_summary', [
                    'payroll' => $payroll,
                    'attendanceSummary' => $attendanceSummary ?? null,
                ])
            </div>

            {{-- Evaluation --}}
            <div class="tab-pane fade" id="pane-evaluation" role="tabpanel" aria-labelledby="tab-evaluation" tabindex="0">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        @include('payroll._monthly_evaluation', [
                            'scores' => $scores ?? null,
                            'evaluationText' => $evaluationText ?? null,
                            'yearAverage' => $yearAverage ?? null,
                            'evaluationSaved' => $evaluationSaved ?? false,
                            'evaluationSubject' => $payroll->user,
                            'evaluationMonth' => (int) $payroll->month,
                            'evaluationYear' => (int) $payroll->year,
                        ])
                    </div>
                    <div class="col-12 col-lg-6">
                        @include('payroll._annual_evaluation', [
                            'monthlyTotals' => $monthlyTotals ?? null,
                            'yearAverage' => $yearAverage ?? null,
                        ])
                    </div>
                </div>
            </div>

            {{-- Hours --}}
            <div class="tab-pane fade" id="pane-hours" role="tabpanel" aria-labelledby="tab-hours" tabindex="0">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-transparent fw-semibold">{{ __('payroll.ot_formula_title') }}</div>
                    <div class="card-body">
                        <p class="mb-2 font-monospace num-ltr">
                            {{ number_format($otHoursTotal, 2) }}
                            × {{ number_format($otHourly, 2) }}
                            × {{ number_format($otMult, 2) }}
                            = {{ number_format($otFormulaPay, 2) }}
                        </p>
                        <p class="small text-body-secondary mb-0">{{ __('payroll.ot_formula_legend') }}</p>
                    </div>
                </div>
                @include('payroll._hours_comparison', [
                    'hoursMonth' => $hoursMonth ?? null,
                ])
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const box = document.querySelector('[data-extra-pay-box]');
    if (box) {
        const daysInput = box.querySelector('[data-extra-days]');
        const payInput = box.querySelector('[data-extra-pay]');
        if (daysInput && payInput) {
            const dayRate = Number(box.dataset.dayRate || 0);
            const mult = Number(box.dataset.extraMult || 1);
            const sync = () => {
                const days = Math.max(0, parseInt(daysInput.value, 10) || 0);
                if (days > 0) {
                    const amount = Math.round(dayRate * days * mult * 100) / 100;
                    payInput.value = amount.toFixed(2);
                    payInput.readOnly = true;
                    payInput.classList.add('bg-body-secondary');
                } else {
                    payInput.readOnly = false;
                    payInput.classList.remove('bg-body-secondary');
                }
            };
            daysInput.addEventListener('input', sync);
            daysInput.addEventListener('change', sync);
            sync();
        }
    }

    const ded = document.querySelector('[data-excel-deductions]');
    if (ded) {
        const dayRate = Number(ded.dataset.dayRate || 0);
        const hourly = Number(ded.dataset.hourlyRate || 0);
        const unexcusedMult = Number(ded.dataset.unexcusedMult || 3);
        const excusedDays = ded.querySelector('[data-excused-days]');
        const unexcusedDays = ded.querySelector('[data-unexcused-days]');
        const hoursInput = ded.querySelector('[data-hours-deduction]');
        const excusedPrev = ded.querySelector('[data-excused-preview]');
        const unexcusedPrev = ded.querySelector('[data-unexcused-preview]');
        const hoursPrev = ded.querySelector('[data-hours-preview]');

        const fmt = (n) => (Math.round(n * 100) / 100).toFixed(2);
        const syncDed = () => {
            if (excusedDays && excusedPrev) {
                excusedPrev.textContent = fmt(dayRate * (parseInt(excusedDays.value, 10) || 0));
            }
            if (unexcusedDays && unexcusedPrev) {
                unexcusedPrev.textContent = fmt(dayRate * (parseInt(unexcusedDays.value, 10) || 0) * unexcusedMult);
            }
            if (hoursInput && hoursPrev) {
                hoursPrev.textContent = fmt(hourly * (parseFloat(hoursInput.value) || 0));
            }
        };
        [excusedDays, unexcusedDays, hoursInput].forEach((el) => {
            if (!el) return;
            el.addEventListener('input', syncDed);
            el.addEventListener('change', syncDed);
        });
        syncDed();
    }
})();
</script>
@endpush
