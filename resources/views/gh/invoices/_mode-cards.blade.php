{{-- Modern card presentation (working tree) --}}
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.invoices.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.invoices.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.orders.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.title') }}</a>
    </div>

    <div class="gh-card-ui" data-gh-card-ui="invoices">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="q">{{ __('gh.invoices.search') }}</label>
                        <input id="q" name="q" class="form-control form-control-sm" value="{{ request('q') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="status">{{ __('gh.invoices.status') }}</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            <option value="DRAFT" @selected(request('status')==='DRAFT')>{{ __('gh.invoice_status.DRAFT') }}</option>
                            <option value="ISSUED" @selected(request('status')==='ISSUED')>{{ __('gh.invoice_status.ISSUED') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">{{ __('general.filter') }}</button></div>
                </form>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="gh-card-ui__empty">
                <p class="text-center text-body-secondary mb-0 py-3">{{ __('gh.invoices.empty') }}</p>
            </div>
        @else
            <div class="gh-card-ui__grid">
                @foreach ($rows as $row)
                    @php
                        $customer = app()->getLocale()==='ar' ? $row->customer_name_ar : ($row->customer_name_en ?: $row->customer_name_ar);
                        $title = $row->number ?? '—';
                    @endphp
                    <x-gh.record-card :title="$title" :title-ltr="true">
                        <x-slot:badges>
                            <span class="gh-record-card__badge {{ $row->status==='ISSUED' ? 'gh-record-card__badge--status' : 'gh-record-card__badge--muted' }}">{{ __('gh.invoice_status.'.$row->status) }}</span>
                        </x-slot:badges>
                        <x-slot:meta>
                            <x-gh.meta-cell icon="bi-receipt" :value="$row->order_reference" :ltr="true" />
                            <x-gh.meta-cell icon="bi-person" :value="$customer" />
                            <div class="gh-record-card__cell">
                                <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
                                <span class="gh-record-card__value"><x-money :amount="$row->total_payable" currency="AED" /></span>
                            </div>
                        </x-slot:meta>
                        <x-slot:actions>
                            @if ($row->isDraft())
                                <a href="{{ route('gh.invoices.builder', $row) }}" class="gh-board-btn">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                    <span>{{ __('gh.invoices.builder') }}</span>
                                </a>
                            @else
                                <a href="{{ route('gh.invoices.show', $row) }}" class="gh-board-btn">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                    <span>{{ __('gh.invoices.view') }}</span>
                                </a>
                            @endif
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
