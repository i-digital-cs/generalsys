{{-- Modern card presentation for daily report review list --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">{{ __('report.tl.title') }}</h1>
        <p class="text-body-secondary mb-0">{{ __('report.tl.subtitle') }}</p>
    </div>
</div>

<div class="card gh-board-filters border-0 mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('team-leader.reports') }}" id="reports-filter"
              class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label for="employee_id" class="form-label">{{ __('report.tl.employee_filter') }}</label>
                <select id="employee_id" name="employee_id" class="form-select"
                        onchange="this.form.submit()">
                    <option value="">{{ __('report.tl.all_employees') }}</option>
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
            <div class="col-12 col-md-4">
                <label for="filter" class="form-label">{{ __('report.tl.filter') }}</label>
                <select id="filter" name="filter" class="form-select" onchange="this.form.submit()">
                    <option value="all" @selected($filter === 'all')>{{ __('report.tl.filters.all') }}</option>
                    <option value="pending" @selected($filter === 'pending')>{{ __('report.tl.filters.pending') }}</option>
                    <option value="replied" @selected($filter === 'replied')>{{ __('report.tl.filters.replied') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-4 d-flex flex-wrap gap-2">
                <a href="{{ route('team-leader.reports') }}" class="btn btn-outline-secondary">
                    {{ __('report.tl.clear_filters') }}
                </a>
            </div>
        </form>
    </div>
</div>

<div class="eval-card-ui" data-eval-card-ui="hr-reports">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
        <h2 class="h6 mb-0" style="color: var(--gh-board-navy)">{{ __('report.tl.list_title') }}</h2>
        <span class="small text-body-secondary">
            {{ __('report.tl.count', ['count' => $reports->total()]) }}
        </span>
    </div>

    @if ($reports->isEmpty())
        <div class="eval-card-ui__empty">
            <x-empty-state
                :title="__('report.tl.empty_title')"
                :text="__('report.tl.empty_text')"
                icon="bi-journal-text"
            />
        </div>
    @else
        <div class="eval-card-ui__grid">
            @foreach ($reports as $row)
                @php
                    $name = app()->getLocale() === 'ar' ? $row->user->name_ar : $row->user->name_en;
                    $preview = \Illuminate\Support\Str::limit(strip_tags((string) $row->daily_report), 80);
                    $statusKey = filled($row->team_leader_response) ? 'replied' : 'waiting';
                    $photoUrl = ($row->user->profile?->personal_photo_path && auth()->user()?->can('view', $row->user))
                        ? route('employees.photo', $row->user)
                        : null;
                    $initial = mb_substr($name, 0, 1);
                    $actionLabel = filled($row->team_leader_response) ? __('report.tl.view') : __('report.tl.reply');
                @endphp
                <x-gh.record-card
                    :title="$name"
                    :title-href="route('team-leader.reports.show', $row)"
                    :image-url="$photoUrl"
                    :image-alt="$name"
                    image-variant="circle"
                    :image-placeholder="$initial"
                >
                    <x-slot:badges>
                        <span @class([
                            'gh-record-card__badge',
                            'gh-record-card__badge--status' => $statusKey === 'replied',
                            'gh-record-card__badge--muted' => $statusKey === 'waiting',
                        ])>
                            {{ __('report.status.'.$statusKey) }}
                        </span>
                    </x-slot:badges>
                    <x-slot:meta>
                        <x-gh.meta-cell icon="bi-calendar3" :value="$row->date->toDateString()" :ltr="true" />
                        <x-gh.meta-cell icon="bi-chat-left-text" :value="$preview !== '' ? $preview : '—'" />
                    </x-slot:meta>
                    <x-slot:actions>
                        <a href="{{ route('team-leader.reports.show', $row) }}" class="gh-board-btn">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                            <span>{{ $actionLabel }}</span>
                        </a>
                    </x-slot:actions>
                </x-gh.record-card>
            @endforeach
        </div>
        <div class="eval-card-ui__pager">{{ $reports->links() }}</div>
    @endif
</div>
