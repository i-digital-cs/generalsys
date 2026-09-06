@php
    $toasts = [];

    if (isset($errors) && $errors->any()) {
        $toasts[] = [
            'variant' => 'danger',
            'icon' => 'bi-exclamation-triangle-fill',
            'title' => __('general.form_errors'),
            'messages' => $errors->all(),
        ];
    }

    foreach ([
        'success' => ['variant' => 'success', 'icon' => 'bi-check-circle-fill'],
        'error' => ['variant' => 'danger', 'icon' => 'bi-exclamation-triangle-fill'],
        'warning' => ['variant' => 'warning', 'icon' => 'bi-exclamation-circle-fill'],
    ] as $sessionKey => $meta) {
        if (! session($sessionKey)) {
            continue;
        }
        // Avoid duplicating the validation toast when session('error') was also flashed.
        if ($sessionKey === 'error' && isset($errors) && $errors->any()) {
            continue;
        }
        $toasts[] = [
            'variant' => $meta['variant'],
            'icon' => $meta['icon'],
            'title' => session($sessionKey),
            'messages' => [],
        ];
    }
@endphp

@if ($toasts !== [])
    {{-- Visible without JS (Vite modules load deferred). app.js wires Bootstrap Toast autohide. --}}
    <div class="hp-toast-stack" data-hp-toast-stack role="region" aria-label="{{ __('general.form_errors') }}">
        @foreach ($toasts as $toast)
            <div class="toast hp-toast hp-toast--{{ $toast['variant'] }} border-0 shadow-lg mb-2 show"
                 role="alert" aria-live="assertive" aria-atomic="true"
                 data-hp-toast
                 data-bs-autohide="true"
                 data-bs-delay="{{ $toast['messages'] !== [] ? 9000 : 5500 }}">
                <div class="d-flex align-items-stretch">
                    <div class="hp-toast__icon d-flex align-items-center justify-content-center px-3">
                        <i class="bi {{ $toast['icon'] }} fs-4" aria-hidden="true"></i>
                    </div>
                    <div class="toast-body py-3 pe-2">
                        <div class="fw-semibold">{{ $toast['title'] }}</div>
                        @if ($toast['messages'] !== [])
                            <ul class="mb-0 mt-2 ps-3 small">
                                @foreach ($toast['messages'] as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <button type="button" class="btn-close me-2 m-auto"
                            data-bs-dismiss="toast" aria-label="{{ __('general.close') }}"></button>
                </div>
            </div>
        @endforeach
    </div>
@endif
