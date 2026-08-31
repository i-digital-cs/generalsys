@extends('layouts.app')

@section('title', $voucher->voucher_number)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><span class="num-ltr">{{ $voucher->voucher_number }}</span></h1>
            <p class="text-body-secondary small mb-0">
                {{ __('gross.treasury.types.'.$voucher->type) }}
                · {{ __('gross.treasury.purposes.'.$voucher->purpose) }}
            </p>
        </div>
        <a href="{{ route('gross.treasury.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.back') }}</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('gross.fields.date') }}</dt>
                <dd class="col-sm-9"><span class="num-ltr">{{ $voucher->voucher_date->translatedFormat('d M Y') }}</span></dd>
                <dt class="col-sm-3">{{ __('gross.treasury.account') }}</dt>
                <dd class="col-sm-9">{{ __('gross.treasury.kinds.'.$voucher->account->kind) }} — {{ $voucher->account->name }} ({{ $voucher->currency }})</dd>
                <dt class="col-sm-3">{{ __('gross.fields.amount') }}</dt>
                <dd class="col-sm-9"><span class="num-ltr"><x-money :amount="$voucher->amount" :currency="$voucher->currency" /></span></dd>
                @if ($voucher->isSupplierPurpose() && $voucher->supplier)
                    <dt class="col-sm-3">{{ __('gross.fields.supplier') }}</dt>
                    <dd class="col-sm-9">
                        <a href="{{ route('gross.suppliers.statement', $voucher->supplier) }}">{{ $voucher->supplier->name }}</a>
                    </dd>
                @endif
                @if (! $voucher->isSupplierPurpose() && $voucher->expenseType)
                    <dt class="col-sm-3">{{ __('gross.treasury.expense_type') }}</dt>
                    <dd class="col-sm-9">{{ $voucher->expenseType->localizedName() }}</dd>
                @endif
                <dt class="col-sm-3">{{ __('gross.fields.created_by') }}</dt>
                <dd class="col-sm-9">{{ $voucher->creator?->name }}</dd>
                @if ($voucher->notes)
                    <dt class="col-sm-3">{{ __('gross.fields.notes') }}</dt>
                    <dd class="col-sm-9">{{ $voucher->notes }}</dd>
                @endif
            </dl>
        </div>
    </div>
@endsection
