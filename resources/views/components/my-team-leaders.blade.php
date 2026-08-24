@props([
    'leaders',
])

@php
    $leaders = collect($leaders);
    $count = $leaders->count();
@endphp

<div {{ $attributes->class('card border-0 shadow-sm') }}>
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-people me-1" aria-hidden="true"></i>{{ __('profile.my_team_leaders') }}
    </div>
    <div class="card-body">
        @if ($count === 0)
            <p class="mb-0 text-body-secondary">{{ __('profile.no_team_leader_assigned') }}</p>
        @else
            <p class="small text-body-secondary mb-3">{{ __('profile.my_team_leaders_hint') }}</p>
            <div class="row g-3">
                @foreach ($leaders as $leader)
                    @php
                        $leaderName = $leader->localizedName();
                    @endphp
                    <div class="col-12 {{ $count > 1 ? 'col-md-6' : '' }}">
                        <div class="d-flex align-items-start gap-3 p-3 rounded border bg-body-tertiary h-100">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary fw-bold flex-shrink-0"
                                  style="width: 2.75rem; height: 2.75rem;" aria-hidden="true">
                                {{ mb_substr($leaderName, 0, 1) }}
                            </span>
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $leaderName }}</div>
                                @if ($leader->localizedJobTitle())
                                    <div class="small text-body-secondary mb-2">{{ $leader->localizedJobTitle() }}</div>
                                @endif
                                <div class="small">
                                    <a href="mailto:{{ $leader->email }}" class="d-inline-flex align-items-center gap-1 text-break">
                                        <i class="bi bi-envelope" aria-hidden="true"></i>
                                        <span dir="ltr">{{ $leader->email }}</span>
                                    </a>
                                </div>
                                @if ($leader->profile?->phone)
                                    <div class="small mt-1">
                                        <a href="tel:{{ $leader->profile->phone }}" class="d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-telephone" aria-hidden="true"></i>
                                            <span dir="ltr">{{ $leader->profile->phone }}</span>
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
