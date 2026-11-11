{{-- Legacy baseline: git HEAD for resources/views/gh/contracts/index.blade.php --}}
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.contracts.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.contracts.subtitle') }}</p>
        </div>
        @can('create', App\Models\GeneralHouse\GhB2bContract::class)
            <a href="{{ route('gh.contracts.create') }}" class="btn btn-primary btn-sm">{{ __('gh.contracts.create') }}</a>
        @endcan
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-3">
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

            @if ($rows->isEmpty())
                <x-empty-state :title="__('gh.contracts.empty')" icon="bi-file-earmark-text" :text="__('gh.contracts.empty_hint')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('gh.fields.reference') }}</th><th>{{ __('gh.fields.customer') }}</th>
                            <th>{{ __('gh.fields.effective_from') }}</th><th>{{ __('gh.fields.effective_to') }}</th>
                            <th>{{ __('gh.fields.status') }}</th><th></th>
                        </tr></thead>
                        <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><a href="{{ route('gh.contracts.show', $row) }}" class="hp-value">{{ $row->reference }}</a></td>
                                <td>{{ $row->customer?->localizedName() }}</td>
                                <td class="num-ltr">{{ $row->effective_from->toDateString() }}</td>
                                <td class="num-ltr">{{ $row->effective_to?->toDateString() ?? __('gh.contracts.open_ended') }}</td>
                                <td>{{ __('gh.contract_status.'.$row->status) }}</td>
                                <td class="text-end"><a href="{{ route('gh.contracts.show', $row) }}" class="btn btn-link btn-sm">{{ __('general.edit') }}</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $rows->links() }}</div>
            @endif
        </div>
    </div>
    @include('gh.import.partials.import-card', ['entity' => 'contracts', 'model' => App\Models\GeneralHouse\GhB2bContract::class])
