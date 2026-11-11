{{-- Modern card presentation (working tree) --}}
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.contracts.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.contracts.subtitle') }}</p>
        </div>
        @can('create', App\Models\GeneralHouse\GhB2bContract::class)
            <a href="{{ route('gh.contracts.create') }}" class="btn btn-primary btn-sm">{{ __('gh.contracts.create') }}</a>
        @endcan
    </div>

    <div class="gh-card-ui" data-gh-card-ui="contracts">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-4"><label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                        <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('gh.contracts.search_placeholder') }}"></div>
                    <div class="col-md-3"><label class="form-label mb-1" for="customer_id">{{ __('gh.fields.customer') }}</label>
                        <select id="customer_id" name="customer_id" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $c->id)>{{ $c->localizedName() }}</option>
                            @endforeach
                        </select></div>
                    <div class="col-md-2"><label class="form-label mb-1" for="status">{{ __('gh.fields.status') }}</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach (\App\Support\GeneralHouse\GhContractStatus::ALL as $st)
                                <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ __('gh.contract_status.'.$st) }}</option>
                            @endforeach
                        </select></div>
                    <div class="col-auto"><button class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button></div>
                </form>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="gh-card-ui__empty">
                <x-empty-state :title="__('gh.contracts.empty')" icon="bi-file-earmark-text" :text="__('gh.contracts.empty_hint')" />
            </div>
        @else
            <div class="gh-card-ui__grid">
                @foreach ($rows as $row)
                    <x-gh.record-card
                        :title="$row->reference"
                        :title-href="route('gh.contracts.show', $row)"
                        :title-ltr="true"
                    >
                        <x-slot:badges>
                            <span class="gh-record-card__badge gh-record-card__badge--status">{{ __('gh.contract_status.'.$row->status) }}</span>
                        </x-slot:badges>
                        <x-slot:meta>
                            <x-gh.meta-cell icon="bi-person" :value="$row->customer?->localizedName() ?? '—'" />
                            <x-gh.meta-cell icon="bi-calendar3" :value="$row->effective_from->toDateString()" :ltr="true" />
                            <x-gh.meta-cell icon="bi-calendar-x" :value="$row->effective_to?->toDateString() ?? __('gh.contracts.open_ended')" :ltr="true" />
                        </x-slot:meta>
                        <x-slot:actions>
                            <a href="{{ route('gh.contracts.show', $row) }}" class="gh-board-btn">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                                <span>{{ __('general.edit') }}</span>
                            </a>
                        </x-slot:actions>
                    </x-gh.record-card>
                @endforeach
            </div>
            <div class="gh-card-ui__pager">{{ $rows->links() }}</div>
        @endif
    </div>
    @include('gh.import.partials.import-card', ['entity' => 'contracts', 'model' => App\Models\GeneralHouse\GhB2bContract::class])
