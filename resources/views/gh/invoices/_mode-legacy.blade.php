{{-- Legacy baseline: git HEAD for resources/views/gh/invoices/index.blade.php --}}
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.invoices.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.invoices.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.orders.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.title') }}</a>
    </div>

    <form method="get" class="row g-2 align-items-end mb-3">
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

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('gh.invoices.number') }}</th>
                    <th>{{ __('gh.orders.reference') }}</th>
                    <th>{{ __('gh.fields.customer') ?? __('gh.customers.title') }}</th>
                    <th>{{ __('gh.invoices.status') }}</th>
                    <th class="text-end">{{ __('gh.invoices.total') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="hp-value num-ltr">{{ $row->number ?? '—' }}</td>
                        <td class="num-ltr">{{ $row->order_reference }}</td>
                        <td>{{ app()->getLocale()==='ar' ? $row->customer_name_ar : ($row->customer_name_en ?: $row->customer_name_ar) }}</td>
                        <td><span class="badge text-bg-{{ $row->status==='ISSUED' ? 'success' : 'secondary' }}">{{ __('gh.invoice_status.'.$row->status) }}</span></td>
                        <td class="text-end"><x-money :amount="$row->total_payable" currency="AED" /></td>
                        <td class="text-end">
                            @if ($row->isDraft())
                                <a href="{{ route('gh.invoices.builder', $row) }}" class="btn btn-sm btn-primary">{{ __('gh.invoices.builder') }}</a>
                            @else
                                <a href="{{ route('gh.invoices.show', $row) }}" class="btn btn-sm btn-outline-primary">{{ __('gh.invoices.view') }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('gh.invoices.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer bg-transparent">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
