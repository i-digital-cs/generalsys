{{-- Modern card presentation for attendance history — same filters, data, modals, actions --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">{{ __('attendance.history.title') }}</h1>
        <p class="text-body-secondary mb-0">{{ __('attendance.history.subtitle') }}</p>
    </div>
</div>

<div class="card gh-board-filters border-0 mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('team-leader.attendance') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-4 col-xl-3">
                <label for="employee_id" class="form-label">{{ __('attendance.history.employee') }}</label>
                <select id="employee_id" name="employee_id" class="form-select">
                    <option value="">{{ __('attendance.history.all_employees') }}</option>
                    @foreach ($employees as $member)
                        @php
                            $label = app()->getLocale() === 'ar' ? $member->name_ar : $member->name_en;
                        @endphp
                        <option value="{{ $member->id }}" @selected((int) $employeeId === (int) $member->id)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label for="from" class="form-label">{{ __('attendance.history.from') }}</label>
                <input type="date" id="from" name="from" value="{{ $from }}"
                       class="form-control @error('from') is-invalid @enderror" required>
                @error('from') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label for="to" class="form-label">{{ __('attendance.history.to') }}</label>
                <input type="date" id="to" name="to" value="{{ $to }}"
                       class="form-control @error('to') is-invalid @enderror" required>
                @error('to') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label for="kind" class="form-label">{{ __('attendance.history.kind') }}</label>
                <select id="kind" name="kind" class="form-select">
                    @foreach (['all', 'present', 'absent', 'shortfall', 'surplus', 'rest', 'vacation'] as $option)
                        <option value="{{ $option }}" @selected($kind === $option)>
                            {{ __('attendance.history.kinds.'.$option) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label for="evidence" class="form-label">{{ __('attendance.history.evidence') }}</label>
                <select id="evidence" name="evidence" class="form-select">
                    @foreach (['all', 'photos', 'location', 'outside'] as $option)
                        <option value="{{ $option }}" @selected($evidence === $option)>
                            {{ __('attendance.history.evidence_options.'.$option) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-xl-1 d-grid">
                <button type="submit" class="btn btn-primary">{{ __('attendance.history.apply') }}</button>
            </div>
            <div class="col-12">
                <a href="{{ route('team-leader.attendance') }}" class="btn btn-sm btn-outline-secondary">
                    {{ __('attendance.history.clear') }}
                </a>
            </div>
        </form>
    </div>
</div>

<div class="eval-card-ui" data-eval-card-ui="hr-attendance">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
        <h2 class="h6 mb-0" style="color: var(--gh-board-navy)">{{ __('attendance.history.list_title') }}</h2>
        <span class="small text-body-secondary">
            {{ __('attendance.history.count', ['count' => $days->total()]) }}
        </span>
    </div>

    @if ($days->isEmpty())
        <div class="eval-card-ui__empty">
            <x-empty-state
                :title="__('attendance.history.empty_title')"
                :text="__('attendance.history.empty_text')"
                icon="bi-calendar-check"
            />
        </div>
    @else
        <div class="eval-card-ui__grid">
            @foreach ($days as $row)
                @php
                    $name = app()->getLocale() === 'ar' ? $row['user']->name_ar : $row['user']->name_en;
                    $modalId = 'att-day-'.$row['user']->id.'-'.str_replace('-', '', $row['date']).'-'.$loop->index;
                    $photoUrl = ($row['user']->profile?->personal_photo_path && auth()->user()?->can('view', $row['user']))
                        ? route('employees.photo', $row['user'])
                        : null;
                    $initial = mb_substr($name, 0, 1);
                    $dateLabel = \Carbon\Carbon::parse($row['date'])->translatedFormat('d M Y');
                    $kindLabel = __('attendance.history.kinds.'.$row['kind']);
                    $late = (int) ($row['late_minutes'] ?? 0);
                @endphp
                <x-gh.record-card
                    :title="$name"
                    :image-url="$photoUrl"
                    :image-alt="$name"
                    image-variant="circle"
                    :image-placeholder="$initial"
                >
                    <x-slot:badges>
                        <span class="gh-record-card__badge gh-record-card__badge--status">{{ $kindLabel }}</span>
                        @if (($row['punctuality'] ?? 'none') !== 'none')
                            <span class="gh-record-card__badge gh-record-card__badge--muted">
                                {{ __('attendance.history.punctuality.'.$row['punctuality']) }}
                            </span>
                        @endif
                    </x-slot:badges>
                    <x-slot:meta>
                        <x-gh.meta-cell icon="bi-calendar3" :value="$dateLabel" />
                        @if ($late > 0)
                            <x-gh.meta-cell icon="bi-clock-history" :value="__('clock.late_minutes').': '.$late" :ltr="true" />
                        @endif
                        <x-gh.meta-cell
                            icon="bi-hourglass-split"
                            :value="__('dashboard.hours.should_spend').': '.number_format($row['planned_hours'], 2).'h'"
                            :ltr="true"
                        />
                    </x-slot:meta>
                    <x-slot:actions>
                        <button type="button" class="gh-board-btn" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                            <i class="bi bi-window" aria-hidden="true"></i>
                            <span>{{ __('attendance.history.open_details') }}</span>
                        </button>
                    </x-slot:actions>
                </x-gh.record-card>
            @endforeach
        </div>

        @include('team-leader.attendance._day-modals')

        <div class="eval-card-ui__pager">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <p class="small text-body-secondary mb-0">
                    {{ __('attendance.history.showing', [
                        'from' => $days->firstItem() ?? 0,
                        'to' => $days->lastItem() ?? 0,
                        'total' => $days->total(),
                    ]) }}
                </p>
                <nav class="overflow-auto" aria-label="{{ __('attendance.history.title') }}">
                    {{ $days->onEachSide(1)->links() }}
                </nav>
            </div>
        </div>
    @endif
</div>
