@if (session('success') || session('error') || session('warning'))
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        @foreach ([
            'success' => 'text-bg-success',
            'error' => 'text-bg-danger',
            'warning' => 'text-bg-warning text-dark',
        ] as $key => $class)
            @if (session($key))
                <div class="toast {{ $class }} border-0" role="alert" aria-live="assertive"
                     aria-atomic="true" data-bs-delay="5000">
                    <div class="d-flex">
                        <div class="toast-body">{{ session($key) }}</div>
                        <button type="button" class="btn-close {{ $key === 'warning' ? '' : 'btn-close-white' }} me-2 m-auto"
                                data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('.toast').forEach(function (el) {
                bootstrap.Toast.getOrCreateInstance(el).show();
            });
        </script>
    @endpush
@endif
