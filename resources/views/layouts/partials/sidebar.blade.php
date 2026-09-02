@php
    // Guests never reach this layout (all app routes require auth).
    $role = auth()->user()->role ?? 'EMPLOYEE';
    $appModule = $appModule ?? \App\Support\AppModule::HR;
    $items = in_array($appModule, [\App\Support\AppModule::SERVICE, \App\Support\AppModule::PRODUCT], true)
        ? config("module_menu.{$appModule}", [])
        : config("menu.{$role}", config('menu.EMPLOYEE'));
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
                @if (($item['type'] ?? '') === 'heading')
                    @if (! empty($item['can']) && ! auth()->user()->can($item['can'][0], $item['can'][1] ?? null))
                        @continue
                    @endif
                    <li class="nav-item mt-2 px-3">
                        <span class="d-block small text-uppercase text-body-secondary fw-semibold"
                              style="font-size: .68rem; letter-spacing: .04em;">
                            {{ __($item['key']) }}
                        </span>
                    </li>
                    @continue
                @endif
                @if (! empty($item['can']) && ! auth()->user()->can($item['can'][0], $item['can'][1] ?? null))
                    @continue
                @endif
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

                    // Wildcard CRUD groups (e.g. gross.sales.*) must not steal highlight
                    // from a sibling menu item with its own route (e.g. gross.sales.collections).
                    if ($isActive && $isCrud) {
                        foreach ($items as $other) {
                            if (($other['route'] ?? '') === $item['route']) {
                                continue;
                            }
                            $otherRoute = $other['route'] ?? '';
                            if ($otherRoute !== '' && request()->routeIs($otherRoute)) {
                                $isActive = false;
                                break;
                            }
                        }
                    }

                    $params = $item['params'] ?? [];
                    if ($isActive && isset($params['module'])) {
                        $isActive = request()->route('module') === $params['module'];
                    }
                @endphp
                <li class="nav-item">
                    <a class="nav-link px-3 {{ $isActive ? 'active fw-semibold' : 'text-body' }}"
                       href="{{ Route::has($item['route']) ? route($item['route'], $params) : '#' }}">
                        <i class="bi {{ $item['icon'] }} me-2" aria-hidden="true"></i>{{ __($item['key']) }}
                        @if (($item['route'] ?? '') === 'gross.stock.index' && ($grossLowStockCount ?? 0) > 0)
                            <span class="badge rounded-pill text-bg-warning ms-1 num-ltr">{{ $grossLowStockCount }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
