@if ($missingReportsToday->isNotEmpty() || $missingReportsOverdue->isNotEmpty())
    <div class="card border-0 mb-3">
        <div class="card-header bg-transparent border-0 pt-3 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-warning" aria-hidden="true"></i>
            <h2 class="h6 mb-0">{{ __('dashboard.missing_reports.title') }}</h2>
        </div>
        <div class="card-body pt-2">
            @if ($missingReportsToday->isNotEmpty())
                <div class="alert alert-warning mb-2">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                        <span class="fw-semibold">
                            {{ __('dashboard.missing_reports.today') }}
                            <span class="num-ltr">({{ $missingReportsTodayDate->translatedFormat('d M Y') }})</span>
                        </span>
                        <a href="{{ route('team-leader.reports') }}" class="btn btn-sm btn-warning">
                            {{ __('dashboard.missing_reports.cta') }}
                        </a>
                    </div>
                    <ul class="mb-0">
                        @foreach ($missingReportsToday as $person)
                            <li>{{ app()->getLocale() === 'ar' ? $person->name_ar : $person->name_en }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($missingReportsOverdue->isNotEmpty())
                <div class="alert alert-danger mb-0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                        <span class="fw-semibold">
                            {{ __('dashboard.missing_reports.overdue') }}
                            <span class="num-ltr">({{ $missingReportsOverdueDate->translatedFormat('d M Y') }})</span>
                        </span>
                        <a href="{{ route('team-leader.reports') }}" class="btn btn-sm btn-danger">
                            {{ __('dashboard.missing_reports.cta') }}
                        </a>
                    </div>
                    <ul class="mb-0">
                        @foreach ($missingReportsOverdue as $person)
                            <li>{{ app()->getLocale() === 'ar' ? $person->name_ar : $person->name_en }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endif
