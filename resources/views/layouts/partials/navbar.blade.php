@php
    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $authUser = auth()->user();
    $displayName = $authUser
        ? (app()->getLocale() === 'ar' ? $authUser->name_ar : $authUser->name_en)
        : '';
    $appModule = $appModule ?? \App\Support\AppModule::HR;
    $showHrSidebar = $showHrSidebar ?? \App\Support\AppModule::showsSidebar($appModule);
    $moduleItems = $authUser ? \App\Support\AppModule::navItems($authUser) : [];
    $gnrlIdentity = config('general_house.android_app.channel') === 'live'
        ? __('nav.gnrl_operations')
        : __('nav.gnrl_operations_test');
@endphp
<nav class="navbar navbar-dark bg-primary hp-app-header" data-hp-app-header>
    <div class="hp-app-header__grid container-fluid">
        {{-- Row 1 (mobile) / single row (desktop): chrome controls --}}
        <div class="hp-app-header__chrome">
            @if ($showHrSidebar)
                <button class="btn btn-primary hp-app-header__icon-btn d-lg-none" type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#sidebarNav" aria-controls="sidebarNav"
                        aria-label="{{ __('nav.menu') }}">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
            @endif

            <a class="navbar-brand hp-app-header__brand mb-0"
               href="{{ route('portal.home') }}"
               title="{{ $gnrlIdentity }}">
                <span class="hp-app-header__brand-mobile">{{ $gnrlIdentity }}</span>
                <span class="hp-app-header__brand-desktop">{{ config('app.name') }}</span>
            </a>

            <div class="hp-app-header__actions d-flex flex-nowrap align-items-center gap-1 {{ auth()->guest() ? 'ms-auto' : '' }}">
                <button type="button"
                        class="nav-link text-white px-2 border-0 bg-transparent hp-app-header__icon-btn d-none d-lg-inline-flex"
                        data-theme-toggle
                        data-label-light="{{ __('general.theme_light') }}"
                        data-label-dark="{{ __('general.theme_dark') }}"
                        data-aria-light="{{ __('general.theme_to_light') }}"
                        data-aria-dark="{{ __('general.theme_to_dark') }}"
                        title="{{ __('general.theme_to_dark') }}"
                        aria-label="{{ __('general.theme_to_dark') }}">
                    <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
                    <span class="d-none d-xl-inline ms-1" data-theme-label>{{ __('general.theme_dark') }}</span>
                </button>

                <a class="nav-link text-white px-2 hp-app-header__icon-btn d-none d-lg-inline-flex"
                   href="{{ route('locale', $otherLocale) }}"
                   title="{{ __('general.language') }}"
                   aria-label="{{ __('general.switch_language') }}">
                    <i class="bi bi-globe" aria-hidden="true"></i>
                    <span class="d-none d-xl-inline ms-1">{{ __('general.language') }}</span>
                </a>

                @auth
                    @php
                        $unreadCount = $authUser->unreadNotifications()->count();
                        $latestNotifications = $authUser->notifications()->limit(5)->get();
                    @endphp
                    <div class="dropdown">
                        <button type="button"
                                class="btn btn-primary hp-app-header__icon-btn"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                                aria-label="{{ __('notifications.bell') }}">
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            @if ($unreadCount > 0)
                                <span class="badge rounded-pill text-bg-danger">
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
                                class="btn btn-primary d-inline-flex align-items-center gap-2 px-2 dropdown-toggle hp-user-menu hp-app-header__icon-btn"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                                aria-label="{{ $displayName }}">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <span class="d-none d-lg-inline text-truncate hp-user-menu-name">{{ $displayName }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li class="dropdown-header">{{ $displayName }}</li>
                            <li><hr class="dropdown-divider"></li>
                            <li class="d-lg-none">
                                <button type="button"
                                        class="dropdown-item"
                                        data-theme-toggle
                                        data-label-light="{{ __('general.theme_light') }}"
                                        data-label-dark="{{ __('general.theme_dark') }}"
                                        data-aria-light="{{ __('general.theme_to_light') }}"
                                        data-aria-dark="{{ __('general.theme_to_dark') }}">
                                    <i class="bi bi-moon-stars me-2" data-theme-icon aria-hidden="true"></i>
                                    <span data-theme-label>{{ __('general.theme_dark') }}</span>
                                </button>
                            </li>
                            <li class="d-lg-none">
                                <a class="dropdown-item" href="{{ route('locale', $otherLocale) }}">
                                    <i class="bi bi-globe me-2" aria-hidden="true"></i>{{ __('general.language') }}
                                </a>
                            </li>
                            <li class="d-lg-none"><hr class="dropdown-divider"></li>
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

        @auth
            {{-- Row 2 (mobile) / mid column (desktop): module switcher — natural width, scroll when needed --}}
            <div class="hp-app-header__modules" role="tablist" aria-label="{{ __('portal.title') }}"
                 data-hp-module-switcher>
                @foreach ($moduleItems as $moduleId)
                    <a class="btn hp-module-tab {{ $appModule === $moduleId ? 'hp-module-tab--active' : '' }}"
                       href="{{ route(\App\Support\AppModule::navRoute($moduleId)) }}"
                       role="tab"
                       data-module="{{ $moduleId }}"
                       aria-selected="{{ $appModule === $moduleId ? 'true' : 'false' }}">
                        {{ __('portal.'.$moduleId) }}
                    </a>
                @endforeach
            </div>
        @endauth
    </div>
</nav>
