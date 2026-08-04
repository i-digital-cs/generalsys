{{-- Salaries.xlsx-style attendance tallies beside evaluation. --}}
@php
    /** @var \App\Models\PayrollRecord $payroll */
    /** @var array<string, int|float>|null $attendanceSummary */
    $attendanceSummary = $attendanceSummary ?? null;
@endphp

@if ($attendanceSummary !== null)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent">
            <h2 class="h6 mb-0">{{ __('payroll.attendance_summary.title') }}</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('payroll.fields.full_time_days') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ (int) $payroll->full_time_days }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('payroll.fields.online_half_days') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ (int) $payroll->online_half_days }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('payroll.fields.online_full_days') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ (int) $payroll->online_full_days }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('payroll.fields.rest_days') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ (int) $payroll->rest_days }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('payroll.attendance_summary.vacation_days') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ (int) ($attendanceSummary['vacation_days'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('payroll.attendance_summary.official_holidays') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ (int) ($attendanceSummary['official_holiday_days'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('payroll.attendance_summary.excused_absence') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ (int) ($attendanceSummary['excused_absence_days'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('payroll.attendance_summary.unexcused_absence') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ (int) ($attendanceSummary['unexcused_absence_days'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('payroll.fields.late_minutes_total') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ (int) $payroll->late_minutes_total }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('payroll.fields.hours_deduction') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ number_format((float) $payroll->hours_deduction, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
