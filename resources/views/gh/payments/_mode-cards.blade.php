{{-- Modern card presentation (working tree) --}}
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.payments.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.payments.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.invoices.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.invoices.title') }}</a>
    </div>

    <div class="gh-card-ui" data-gh-card-ui="payments">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="q">{{ __('gh.payments.search') }}</label>
                        <input id="q" name="q" class="form-control form-control-sm" value="{{ request('q') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="status">{{ __('gh.invoices.status') }}</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach (['PENDING','CONFIRMED','CANCELLED'] as $st)
                                <option value="{{ $st }}" @selected(request('status')===$st)>{{ __('gh.pay_status.'.$st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="method">{{ __('gh.payments.method') }}</label>
                        <select id="method" name="method" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            <option value="CASH" @selected(request('method')==='CASH')>{{ __('gh.payment_method.CASH') }}</option>
                            <option value="ONLINE" @selected(request('method')==='ONLINE')>{{ __('gh.payment_method.ONLINE') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">{{ __('general.filter') }}</button></div>
                </form>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="gh-card-ui__empty">
                <p class="text-center text-body-secondary mb-0 py-3">{{ __('gh.payments.empty') }}</p>
            </div>
        @else
            <div class="gh-card-ui__grid">
                @foreach ($rows as $row)
                    <x-gh.record-card :title="$row->reference" :title-ltr="true">
                        <x-slot:badges>
                            <span class="gh-record-card__badge {{ $row->status === 'CONFIRMED' ? 'gh-record-card__badge--status' : 'gh-record-card__badge--muted' }}">{{ __('gh.pay_status.'.$row->status) }}</span>
                        </x-slot:badges>
                        <x-slot:meta>
                            <x-gh.meta-cell icon="bi-receipt" :value="$row->invoice?->number ?? '—'" :ltr="true" />
                            <x-gh.meta-cell icon="bi-wallet2" :value="__('gh.payment_method.'.$row->method)" />
                            <div class="gh-record-card__cell">
                                <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
                                <span class="gh-record-card__value"><x-money :amount="$row->amount" currency="AED" /></span>
                            </div>
                        </x-slot:meta>
                        <x-slot:actions>
                            @if ($row->invoice)
                                <a href="{{ route('gh.invoices.show', $row->invoice) }}" class="gh-board-btn">
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
