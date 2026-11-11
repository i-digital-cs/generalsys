{{-- Modern card presentation (working tree) --}}
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.refunds.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.refunds.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.invoices.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.invoices.title') }}</a>
    </div>

    <div class="gh-card-ui" data-gh-card-ui="refunds">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="status">{{ __('gh.invoices.status') }}</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach (['REQUESTED','PENDING_APPROVAL','APPROVED','PAID_OUT','REJECTED','CANCELLED'] as $st)
                                <option value="{{ $st }}" @selected(request('status')===$st)>{{ __('gh.rf_status.'.$st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">{{ __('general.filter') }}</button></div>
                </form>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="gh-card-ui__empty">
                <p class="text-center text-body-secondary mb-0 py-3">{{ __('gh.refunds.empty') }}</p>
            </div>
        @else
            <div class="gh-card-ui__grid">
                @foreach ($rows as $row)
                    <x-gh.record-card :title="$row->reference ?: '—'" :title-ltr="true">
                        <x-slot:badges>
                            <span class="gh-record-card__badge gh-record-card__badge--muted">{{ __('gh.rf_status.'.$row->status) }}</span>
                        </x-slot:badges>
                        <x-slot:meta>
                            <x-gh.meta-cell icon="bi-receipt" :value="$row->invoice?->number ?? '—'" :ltr="true" />
                            <div class="gh-record-card__cell">
                                <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
                                <span class="gh-record-card__value"><x-money :amount="$row->amount" currency="AED" /></span>
                            </div>
                        </x-slot:meta>
                        <x-slot:actions>
                            <a href="{{ route('gh.refunds.show', $row) }}" class="gh-board-btn">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                                <span>{{ __('general.view') }}</span>
                            </a>
                        </x-slot:actions>
                    </x-gh.record-card>
                @endforeach
            </div>
            @if ($rows->hasPages())
                <div class="gh-card-ui__pager">{{ $rows->links() }}</div>
            @endif
        @endif
    </div>
</div>
