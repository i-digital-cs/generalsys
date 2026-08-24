@php
    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $authUser = auth()->user();
    $displayName = $authUser
        ? (app()->getLocale() === 'ar' ? $authUser->name_ar : $authUser->name_en)
        : '';
    $appModule = $appModule ?? \App\Support\AppModule::HR;
    $showHrSidebar = $showHrSidebar ?? \App\Support\AppModule::showsSidebar($appModule);
@endphp
<nav class="navbar navbar-dark bg-primary">
    <div class="container-fluid flex-nowrap gap-2">
        @if ($showHrSidebar)
            <button class="btn btn-primary d-lg-none flex-shrink-0" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#sidebarNav" aria-controls="sidebarNav"
                    aria-label="{{ __('nav.menu') }}">
                <i class="bi bi-list"></i>
            </button>
        @endif

        <a class="navbar-brand text-truncate mb-0 d-none d-sm-inline"
           href="{{ route('portal.home') }}">{{ config('app.name') }}</a>

        @auth
            <div class="d-flex flex-nowrap flex-grow-1 overflow-auto gap-1 py-1" role="tablist" aria-label="{{ __('portal.title') }}">
                @foreach (\App\Support\AppModule::navItems($authUser) as $moduleId)
                    <a class="btn btn-sm flex-shrink-0 {{ $appModule === $moduleId ? 'btn-light text-primary' : 'btn-outline-light' }}"
                       href="{{ route(\App\Support\AppModule::navRoute($moduleId)) }}"
                       role="tab"
                       aria-selected="{{ $appModule === $moduleId ? 'true' : 'false' }}">
                        {{ __('portal.'.$moduleId) }}
                    </a>
                @endforeach
            </div>
        @endauth

        <div class="d-flex flex-nowrap flex-shrink-0 align-items-center gap-1 {{ auth()->guest() ? 'ms-auto' : '' }}">
            <button type="button"
                    class="nav-link text-white px-2 border-0 bg-transparent"
                    data-theme-toggle
                    data-label-light="{{ __('general.theme_light') }}"
                    data-label-dark="{{ __('general.theme_dark') }}"
                    data-aria-light="{{ __('general.theme_to_light') }}"
                    data-aria-dark="{{ __('general.theme_to_dark') }}"
                    title="{{ __('general.theme_to_dark') }}"
                    aria-label="{{ __('general.theme_to_dark') }}">
                <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
                <span class="d-none d-sm-inline ms-1" data-theme-label>{{ __('general.theme_dark') }}</span>
            </button>

            <a class="nav-link text-white px-2"
               href="{{ route('locale', $otherLocale) }}"
               title="{{ __('general.language') }}"
               aria-label="{{ __('general.switch_language') }}">
                <i class="bi bi-globe" aria-hidden="true"></i>
                <span class="d-none d-sm-inline ms-1">{{ __('general.language') }}</span>
            </a>

            @auth
                @php
                    $unreadCount = $authUser->unreadNotifications()->count();
                    $latestNotifications = $authUser->notifications()->limit(5)->get();
                @endphp
                <div class="dropdown">
                    <button type="button"
                            class="btn btn-primary position-relative px-2"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            aria-label="{{ __('notifications.bell') }}">
                        <i class="bi bi-bell fs-5" aria-hidden="true"></i>
                        @if ($unreadCount > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">
                                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                            </span>
                        @endif
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 18rem;">
                        <li class="dropdown-header d-flex justify-content-between align-items-center">
                            <span>{{ __('notifications.bell') }}</span>
                            @if ($unreadCount > 0)
                                <form method="POST" action="{{ route('notifications.read-all') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-link btn-sm p-0">{{ __('notifications.mark_all_read') }}</button>
                                </form>
                            @endif
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @forelse ($latestNotifications as $item)
                            <li>
                                <a class="dropdown-item small @if ($item->read_at === null) fw-semibold @endif"
                                   href="{{ route('notifications.open', $item->id) }}">
                                    {{ \App\Support\NotificationMessage::title($item->data) }}
                                    <div class="text-body-secondary text-wrap">
                                        {{ \App\Support\NotificationMessage::body($item->data) }}
                                    </div>
                                </a>
                            </li>
                        @empty
                            <li><span class="dropdown-item-text small text-body-secondary">{{ __('notifications.empty_title') }}</span></li>
                        @endforelse
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-center" href="{{ route('notifications.index') }}">
                                {{ __('notifications.view_all') }}
                            </a>
                        </li>
                    </ul>
                </div>

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
                        @if (! empty($impersonationBanner))
                            <li>
                                <form method="POST" action="{{ route('impersonation.leave') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-arrow-return-left me-2" aria-hidden="true"></i>{{ __('impersonation.return') }}
                                    </button>
                                </form>
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
