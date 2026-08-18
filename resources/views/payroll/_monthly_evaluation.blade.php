{{-- Salaries.xlsx-style monthly evaluation: 5 averages → monthly total → year total. --}}
@php
    /** @var array<string, float>|null $scores */
    $scores = $scores ?? null;
    /** @var string|null $evaluationText */
    $evaluationText = $evaluationText ?? null;
    /** @var float|null $yearAverage */
    $yearAverage = $yearAverage ?? null;
    /** @var bool $evaluationSaved */
    $evaluationSaved = (bool) ($evaluationSaved ?? false);
    /** @var \App\Models\User|null $evaluationSubject */
    $evaluationSubject = $evaluationSubject ?? null;
    /** @var int|null $evaluationMonth */
    $evaluationMonth = $evaluationMonth ?? null;
    /** @var int|null $evaluationYear */
    $evaluationYear = $evaluationYear ?? null;

    $actor = auth()->user();
    $canSaveEvaluation = $actor
        && $evaluationSubject
        && $evaluationMonth
        && $evaluationYear
        && (
            $actor->isManager()
            || ($actor->isTeamLeader() && app(\App\Services\Employees\TeamMembershipService::class)->leads($actor, $evaluationSubject))
        );
@endphp

@if ($scores !== null)
    <div class="card border-0 shadow-sm mb-3 h-100">
        <div class="card-header bg-transparent">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                <div>
                    <h2 class="h6 mb-0">{{ __('payroll.evaluation.title') }}</h2>
                    <p class="small text-body-secondary mb-0 mt-1">{{ __('payroll.evaluation.hint') }}</p>
                </div>
                @if ($evaluationSaved)
                    <span class="badge text-bg-success">{{ __('payroll.evaluation.saved_badge') }}</span>
                @else
                    <span class="badge text-bg-warning text-dark">{{ __('payroll.evaluation.preview_badge') }}</span>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            @unless ($evaluationSaved)
                <div class="alert alert-warning border-0 rounded-0 mb-0 px-3 py-2 small">
                    <div class="fw-semibold mb-1">{{ __('payroll.evaluation.not_saved_title') }}</div>
                    <p class="mb-2">{{ __('payroll.evaluation.not_saved_text') }}</p>
                    @if ($canSaveEvaluation)
                        <a href="{{ route('team-leader.evaluations.show', [
                                'employee' => $evaluationSubject,
                                'month' => $evaluationMonth,
                                'year' => $evaluationYear,
                            ]) }}"
                           class="btn btn-sm btn-warning">
                            {{ __('payroll.evaluation.open_save') }}
                        </a>
                    @endif
                </div>
            @endunless

            <div class="table-responsive">
                <table class="table table-hr table-sm align-middle mb-0">
                    <tbody>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('evaluation.fields.performance') }}</th>
                            <td class="col-num pe-3"><span class="num-ltr">{{ number_format((float) $scores['performance_score'], 2) }}%</span></td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('evaluation.fields.behavior') }}</th>
                            <td class="col-num pe-3"><span class="num-ltr">{{ number_format((float) $scores['behavior_score'], 2) }}%</span></td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('evaluation.fields.productivity') }}</th>
                            <td class="col-num pe-3"><span class="num-ltr">{{ number_format((float) $scores['productivity_score'], 2) }}%</span></td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('evaluation.fields.interest_return') }}</th>
                            <td class="col-num pe-3"><span class="num-ltr">{{ number_format((float) $scores['interest_return_score'], 2) }}%</span></td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('evaluation.fields.attendance') }}</th>
                            <td class="col-num pe-3"><span class="num-ltr">{{ number_format((float) $scores['attendance_score'], 2) }}%</span></td>
                        </tr>
                        <tr class="table-light fw-semibold">
                            <th scope="row" class="ps-3">{{ __('payroll.evaluation.monthly_total') }}</th>
                            <td class="col-num pe-3"><span class="num-ltr">{{ number_format((float) $scores['total_score'], 2) }}%</span></td>
                        </tr>
                        <tr class="fw-semibold">
                            <th scope="row" class="ps-3">{{ __('payroll.evaluation.year_total') }}</th>
                            <td class="col-num pe-3">
                                <span class="num-ltr">
                                    @if ($yearAverage !== null)
                                        {{ number_format((float) $yearAverage, 2) }}%
                                    @else
                                        {{ __('general.not_available') }}
                                    @endif
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if (filled($evaluationText))
                <div class="border-top px-3 py-3">
                    <div class="text-body-secondary small mb-1">{{ __('payroll.evaluation.text') }}</div>
                    <p class="mb-0">{{ $evaluationText }}</p>
                </div>
            @elseif ($evaluationSaved)
                <div class="border-top px-3 py-3 text-body-secondary small">
                    {{ __('payroll.evaluation.text_empty') }}
                </div>
            @endif
        </div>
    </div>
@endif
