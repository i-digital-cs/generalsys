@php
    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $authUser = auth()->user();
@endphp
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <button class="btn btn-primary d-lg-none" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#sidebarNav" aria-controls="sidebarNav"
                aria-label="{{ __('nav.menu') }}">
            <i class="bi bi-list"></i>
        </button>

        <a class="navbar-brand" href="#">{{ config('app.name') }}</a>

        <ul class="navbar-nav ms-auto align-items-lg-center">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('locale', $otherLocale) }}">
                    <i class="bi bi-translate me-1" aria-hidden="true"></i>{{ __('general.language') }}
                </a>
            </li>
            @auth
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                        {{ app()->getLocale() === 'ar' ? $authUser->name_ar : $authUser->name_en }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @if ($authUser->isEmployee())
                            <li>
                                <a class="dropdown-item" href="{{ route('employee.profile', $authUser) }}">
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
                </li>
            @endauth
        </ul>
    </div>
</nav>
