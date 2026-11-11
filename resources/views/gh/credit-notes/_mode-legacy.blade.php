{{-- Legacy baseline: git HEAD for resources/views/gh/credit-notes/index.blade.php --}}
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.credit_notes.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.credit_notes.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.invoices.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.invoices.title') }}</a>
    </div>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-4">
            <label class="form-label" for="status">{{ __('gh.invoices.status') }}</label>
            <select id="status" name="status" class="form-select form-select-sm">
                <option value="">{{ __('general.all') }}</option>
                @foreach (['DRAFT','PENDING_APPROVAL','APPROVED','ISSUED','REJECTED','CANCELLED'] as $st)
                    <option value="{{ $st }}" @selected(request('status')===$st)>{{ __('gh.cn_status.'.$st) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">{{ __('general.filter') }}</button></div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('gh.credit_notes.number') }}</th>
                    <th>{{ __('gh.invoices.number') }}</th>
                    <th>{{ __('gh.invoices.status') }}</th>
                    <th class="text-end">{{ __('gh.credit_notes.total') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="num-ltr">{{ $row->number ?: '—' }}</td>
                        <td class="num-ltr">{{ $row->invoice?->number }}</td>
                        <td><span class="badge text-bg-secondary">{{ __('gh.cn_status.'.$row->status) }}</span></td>
                        <td class="text-end"><x-money :amount="$row->total_credit" currency="AED" /></td>
                        <td class="text-end">
                            <a href="{{ route('gh.credit-notes.show', $row) }}" class="btn btn-sm btn-outline-primary">{{ __('general.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('gh.credit_notes.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer bg-transparent">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
