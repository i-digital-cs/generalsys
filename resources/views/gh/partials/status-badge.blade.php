@php
    $isActive = ($status ?? '') === \App\Support\GeneralHouse\GhCatalogStatus::ACTIVE;
@endphp
<span class="badge {{ $isActive ? 'text-bg-success' : 'text-bg-secondary' }}">
    {{ __('gh.status.'.$status) }}
</span>
