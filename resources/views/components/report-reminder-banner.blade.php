@php
    $reminder = $reminder ?? session('report_reminder');
@endphp
@if (is_array($reminder))
    @php
        $late = max(0, (int) ($reminder['late_minutes'] ?? 0));
        $early = max(0, (int) ($reminder['early_minutes'] ?? 0));
        $date = $reminder['date'] ?? now()->toDateString();
        $reportMissing = (bool) ($reminder['report_missing'] ?? true);

        if ($late > 0 && $early > 0) {
            $detail = __('report.reminder.both', ['late' => $late, 'early' => $early]);
        } elseif ($late > 0) {
            $detail = __('report.reminder.late', ['minutes' => $late]);
        } elseif ($early > 0) {
            $detail = __('report.reminder.early', ['minutes' => $early]);
        } else {
            $detail = __('report.reminder.on_time');
        }
    @endphp
    <div class="alert alert-warning border-warning border-start border-4 shadow-sm d-flex flex-wrap align-items-start gap-3 mb-4 report-reminder-banner"
         role="alert" aria-live="assertive">
        <span class="report-reminder-beacon rounded-circle bg-warning flex-shrink-0" aria-hidden="true"></span>
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-5" aria-hidden="true"></i>
                <strong>{{ __('report.reminder.title') }}</strong>
            </div>
            <p class="mb-2">{{ __('report.reminder.body') }}</p>
            <p class="mb-0 small">{{ $detail }}</p>
        </div>
        @if ($reportMissing)
            <a href="{{ route('employee.report', ['date' => $date]) }}"
               class="btn btn-warning btn-sm align-self-center">
                {{ __('report.reminder.action') }}
            </a>
        @endif
    </div>
    @once
        @push('scripts')
            <style>
                .report-reminder-beacon {
                    width: 0.75rem;
                    height: 0.75rem;
                    margin-top: 0.35rem;
                    animation: report-reminder-pulse 1.4s ease-in-out infinite;
                }
                @keyframes report-reminder-pulse {
                    0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(var(--bs-warning-rgb), 0.55); }
                    50% { opacity: 0.65; box-shadow: 0 0 0 0.45rem rgba(var(--bs-warning-rgb), 0); }
                }
            </style>
        @endpush
    @endonce
@endif
