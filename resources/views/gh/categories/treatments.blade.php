@extends('layouts.app')

@section('title', __('gh.categories.services_title', ['name' => $category->localizedName()]))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.categories.services_title', ['name' => $category->localizedName()]) }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.categories.services_subtitle') }}</p>
        </div>
        <a href="{{ route('gh.categories.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.categories.back') }}</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('gh.categories.treatments.sync', $category) }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-12">
                    <label class="form-label" for="gh-cat-svc-search">{{ __('gh.categories.services_search') }}</label>
                    <input type="search" id="gh-cat-svc-search" class="form-control form-control-sm"
                           placeholder="{{ __('gh.categories.services_search_placeholder') }}" autocomplete="off">
                </div>

                <div class="col-12">
                    @if ($allTreatments->isEmpty())
                        <div class="alert alert-warning py-2 small mb-0">{{ __('gh.categories.services_none_global') }}</div>
                    @else
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2" id="gh-cat-svc-grid">
                            @foreach ($allTreatments as $treatment)
                                @php
                                    $checked = in_array((int) $treatment->id, $selectedIds, true);
                                @endphp
                                <div class="col gh-cat-svc-item"
                                     data-name-ar="{{ mb_strtolower($treatment->name_ar) }}"
                                     data-name-en="{{ mb_strtolower($treatment->name_en) }}">
                                    <label class="card h-100 border {{ $checked ? 'border-primary' : '' }} mb-0">
                                        <div class="card-body p-2 d-flex align-items-start gap-2">
                                            <input class="form-check-input mt-1" type="checkbox"
                                                   name="treatment_ids[]" value="{{ $treatment->id }}"
                                                   @checked($checked)>
                                            <div>
                                                <div class="fw-semibold small">{{ $treatment->localizedName() }}</div>
                                                <div class="text-body-secondary small" dir="ltr">{{ $treatment->name_en }}</div>
                                                @if (! $treatment->isActive())
                                                    <span class="badge text-bg-secondary">{{ __('gh.status.INACTIVE') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-body-secondary small mt-2 mb-0">{{ __('gh.categories.services_detach_hint') }}</p>
                    @endif
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                    <a href="{{ route('gh.categories.index') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const search = document.getElementById('gh-cat-svc-search');
    const items = document.querySelectorAll('.gh-cat-svc-item');
    if (!search || !items.length) return;
    search.addEventListener('input', function () {
        const q = (search.value || '').trim().toLowerCase();
        items.forEach(function (el) {
            if (!q) {
                el.hidden = false;
                return;
            }
            const ar = el.getAttribute('data-name-ar') || '';
            const en = el.getAttribute('data-name-en') || '';
            el.hidden = !(ar.includes(q) || en.includes(q));
        });
    });
})();
</script>
@endpush
