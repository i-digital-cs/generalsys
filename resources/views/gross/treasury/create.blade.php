@extends('layouts.app')

@section('title', $type === 'PAYMENT' ? __('gross.treasury.create_payment') : __('gross.treasury.create_receipt'))

@section('content')
    @php
        $purpose = old('purpose', $purpose ?? 'EXPENSE');
        $accountBalances = [];
        foreach ($accountsWithBalances as $row) {
            $accountBalances[$row['account']->id] = [
                'balance' => $row['balance'],
                'currency' => $row['account']->currency,
                'kind' => $row['account']->kind,
                'name' => $row['account']->name,
            ];
        }
    @endphp
    <h1 class="h4 mb-3">{{ $type === 'PAYMENT' ? __('gross.treasury.create_payment') : __('gross.treasury.create_receipt') }}</h1>
    <p class="text-body-secondary small mb-3">
        {{ $type === 'PAYMENT' ? __('gross.treasury.payment_help') : __('gross.treasury.receipt_help') }}
    </p>

    <div class="row g-3 mb-3" id="treasury-context-panel">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('gross.treasury.context_account_balance') }}</div>
                    <div class="fs-5 fw-semibold num-ltr mt-1" id="treasury-context-account-balance">—</div>
                    <div class="small text-body-secondary mt-1" id="treasury-context-account-name"></div>
                    <div class="small text-body-secondary mt-2" id="treasury-context-account-hint">
                        {{ $type === 'PAYMENT' ? __('gross.treasury.context_payment_hint') : __('gross.treasury.context_receipt_hint') }}
                    </div>
                    <div class="small mt-2 d-none" id="treasury-context-account-after-wrap">
                        <span class="text-body-secondary">{{ __('gross.treasury.context_balance_after') }}:</span>
                        <span class="fw-semibold num-ltr" id="treasury-context-account-after"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8 d-none" id="treasury-context-supplier-wrap">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                        <div class="small text-body-secondary" id="treasury-context-supplier-title">{{ __('gross.fields.supplier') }}</div>
                        <a href="#" class="small text-decoration-none d-none" id="treasury-context-supplier-link" target="_blank" rel="noopener"></a>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <div class="small text-body-secondary">{{ __('gross.suppliers.statement_purchases') }}</div>
                            <div class="fw-semibold num-ltr" id="treasury-context-supplier-purchases">—</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-body-secondary">{{ __('gross.suppliers.statement_returns') }}</div>
                            <div class="fw-semibold num-ltr" id="treasury-context-supplier-returns">—</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-body-secondary">{{ __('gross.suppliers.statement_payments') }}</div>
                            <div class="fw-semibold num-ltr" id="treasury-context-supplier-payments">—</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-body-secondary">{{ __('gross.suppliers.statement_balance') }}</div>
                            <div class="fw-semibold num-ltr" id="treasury-context-supplier-balance">—</div>
                            <div class="small text-body-secondary">{{ __('gross.suppliers.statement_balance_hint') }}</div>
                        </div>
                    </div>
                    <div class="small mt-2 d-none" id="treasury-context-supplier-after-wrap">
                        <span class="text-body-secondary">{{ __('gross.treasury.context_supplier_after') }}:</span>
                        <span class="fw-semibold num-ltr" id="treasury-context-supplier-after"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('gross.treasury.store') }}" id="gross-treasury-form" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="purpose_ui">{{ __('gross.treasury.purpose') }}</label>
                        @if ($type === 'RECEIPT')
                            @php
                                $selectedRp = (string) old('receipt_purpose_id', $selectedReceiptPurposeId ?? '');
                                $uiPurposeValue = $purpose === 'OTHER' && $selectedRp !== ''
                                    ? 'OTHER:'.$selectedRp
                                    : $purpose;
                            @endphp
                            <select id="purpose_ui" class="form-select @error('purpose') is-invalid @enderror @error('receipt_purpose_id') is-invalid @enderror" required>
                                @forelse ($receiptPurposes as $receiptPurpose)
                                    <option value="OTHER:{{ $receiptPurpose->id }}"
                                            @selected($uiPurposeValue === 'OTHER:'.$receiptPurpose->id)>
                                        {{ $receiptPurpose->localizedName() }}
                                    </option>
                                @empty
                                    {{-- no admin purposes yet — system options only --}}
                                @endforelse
                                <option value="EXPENSE" @selected($uiPurposeValue === 'EXPENSE')>{{ __('gross.treasury.purposes.EXPENSE') }}</option>
                                <option value="SUPPLIER" @selected($uiPurposeValue === 'SUPPLIER')>{{ __('gross.treasury.purposes.SUPPLIER') }}</option>
                                <option value="COURIER_COD" @selected($uiPurposeValue === 'COURIER_COD')>{{ __('gross.treasury.purposes.COURIER_COD') }}</option>
                            </select>
                            <input type="hidden" name="purpose" id="purpose" value="{{ $purpose }}">
                            <input type="hidden" name="receipt_purpose_id" id="receipt_purpose_id" value="{{ $selectedRp }}">
                            @error('purpose') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @error('receipt_purpose_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <div class="form-text">{{ __('gross.treasury.receipt_other_help') }}</div>
                            @can('viewAny', App\Models\Gross\GrossReceiptPurpose::class)
                                <div class="form-text">
                                    <a href="{{ route('gross.receipt-purposes.index') }}">{{ __('gross.receipt_purposes.manage_link') }}</a>
                                </div>
                            @endcan
                        @else
                            <select id="purpose" name="purpose" class="form-select @error('purpose') is-invalid @enderror" required>
                                <option value="EXPENSE" @selected($purpose === 'EXPENSE')>{{ __('gross.treasury.purposes.EXPENSE') }}</option>
                                <option value="SUPPLIER" @selected($purpose === 'SUPPLIER')>{{ __('gross.treasury.purposes.SUPPLIER') }}</option>
                            </select>
                            @error('purpose') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="account_id">{{ __('gross.treasury.account') }}</label>
                        <select id="account_id" name="account_id" class="form-select @error('account_id') is-invalid @enderror" required>
                            @foreach ($accountsWithBalances as $row)
                                @php $account = $row['account']; @endphp
                                <option value="{{ $account->id }}"
                                        data-currency="{{ $account->currency }}"
                                        @selected((string) old('account_id') === (string) $account->id)>
                                    {{ __('gross.treasury.kinds.'.$account->kind) }} — {{ $account->name }} ({{ $account->currency }}) · {{ $row['balance'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6" data-purpose-panel="SUPPLIER">
                        <label class="form-label" for="supplier_id">{{ __('gross.fields.supplier') }}</label>
                        <select id="supplier_id" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                            <option value="">{{ __('gross.treasury.pick_supplier') }}</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}"
                                        data-statement-url="{{ route('gross.suppliers.statement', $supplier) }}"
                                        @selected((string) old('supplier_id', $selectedSupplierId ?? '') === (string) $supplier->id)>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6" data-purpose-panel="EXPENSE">
                        <label class="form-label" for="expense_type_id">{{ __('gross.treasury.expense_type') }}</label>
                        <select id="expense_type_id" name="expense_type_id" class="form-select @error('expense_type_id') is-invalid @enderror">
                            <option value="">{{ __('gross.treasury.pick_expense_type') }}</option>
                            @foreach ($expenseTypes as $expenseType)
                                <option value="{{ $expenseType->id }}" @selected((string) old('expense_type_id', $selectedExpenseTypeId ?? '') === (string) $expenseType->id)>
                                    {{ $expenseType->localizedName() }}
                                </option>
                            @endforeach
                        </select>
                        @error('expense_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">
                            <a href="{{ route('gross.expense-types.index') }}">{{ __('gross.expense_types.manage_link') }}</a>
                        </div>
                    </div>
                    <div class="col-md-6" data-purpose-panel="COURIER_COD">
                        <label class="form-label" for="sales_invoice_id">{{ __('gross.fields.invoice_number') }}</label>
                        <select id="sales_invoice_id" name="sales_invoice_id" class="form-select @error('sales_invoice_id') is-invalid @enderror"
                                data-courier-cod-amount>
                            <option value="">{{ __('gross.treasury.pick_courier_cod_invoice') }}</option>
                            @foreach ($courierCodInvoices as $codInvoice)
                                <option value="{{ $codInvoice->id }}"
                                        data-amount="{{ $codInvoice->collectibleAmount() }}"
                                        @selected((string) old('sales_invoice_id', $selectedSalesInvoiceId ?? '') === (string) $codInvoice->id)>
                                    {{ $codInvoice->invoice_number }} — {{ $codInvoice->customer_name }} ({{ number_format($codInvoice->collectibleAmount(), 2, '.', '') }} {{ $codInvoice->currency }})
                                </option>
                            @endforeach
                        </select>
                        @error('sales_invoice_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">{{ __('gross.treasury.courier_cod_help') }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="amount">{{ __('gross.fields.amount') }}</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0.01"
                               class="form-control @error('amount') is-invalid @enderror"
                               value="{{ old('amount') }}" required>
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="voucher_date">{{ __('gross.fields.date') }}</label>
                        <input id="voucher_date" name="voucher_date" type="date" class="form-control @error('voucher_date') is-invalid @enderror" value="{{ old('voucher_date', now()->toDateString()) }}" required>
                        @error('voucher_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="attachments">{{ __('gross.treasury.attachments') }}</label>
                        <input id="attachments" name="attachments[]" type="file"
                               class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror"
                               accept=".jpg,.jpeg,.png,.pdf" multiple>
                        <div class="form-text">{{ __('gross.treasury.attachments_help') }}</div>
                        @error('attachments') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @error('attachments.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="notes">{{ __('gross.fields.notes') }}</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                    <a href="{{ route('gross.treasury.index') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var voucherType = @json($type);
    var accounts = @json($accountBalances);
    var supplierSummaries = @json($supplierSummaries);
    var statementTitleTemplate = @json(__('gross.suppliers.statement_title', ['name' => ':name']));

    var purpose = document.getElementById('purpose');
    var purposeUi = document.getElementById('purpose_ui');
    var receiptPurposeId = document.getElementById('receipt_purpose_id');
    var accountSelect = document.getElementById('account_id');
    var supplierSelect = document.getElementById('supplier_id');
    var amount = document.getElementById('amount');
    var invoiceSelect = document.getElementById('sales_invoice_id');

    if (!purpose) return;

    function syncPurposeFromUi() {
        if (!purposeUi) return;
        var raw = purposeUi.value || '';
        if (raw.indexOf('OTHER:') === 0) {
            purpose.value = 'OTHER';
            if (receiptPurposeId) {
                receiptPurposeId.value = raw.slice(6);
            }
        } else {
            purpose.value = raw;
            if (receiptPurposeId) {
                receiptPurposeId.value = '';
            }
        }
    }

    function panelMatches(el, value) {
        var panels = (el.getAttribute('data-purpose-panel') || '').split(',');
        return panels.indexOf(value) !== -1;
    }

    function formatMoney(value, currency) {
        var num = Number(value);
        if (!isFinite(num)) return '—';
        return num.toFixed(2) + ' ' + (currency || '');
    }

    function parseAmount() {
        var raw = amount ? parseFloat(amount.value) : NaN;
        return isFinite(raw) && raw > 0 ? raw : 0;
    }

    function selectedAccount() {
        if (!accountSelect || !accountSelect.value) return null;
        return accounts[accountSelect.value] || null;
    }

    function selectedSupplierSummary() {
        if (!supplierSelect || !supplierSelect.value) return null;
        var account = selectedAccount();
        var currency = account ? account.currency : 'EGP';
        var bucket = supplierSummaries[currency] || {};
        return bucket[supplierSelect.value] || {
            total_purchases: '0.00',
            total_returns: '0.00',
            total_payments: '0.00',
            balance: '0.00',
            currency: currency
        };
    }

    function syncCourierAmount() {
        if (!invoiceSelect || !amount) return;
        var opt = invoiceSelect.options[invoiceSelect.selectedIndex];
        var val = opt ? opt.getAttribute('data-amount') : '';
        if (purpose.value === 'COURIER_COD' && val) {
            amount.value = val;
            amount.readOnly = true;
        } else {
            amount.readOnly = false;
        }
    }

    function syncContext() {
        var account = selectedAccount();
        var accountBalanceEl = document.getElementById('treasury-context-account-balance');
        var accountNameEl = document.getElementById('treasury-context-account-name');
        var accountAfterWrap = document.getElementById('treasury-context-account-after-wrap');
        var accountAfterEl = document.getElementById('treasury-context-account-after');
        var supplierWrap = document.getElementById('treasury-context-supplier-wrap');
        var supplierAfterWrap = document.getElementById('treasury-context-supplier-after-wrap');
        var supplierAfterEl = document.getElementById('treasury-context-supplier-after');
        var supplierLink = document.getElementById('treasury-context-supplier-link');
        var entryAmount = parseAmount();

        if (!account) {
            if (accountBalanceEl) accountBalanceEl.textContent = '—';
            if (accountNameEl) accountNameEl.textContent = @json(__('gross.treasury.context_select_account'));
            if (accountAfterWrap) accountAfterWrap.classList.add('d-none');
        } else {
            var balance = parseFloat(account.balance);
            if (accountBalanceEl) accountBalanceEl.textContent = formatMoney(balance, account.currency);
            if (accountNameEl) {
                accountNameEl.textContent = account.name + ' · ' + account.currency;
            }
            if (entryAmount > 0) {
                var nextBalance = voucherType === 'RECEIPT'
                    ? balance + entryAmount
                    : balance - entryAmount;
                if (accountAfterEl) accountAfterEl.textContent = formatMoney(nextBalance, account.currency);
                if (accountAfterWrap) accountAfterWrap.classList.remove('d-none');
            } else if (accountAfterWrap) {
                accountAfterWrap.classList.add('d-none');
            }
        }

        var showSupplier = purpose.value === 'SUPPLIER' && supplierSelect && supplierSelect.value;
        if (supplierWrap) supplierWrap.classList.toggle('d-none', !showSupplier);

        if (showSupplier) {
            var summary = selectedSupplierSummary();
            var opt = supplierSelect.options[supplierSelect.selectedIndex];
            var supplierName = opt ? opt.textContent.trim() : '';
            var titleEl = document.getElementById('treasury-context-supplier-title');
            if (titleEl) titleEl.textContent = supplierName;

            var purchasesEl = document.getElementById('treasury-context-supplier-purchases');
            var returnsEl = document.getElementById('treasury-context-supplier-returns');
            var paymentsEl = document.getElementById('treasury-context-supplier-payments');
            var balanceEl = document.getElementById('treasury-context-supplier-balance');

            if (purchasesEl) purchasesEl.textContent = formatMoney(summary.total_purchases, summary.currency);
            if (returnsEl) returnsEl.textContent = formatMoney(summary.total_returns, summary.currency);
            if (paymentsEl) paymentsEl.textContent = formatMoney(summary.total_payments, summary.currency);
            if (balanceEl) balanceEl.textContent = formatMoney(summary.balance, summary.currency);

            if (supplierLink && opt) {
                var url = opt.getAttribute('data-statement-url');
                if (url) {
                    supplierLink.href = url;
                    supplierLink.textContent = statementTitleTemplate.replace(':name', supplierName);
                    supplierLink.classList.remove('d-none');
                } else {
                    supplierLink.classList.add('d-none');
                }
            }

            if (voucherType === 'PAYMENT' && entryAmount > 0) {
                var owedAfter = parseFloat(summary.balance) - entryAmount;
                if (supplierAfterEl) supplierAfterEl.textContent = formatMoney(owedAfter, summary.currency);
                if (supplierAfterWrap) supplierAfterWrap.classList.remove('d-none');
            } else if (supplierAfterWrap) {
                supplierAfterWrap.classList.add('d-none');
            }
        } else if (supplierAfterWrap) {
            supplierAfterWrap.classList.add('d-none');
        }
    }

    function sync() {
        syncPurposeFromUi();
        var value = purpose.value;
        document.querySelectorAll('[data-purpose-panel]').forEach(function (el) {
            var show = panelMatches(el, value);
            el.classList.toggle('d-none', !show);
            el.querySelectorAll('select').forEach(function (sel) {
                sel.required = show;
                if (!show) sel.value = '';
            });
        });
        if (amount) {
            amount.required = value !== 'COURIER_COD' || (invoiceSelect && invoiceSelect.value !== '');
        }
        syncCourierAmount();
        syncContext();
    }

    if (purposeUi) {
        purposeUi.addEventListener('change', sync);
    } else {
        purpose.addEventListener('change', sync);
    }
    if (accountSelect) accountSelect.addEventListener('change', syncContext);
    if (supplierSelect) supplierSelect.addEventListener('change', syncContext);
    if (amount) amount.addEventListener('input', syncContext);
    if (invoiceSelect) invoiceSelect.addEventListener('change', function () {
        syncCourierAmount();
        syncContext();
    });
    sync();
})();
</script>
@endpush
