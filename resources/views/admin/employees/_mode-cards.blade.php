{{-- Modern card presentation (working tree) --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('employees.title') }}</h1>
        <a href="{{ route('admin.employees.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('employees.add_new') }}
        </a>
    </div>

    <div class="eval-card-ui" data-eval-card-ui="hr-employees">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.employees.index') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-6">
                        <label for="q" class="form-label mb-1">{{ __('general.search') }}</label>
                        <input type="search" id="q" name="q" value="{{ $q }}" class="form-control form-control-sm"
                               placeholder="{{ __('employees.search_placeholder') }}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="status" class="form-label mb-1">{{ __('employees.status') }}</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach (['ACTIVE', 'INACTIVE'] as $option)
                                <option value="{{ $option }}" @selected($status === $option)>
                                    {{ __("employees.statuses.{$option}") }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>{{ __('general.search') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if ($employees->isEmpty())
            <div class="eval-card-ui__empty">
                <x-empty-state :title="__('employees.empty_title')" :text="__('employees.empty_text')" icon="bi-people" />
            </div>
        @else
            <div class="eval-card-ui__grid">
                @foreach ($employees as $employee)
                    @php
                        $displayName = app()->getLocale() === 'ar' ? $employee->name_ar : $employee->name_en;
                        $assignedLeaders = $employee->teamLeaders->values();
                        $photoUrl = ($employee->profile?->personal_photo_path && auth()->user()?->can('view', $employee))
                            ? route('employees.photo', $employee)
                            : null;
                        $initial = mb_substr($displayName, 0, 1);
                        $code = $employee->profile?->employee_code ?? __('general.not_available');
                        $phone = $employee->profile?->phone;
                    @endphp
                    <x-gh.record-card
                        :title="$displayName"
                        :title-href="route('admin.employees.edit', $employee)"
                        :image-url="$photoUrl"
                        :image-alt="$displayName"
                        image-variant="circle"
                        :image-placeholder="$initial"
                    >
                        <x-slot:badges>
                            <span class="gh-record-card__badge gh-record-card__badge--muted">{{ __("employees.roles.{$employee->role}") }}</span>
                            <span @class([
                                'gh-record-card__badge',
                                'gh-record-card__badge--status' => $employee->isActive(),
                                'gh-record-card__badge--muted' => ! $employee->isActive(),
                            ])>
                                {{ __("employees.statuses.{$employee->status}") }}
                            </span>
                        </x-slot:badges>
                        <x-slot:meta>
                            <x-gh.meta-cell icon="bi-hash" :value="$code" :ltr="true" />
                            <x-gh.meta-cell icon="bi-briefcase" :value="$employee->localizedJobTitle() ?? __('general.not_available')" />
                            <x-gh.meta-cell icon="bi-person-badge" :value="$assignedLeaders->get(0)?->localizedName() ?? __('employees.no_team_leader')" />
                            @if ($phone)
                                <x-gh.meta-cell icon="bi-telephone" :value="$phone" :ltr="true" />
                            @endif
                            <x-gh.meta-cell icon="bi-envelope" :value="$employee->email" :ltr="true" />
                        </x-slot:meta>
                        <x-slot:actions>
                            @if ($canImpersonate && $employee->id !== auth()->id())
                                <x-impersonate-button :user="$employee" />
                            @endif
                            <a href="{{ route('admin.employees.edit', $employee) }}" class="gh-board-btn">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                <span>{{ __('general.edit') }}</span>
                            </a>
                        </x-slot:actions>
                    </x-gh.record-card>
                @endforeach
            </div>
            <div class="eval-card-ui__pager">{{ $employees->links() }}</div>
        @endif
    </div>
