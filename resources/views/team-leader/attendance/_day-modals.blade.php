{{-- Shared day detail modals --}}
                @foreach ($days as $row)
                    @php
                        $name = app()->getLocale() === 'ar' ? $row['user']->name_ar : $row['user']->name_en;
                        $modalId = 'att-day-'.$row['user']->id.'-'.str_replace('-', '', $row['date']).'-'.$loop->index;
                        $reviewable = collect($row['attendances'] ?? [])->values();
                        $late = (int) ($row['late_minutes'] ?? 0);
                        $gap = (int) ($row['gap_minutes'] ?? 0);
                        $ot = (int) ($row['recommended_overtime_minutes'] ?? 0);
                        $teamLeaders = $row['user']->teamLeaderDisplayNames();
                        $teamLeadersLabel = $teamLeaders !== '' ? $teamLeaders : __('general.not_available');
                    @endphp
                    <div class="modal fade attendance-detail-modal" id="{{ $modalId }}" tabindex="-1"
                         aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header border-0 pb-0">
                                    <div>
                                        <h2 class="modal-title h5 mb-0" id="{{ $modalId }}-label">
                                            {{ $name }}
                                        </h2>
                                        <p class="small text-body-secondary mb-0 mt-1">
                                            <span class="num-ltr">{{ \Carbon\Carbon::parse($row['date'])->translatedFormat('d M Y') }}</span>
                                            · {{ __('attendance.history.kinds.'.$row['kind']) }}
                                            @if (($row['punctuality'] ?? 'none') !== 'none')
                                                · {{ __('attendance.history.punctuality.'.$row['punctuality']) }}
                                            @endif
                                            · {{ __('employees.team_leader') }}:
                                            {{ $teamLeadersLabel }}
                                        </p>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="{{ __('general.close') }}"></button>
                                </div>
                                <div class="modal-body pt-3">
                                    @if (! empty($row['needs_review']))
                                        <div class="mb-3">
                                            <x-attendance-review-indicator
                                                :reason-codes="$row['review_reason_codes'] ?? []"
                                                :tooltip="$row['review_tooltip'] ?? ''"
                                            />
                                        </div>
                                    @endif
                                    <div class="attendance-detail-section mb-3">
                                        <div class="attendance-detail-metrics">
                                            <div>
                                                <h3 class="h6 mb-2">{{ __('attendance.history.hours_column') }}</h3>
                                                <dl class="attendance-detail-metric mb-2">
                                                    <dt>{{ __('dashboard.hours.should_spend') }}</dt>
                                                    <dd class="num-ltr hp-value">{{ number_format($row['planned_hours'], 2) }}h</dd>
                                                </dl>
                                                <dl class="attendance-detail-metric mb-2">
                                                    <dt>
                                                        {{ __('attendance_review.raw_hours') }}
                                                        <x-hp-tooltip :text="__('attendance.tooltips.recorded_hours')" />
                                                    </dt>
                                                    <dd class="num-ltr hp-value">{{ number_format($row['raw_hours'], 2) }}h</dd>
                                                </dl>
                                                @if (! empty($row['has_unverified_duration']))
                                                    <dl class="attendance-detail-metric mb-2">
                                                        <dt>
                                                            {{ __('attendance.integrity.recorded_hours_unverified') }}
                                                            <x-hp-tooltip :text="__('attendance.tooltips.recorded_hours')" />
                                                        </dt>
                                                        <dd class="num-ltr hp-value">{{ number_format($row['recorded_hours'] ?? $row['raw_hours'], 2) }}h</dd>
                                                    </dl>
                                                    <dl class="attendance-detail-metric mb-0">
                                                        <dt>
                                                            {{ __('attendance.integrity.credited_policy_cap') }}
                                                            <x-hp-tooltip :text="__('attendance.tooltips.credited_hours')" />
                                                        </dt>
                                                        <dd class="num-ltr hp-value">{{ number_format($row['credited_hours'] ?? $row['actual_hours'], 2) }}h</dd>
                                                    </dl>
                                                @else
                                                    <dl class="attendance-detail-metric mb-0">
                                                        <dt>
                                                            {{ __('attendance.integrity.verified_worked_hours') }}
                                                            <x-hp-tooltip :text="__('attendance.tooltips.verified_worked_hours')" />
                                                        </dt>
                                                        <dd class="num-ltr hp-value">{{ number_format($row['verified_hours'] ?? $row['actual_hours'], 2) }}h</dd>
                                                    </dl>
                                                @endif
                                            </div>
                                            <div>
                                                <h3 class="h6 mb-2">{{ __('attendance.history.minutes_column') }}</h3>
                                                <dl class="attendance-detail-metric mb-2">
                                                    <dt>
                                                        @if (! empty($row['has_shift_review']))
                                                            {{ __('attendance.integrity.late_vs_planned_window') }}
                                                            <x-hp-tooltip :text="__('attendance.tooltips.late_vs_planned_window')" />
                                                        @else
                                                            {{ __('clock.late_minutes') }}
                                                            <x-hp-tooltip :text="__('attendance.tooltips.late')" />
                                                        @endif
                                                    </dt>
                                                    <dd class="num-ltr hp-value">{{ $late }}</dd>
                                                </dl>
                                                @php
                                                    $otEligibleDetail = (int) ($row['overtime_eligible_minutes'] ?? 0);
                                                    $otApprovedDetail = (int) ($row['overtime_approved_minutes'] ?? 0);
                                                @endphp
                                                @if ($otEligibleDetail > 0 || $otApprovedDetail > 0)
                                                    <dl class="attendance-detail-metric mb-2">
                                                        <dt>
                                                            {{ __('attendance.integrity.ot_eligible') }}
                                                            <x-hp-tooltip :text="__('attendance.tooltips.eligible_overtime')" />
                                                        </dt>
                                                        <dd class="num-ltr hp-value">{{ $otEligibleDetail }}</dd>
                                                    </dl>
                                                    <dl class="attendance-detail-metric mb-2">
                                                        <dt>
                                                            {{ __('attendance.integrity.ot_approved') }}
                                                            <x-hp-tooltip :text="__('attendance.tooltips.approved_overtime')" />
                                                        </dt>
                                                        <dd class="num-ltr hp-value">{{ $otApprovedDetail }}</dd>
                                                    </dl>
                                                @endif
                                                <dl class="attendance-detail-metric mb-2">
                                                    <dt>{{ __('attendance_review.early_minutes') }}</dt>
                                                    <dd class="num-ltr hp-value">{{ (int) ($row['early_departure_minutes'] ?? 0) }}</dd>
                                                </dl>
                                                <dl class="attendance-detail-metric mb-2">
                                                    <dt>{{ __('attendance_review.breakdown.gap') }}</dt>
                                                    <dd class="num-ltr hp-value">{{ $gap }}</dd>
                                                </dl>
                                                @if ($ot > 0)
                                                    <dl class="attendance-detail-metric mb-0">
                                                        <dt>{{ __('attendance_review.overtime_minutes') }}</dt>
                                                        <dd class="num-ltr hp-value">{{ $ot }}</dd>
                                                    </dl>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    @if ($row['has_photos'] || $row['has_location'])
                                        <div class="attendance-detail-section mb-3">
                                            <h3 class="h6 mb-3">{{ __('attendance.history.evidence') }}</h3>
                                            @if ($row['has_photos'])
                                                <x-attendance-photos :photos="$row['photos']" />
                                            @endif
                                            @foreach ($row['attendances'] ?? [] as $attRow)
                                                @if ($attRow->clock_in_lat !== null || $attRow->clock_out_lat !== null)
                                                    <x-attendance-location
                                                        :attendance="$attRow"
                                                        :user="$row['user']"
                                                        class="{{ $loop->first && ! $row['has_photos'] ? '' : 'mt-3' }}"
                                                    />
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    @forelse ($reviewable as $attendanceRow)
                                        <div @class(['attendance-detail-section', 'mb-3' => ! $loop->last])>
                                            <div class="attendance-shift-header">
                                                <h3 class="h6 mb-0">{{ __('clock.shifts.'.$attendanceRow->shift_type) }}</h3>
                                                <x-attendance-location-badges
                                                    :attendance="$attendanceRow"
                                                    :user="$row['user']"
                                                />
                                                <x-attendance-end-shift :attendance="$attendanceRow" />
                                            </div>

                                            <x-attendance-shift-timeline
                                                :attendance="$attendanceRow"
                                                class="mb-3"
                                            />

                                            @if ($attendanceRow->clock_out === null)
                                                <p class="small text-body-secondary mb-0">{{ __('clock.end_shift.hint') }}</p>
                                            @else
                                                <x-attendance-time-breakdown
                                                    :attendance="$attendanceRow"
                                                    review
                                                    :show-overtime-section="false"
                                                />

                                                <x-attendance-overtime-review
                                                    :attendance="$attendanceRow"
                                                    embedded
                                                    class="mt-0"
                                                />
                                            @endif
                                        </div>
                                    @empty
                                        <div class="attendance-detail-section">
                                            <p class="small text-body-secondary mb-0">
                                                {{ __('attendance.history.no_review') }}
                                            </p>
                                        </div>
                                    @endforelse
                                </div>
                                <div class="modal-footer border-0 pt-0">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                        {{ __('general.close') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
