{{-- Modern card presentation (working tree) --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.products.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.products.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\GeneralHouse\GhProduct::class)
                <a href="{{ route('gh.products.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('gh.products.create') }}
                </a>
            @endcan
            <a href="{{ route('gh.offerings.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.offerings.title') }}</a>
        </div>
    </div>

    <div class="gh-card-ui" data-gh-card-ui="products">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                        <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"
                               placeholder="{{ __('gh.products.search_placeholder') }}" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1" for="category_id">{{ __('gh.fields.category') }}</label>
                        <select id="category_id" name="category_id" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $cat->id)>
                                    {{ $cat->localizedName() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1" for="filter_status">{{ __('gh.fields.status') }}</label>
                        <select id="filter_status" name="status" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                                <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ __('gh.status.'.$st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                        @if (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || ($filters['category_id'] ?? '') !== '')
                            <a href="{{ route('gh.products.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="gh-card-ui__empty">
                <x-empty-state :title="__('gh.products.empty')" icon="bi-box-seam" :text="__('gh.products.empty_hint')" />
            </div>
        @else
            <div class="gh-card-ui__grid">
                @foreach ($rows as $row)
                    @php
                        $productImage = $row->imageUrl();
                    @endphp
                    <x-gh.record-card
                        :title="$row->localizedName()"
                        :title-href="auth()->user()?->can('update', $row) ? route('gh.products.edit', $row) : null"
                        :image-url="$productImage"
                        :image-alt="$row->localizedName()"
                        image-variant="square"
                        image-placeholder="bi-box-seam"
                    >
                        <x-slot:badges>
                            @include('gh.partials.status-badge', ['status' => $row->status])
                        </x-slot:badges>
                        <x-slot:meta>
                            <x-gh.meta-cell icon="bi-upc" :value="$row->code" :ltr="true" />
                            <x-gh.meta-cell icon="bi-translate" :value="$row->name_ar" />
                            <x-gh.meta-cell icon="bi-type" :value="$row->name_en" :ltr="true" />
                            <x-gh.meta-cell icon="bi-folder2" :value="$row->category?->localizedName() ?? '—'" />
                        </x-slot:meta>
                        <x-slot:actions>
                            @can('update', $row)
                                <a href="{{ route('gh.products.edit', $row) }}" class="gh-board-btn gh-board-btn--ghost">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                    <span>{{ __('general.edit') }}</span>
                                </a>
                                <form method="post" action="{{ route('gh.products.destroy', $row) }}" class="d-inline"
                                      onsubmit="return confirm(@json(__('gh.products.confirm_delete')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="gh-board-btn gh-board-btn--ghost text-danger">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                        <span>{{ __('general.delete') }}</span>
                                    </button>
                                </form>
                            @endcan
                        </x-slot:actions>
                    </x-gh.record-card>
                @endforeach
            </div>
            <div class="gh-card-ui__pager">{{ $rows->links() }}</div>
        @endif
    </div>
    @include('gh.import.partials.import-card', ['entity' => 'products', 'model' => App\Models\GeneralHouse\GhProduct::class, 'withZip' => true])
