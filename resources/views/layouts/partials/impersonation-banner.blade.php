@if (! empty($impersonationBanner))
    <div class="alert alert-warning rounded-0 mb-0 sticky-top d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2"
         role="status">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-person-badge" aria-hidden="true"></i>
            <span>{{ __('impersonation.banner', ['name' => $impersonationBanner['name']]) }}</span>
        </div>
        <form method="POST" action="{{ route('impersonation.leave') }}" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-dark">
                <i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>{{ __('impersonation.return') }}
            </button>
        </form>
    </div>
@endif
