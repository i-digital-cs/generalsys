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
            'official_holiday_bonus',
        ];
        $deductions = [
            'late_penalty',
            'leave_permission_penalty',
            'late_permission_penalty',
            'excused_absence_penalty',
            'unexcused_absence_penalty',
            'forced_penalty_surcharge',
            'advance_deductions',
        ];
        $countFields = [
            'full_time_days',
            'online_half_days',
            'online_full_days',
            'rest_days',
            'friday_work_days',
            'day_off_work_days',
            'official_holiday_work_days',
            'late_minutes_total',
            'extra_shift_hours',
            'overtime_hours',
            'hours_deduction',
        ];
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $name }}</h1>
            <p class="text-body-secondary small mb-0">
                {{ $period }}
                ·
                <span @class([
                    'badge',
                    'text-bg-secondary' => $payroll->status === 'DRAFT',
                    'text-bg-success' => $payroll->status === 'APPROVED',
                    'text-bg-primary' => $payroll->status === 'PAID',
                ])>{{ __('payroll.status.'.$payroll->status) }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.payroll.index', ['month' => $payroll->month, 'year' => $payroll->year]) }}"
               class="btn btn-outline-secondary">
                {{ __('payroll.back_to_list') }}
            </a>
            @if ($editable)
                <form method="POST" action="{{ route('admin.payroll.approve', $payroll) }}">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="btn btn-success">{{ __('payroll.approve') }}</button>
                </form>
            @endif
        </div>
    </div>

    @unless ($editable)
        <div class="alert alert-secondary">{{ __('payroll.locked_note') }}</div>
    @endunless

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-body-secondary small">{{ __('payroll.fields.base_salary') }}</div>
                    <div class="fs-5" dir="ltr">
                        <x-money :amount="$payroll->base_salary" :currency="$payroll->currency" />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-body-secondary small">{{ __('payroll.fields.gross') }}</div>
                    <div class="fs-5" dir="ltr">
                        <x-money :amount="$payroll->gross_pay" :currency="$payroll->currency" />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-body-secondary small">{{ __('payroll.fields.net') }}</div>
                    <div class="fs-5 fw-semibold" dir="ltr">
                        <x-money :amount="$payroll->net_pay" :currency="$payroll->currency" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">{{ __('payroll.earnings') }}</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <tbody>
                                @foreach ($earnings as $key)
                                    <tr>
                                        <th scope="row" class="ps-3">{{ __('payroll.fields.'.$key) }}</th>
                                        <td class="text-end pe-3" dir="ltr">
                                            <x-money :amount="$payroll->{$key}" :currency="$payroll->currency" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">{{ __('payroll.deductions_section') }}</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <tbody>
                                @foreach ($deductions as $key)
                                    <tr>
                                        <th scope="row" class="ps-3">{{ __('payroll.fields.'.$key) }}</th>
                                        <td class="text-end pe-3" dir="ltr">
                                            <x-money :amount="$payroll->{$key}" :currency="$payroll->currency" />
                                        </td>
                                    </tr>
                                @endforeach
                                <tr class="fw-semibold">
                                    <th scope="row" class="ps-3">{{ __('payroll.fields.deductions') }}</th>
                                    <td class="text-end pe-3" dir="ltr">
                                        <x-money :amount="$payroll->total_deductions" :currency="$payroll->currency" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">{{ __('payroll.counts') }}</div>
                <div class="card-body">
                    @if ($editable)
                        <form method="POST" action="{{ route('admin.payroll.update', $payroll) }}" class="row g-2">
                            @csrf
                            @method('PUT')
                            @foreach ($countFields as $field)
                                <div class="col-6">
                                    <label for="{{ $field }}" class="form-label">{{ __('payroll.fields.'.$field) }}</label>
                                    <input
                                        type="number"
                                        step="{{ str_contains($field, 'hours') || $field === 'hours_deduction' ? '0.01' : '1' }}"
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
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">{{ __('payroll.save_counts') }}</button>
                            </div>
                        </form>
                    @else
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle">
                                <tbody>
                                    @foreach ($countFields as $field)
                                        <tr>
                                            <th scope="row">{{ __('payroll.fields.'.$field) }}</th>
                                            <td class="text-end" dir="ltr">{{ $payroll->{$field} }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">{{ __('payroll.adjustments') }}</div>
                <div class="card-body">
                    @if ($payroll->adjustments->isEmpty())
                        <p class="text-body-secondary small mb-3">{{ __('payroll.empty_adjustments') }}</p>
                    @else
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('payroll.fields.type') }}</th>
                                        <th scope="col">{{ __('payroll.fields.amount') }}</th>
                                        <th scope="col">{{ __('payroll.fields.note') }}</th>
                                        @if ($editable)
                                            <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payroll->adjustments as $adj)
                                        <tr>
                                            <td>{{ __('payroll.adjustment_types.'.$adj->type) }}</td>
                                            <td dir="ltr">
                                                <x-money :amount="$adj->amount" :currency="$payroll->currency" />
                                            </td>
                                            <td>{{ $adj->note ?: __('general.not_available') }}</td>
                                            @if ($editable)
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
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($editable)
                        <form method="POST" action="{{ route('admin.payroll.adjustments.store', $payroll) }}" class="row g-2">
                            @csrf
                            <div class="col-12 col-md-4">
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
                            <div class="col-6 col-md-3">
                                <label for="amount" class="form-label">{{ __('payroll.fields.amount') }}</label>
                                <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                                       class="form-control @error('amount') is-invalid @enderror"
                                       value="{{ old('amount') }}" required>
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="note" class="form-label">{{ __('payroll.fields.note') }}</label>
                                <input type="text" id="note" name="note" maxlength="255"
                                       class="form-control @error('note') is-invalid @enderror"
                                       value="{{ old('note') }}">
                                @error('note')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    {{ __('payroll.add_adjustment') }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
