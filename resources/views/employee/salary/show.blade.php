@extends('layouts.app')

@section('title', __('salary.breakdown'))

@section('content')
    @php
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
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('salary.breakdown') }}</h1>
            <p class="text-body-secondary small mb-0">
                {{ $period }}
                ·
                <span @class([
                    'badge',
                    'text-bg-success' => $payroll->status === 'APPROVED',
                    'text-bg-primary' => $payroll->status === 'PAID',
                ])>{{ __('payroll.status.'.$payroll->status) }}</span>
            </p>
            <p class="small text-body-secondary mb-0 mt-1">{{ __('salary.read_only_hint') }}</p>
        </div>
        <a href="{{ route('employee.salary') }}" class="btn btn-outline-secondary">{{ __('salary.back') }}</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-body-secondary small">{{ __('salary.fields.base_salary') }}</div>
                    <div class="fs-5" dir="ltr">
                        <x-money :amount="$payroll->base_salary" :currency="$payroll->currency" />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-body-secondary small">{{ __('salary.fields.gross') }}</div>
                    <div class="fs-5" dir="ltr">
                        <x-money :amount="$payroll->gross_pay" :currency="$payroll->currency" />
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-body-secondary small">{{ __('salary.fields.net') }}</div>
                    <div class="fs-5 fw-semibold" dir="ltr">
                        <x-money :amount="$payroll->net_pay" :currency="$payroll->currency" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
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
        </div>
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
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
                                    <th scope="row" class="ps-3">{{ __('salary.fields.deductions') }}</th>
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
    </div>

    @if ($payroll->adjustments->isNotEmpty())
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-transparent fw-semibold">{{ __('payroll.adjustments') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th scope="col" class="ps-3">{{ __('payroll.fields.type') }}</th>
                                <th scope="col">{{ __('payroll.fields.amount') }}</th>
                                <th scope="col">{{ __('payroll.fields.note') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payroll->adjustments as $adj)
                                <tr>
                                    <td class="ps-3">{{ __('payroll.adjustment_types.'.$adj->type) }}</td>
                                    <td dir="ltr">
                                        <x-money :amount="$adj->amount" :currency="$payroll->currency" />
                                    </td>
                                    <td>{{ $adj->note ?: __('general.not_available') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
