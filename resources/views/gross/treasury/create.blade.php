@extends('layouts.app')

@section('title', $type === 'PAYMENT' ? __('gross.treasury.create_payment') : __('gross.treasury.create_receipt'))

@section('content')
    @php
        $purpose = old('purpose', $purpose ?? 'EXPENSE');
    @endphp
    <h1 class="h4 mb-3">{{ $type === 'PAYMENT' ? __('gross.treasury.create_payment') : __('gross.treasury.create_receipt') }}</h1>
    <p class="text-body-secondary small mb-3">
        {{ $type === 'PAYMENT' ? __('gross.treasury.payment_help') : __('gross.treasury.receipt_help') }}
    </p>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('gross.treasury.store') }}" id="gross-treasury-form">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="purpose">{{ __('gross.treasury.purpose') }}</label>
                        <select id="purpose" name="purpose" class="form-select @error('purpose') is-invalid @enderror" required>
                            <option value="EXPENSE" @selected($purpose === 'EXPENSE')>{{ __('gross.treasury.purposes.EXPENSE') }}</option>
                            <option value="SUPPLIER" @selected($purpose === 'SUPPLIER')>{{ __('gross.treasury.purposes.SUPPLIER') }}</option>
                        </select>
                        @error('purpose') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="account_id">{{ __('gross.treasury.account') }}</label>
                        <select id="account_id" name="account_id" class="form-select @error('account_id') is-invalid @enderror" required>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>
                                    {{ __('gross.treasury.kinds.'.$account->kind) }} — {{ $account->name }} ({{ $account->currency }})
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
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $selectedSupplierId ?? '') === (string) $supplier->id)>
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
                    <div class="col-md-4">
                        <label class="form-label" for="amount">{{ __('gross.fields.amount') }}</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="voucher_date">{{ __('gross.fields.date') }}</label>
                        <input id="voucher_date" name="voucher_date" type="date" class="form-control @error('voucher_date') is-invalid @enderror" value="{{ old('voucher_date', now()->toDateString()) }}" required>
                        @error('voucher_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
    var purpose = document.getElementById('purpose');
    if (!purpose) return;
    function sync() {
        var value = purpose.value;
        document.querySelectorAll('[data-purpose-panel]').forEach(function (el) {
            var show = el.getAttribute('data-purpose-panel') === value;
            el.classList.toggle('d-none', !show);
            el.querySelectorAll('select').forEach(function (sel) {
                sel.required = show;
                if (!show) sel.value = '';
            });
        });
    }
    purpose.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
