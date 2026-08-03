@php
    // Guests never reach this layout (all app routes require auth).
    $role = auth()->user()->role ?? 'EMPLOYEE';
    $items = config("menu.{$role}", config('menu.EMPLOYEE'));
@endphp
<div class="offcanvas-lg offcanvas-start bg-body-tertiary border-end flex-shrink-0"
     tabindex="-1" id="sidebarNav" style="width: 210px;">
    <div class="offcanvas-header d-lg-none">
        <span class="offcanvas-title fw-semibold">{{ config('app.name') }}</span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                data-bs-target="#sidebarNav" aria-label="{{ __('nav.menu') }}"></button>
    </div>
    <div class="offcanvas-body p-0">
        <ul class="nav flex-column py-2">
            @foreach ($items as $item)
                @php
                    // Group matching only for CRUD resources: "admin.employees.index"
                    // also highlights on .create/.edit — but "admin.dashboard" or
                    // "admin.settings" must match their exact route only, otherwise
                    // every admin.* page would light up the whole menu.
                    $segments = explode('.', $item['route']);
                    $isCrud = in_array(end($segments), ['index', 'create', 'edit', 'show'], true);
                    $pattern = $isCrud
                        ? implode('.', array_slice($segments, 0, -1)) . '.*'
                        : $item['route'];
                    $isActive = request()->routeIs($pattern);
                @endphp
                <li class="nav-item">
                    <a class="nav-link px-3 {{ $isActive ? 'active fw-semibold' : 'text-body' }}"
                       href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}">
                        <i class="bi {{ $item['icon'] }} me-2" aria-hidden="true"></i>{{ __($item['key']) }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
