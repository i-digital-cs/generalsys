@php
    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $authUser = auth()->user();
    $displayName = $authUser
        ? (app()->getLocale() === 'ar' ? $authUser->name_ar : $authUser->name_en)
        : '';
    $homeRoute = match (true) {
        $authUser?->isManager() => 'admin.dashboard',
        $authUser?->isTeamLeader() => 'team-leader.dashboard',
        $authUser?->isEmployee() => 'employee.dashboard',
        default => 'dashboard',
    };
@endphp
<nav class="navbar navbar-dark bg-primary">
    <div class="container-fluid flex-nowrap gap-2">
        <button class="btn btn-primary d-lg-none flex-shrink-0" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#sidebarNav" aria-controls="sidebarNav"
                aria-label="{{ __('nav.menu') }}">
            <i class="bi bi-list"></i>
        </button>

        <a class="navbar-brand text-truncate mb-0 me-auto"
           href="{{ Route::has($homeRoute) ? route($homeRoute) : route('dashboard') }}">{{ config('app.name') }}</a>

        <div class="d-flex flex-nowrap flex-shrink-0 align-items-center gap-1">
            <a class="nav-link text-white px-2"
               href="{{ route('locale', $otherLocale) }}"
               title="{{ __('general.language') }}"
               aria-label="{{ __('general.switch_language') }}">
                <i class="bi bi-globe" aria-hidden="true"></i>
                <span class="d-none d-sm-inline ms-1">{{ __('general.language') }}</span>
            </a>

            @auth
                <div class="dropdown">
                    <button type="button"
                            class="btn btn-primary d-inline-flex align-items-center gap-2 px-2 dropdown-toggle hp-user-menu"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            aria-label="{{ $displayName }}">
                        <i class="bi bi-person-circle fs-5" aria-hidden="true"></i>
                        <span class="d-none d-md-inline text-truncate hp-user-menu-name">{{ $displayName }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li class="dropdown-header d-md-none">{{ $displayName }}</li>
                        <li class="d-md-none"><hr class="dropdown-divider"></li>
                        @if ($authUser->isEmployee())
                            <li>
                                <a class="dropdown-item" href="{{ route('employee.profile') }}">
                                    <i class="bi bi-person me-2" aria-hidden="true"></i>{{ __('nav.profile') }}
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>{{ __('nav.logout') }}
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            @endauth
        </div>
    </div>
</nav>
