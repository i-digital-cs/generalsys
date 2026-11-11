{{-- Modern card presentation (working tree) --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.customers.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.customers.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\GeneralHouse\GhCustomer::class)
                <a href="{{ route('gh.customers.create') }}" class="btn btn-primary btn-sm">{{ __('gh.customers.create') }}</a>
            @endcan
            <a href="{{ route('gh.zones.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.zones.title') }}</a>
        </div>
    </div>

    <div class="gh-card-ui" data-gh-card-ui="customers">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                        <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('gh.customers.search_placeholder') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1" for="type">{{ __('gh.fields.type') }}</label>
                        <select id="type" name="type" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach (\App\Support\GeneralHouse\GhCustomerType::ALL as $t)
                                <option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ __('gh.customer_type.'.$t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1" for="status">{{ __('gh.fields.status') }}</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                                <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ __('gh.status.'.$st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto"><button class="btn btn-outline-primary btn-sm" type="submit">{{ __('general.filter') }}</button></div>
                </form>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="gh-card-ui__empty">
                <x-empty-state :title="__('gh.customers.empty')" icon="bi-people" :text="__('gh.customers.empty_hint')" />
            </div>
        @else
            <div class="gh-card-ui__grid">
                @foreach ($rows as $row)
                    <x-gh.record-card
                        :title="$row->localizedName()"
                        :title-href="route('gh.customers.show', $row)"
                    >
                        <x-slot:badges>
                            <span class="gh-record-card__badge gh-record-card__badge--muted">{{ __('gh.customer_type.'.$row->type) }}</span>
                            @include('gh.partials.status-badge', ['status' => $row->status])
                        </x-slot:badges>
                        <x-slot:meta>
                            <x-gh.meta-cell icon="bi-telephone" :value="$row->phone" :ltr="true" />
                            @if ($row->email)
                                <x-gh.meta-cell icon="bi-envelope" :value="$row->email" :ltr="true" />
                            @endif
                        </x-slot:meta>
                        <x-slot:actions>
                            <a href="{{ route('gh.customers.show', $row) }}" class="gh-board-btn">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                                <span>{{ __('gh.customers.show') }}</span>
                            </a>
                            @can('update', $row)
                                <a href="{{ route('gh.customers.edit', $row) }}" class="gh-board-btn gh-board-btn--ghost">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                    <span>{{ __('general.edit') }}</span>
                                </a>
                            @endcan
                        </x-slot:actions>
                    </x-gh.record-card>
                @endforeach
            </div>
            <div class="gh-card-ui__pager">{{ $rows->links() }}</div>
        @endif
    </div>

    @include('gh.import.partials.import-card', ['entity' => 'customers', 'model' => App\Models\GeneralHouse\GhCustomer::class])
