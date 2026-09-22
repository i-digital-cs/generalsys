@extends('layouts.app')

@section('title', $voucher->voucher_number)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><span class="num-ltr">{{ $voucher->voucher_number }}</span></h1>
            <p class="text-body-secondary small mb-0">
                {{ __('gross.treasury.types.'.$voucher->type) }}
                · @if ($voucher->isOtherPurpose() && $voucher->receiptPurpose)
                    {{ $voucher->receiptPurpose->localizedName() }}
                @else
                    {{ __('gross.treasury.purposes.'.$voucher->purpose) }}
                @endif
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
                @if ($voucher->isOtherPurpose() && $voucher->receiptPurpose)
                    <dt class="col-sm-3">{{ __('gross.treasury.receipt_purpose') }}</dt>
                    <dd class="col-sm-9">{{ $voucher->receiptPurpose->localizedName() }}</dd>
                @endif
                @if ($voucher->isCourierCodPurpose() && $voucher->salesInvoice)
                    <dt class="col-sm-3">{{ __('gross.fields.invoice_number') }}</dt>
                    <dd class="col-sm-9">
                        <a href="{{ route('gross.sales.show', $voucher->salesInvoice) }}" class="num-ltr text-decoration-none">
                            {{ $voucher->salesInvoice->invoice_number }}
                        </a>
                    </dd>
                    <dt class="col-sm-3">{{ __('gross.treasury.cod_merchandise_only') }}</dt>
                    <dd class="col-sm-9 small text-body-secondary">{{ __('gross.treasury.cod_merchandise_only_hint') }}</dd>
                @endif
                @if ($voucher->isCourierCodPurpose() && $voucher->shippingCompany)
                    <dt class="col-sm-3">{{ __('gross.fields.shipping_company') }}</dt>
                    <dd class="col-sm-9">
                        <a href="{{ route('gross.shipping.show', $voucher->shippingCompany) }}">{{ $voucher->shippingCompany->name }}</a>
                    </dd>
                @endif
                @if ($voucher->isSupplierPurpose() && $voucher->supplier)
                    <dt class="col-sm-3">{{ __('gross.fields.supplier') }}</dt>
                    <dd class="col-sm-9">
                        <a href="{{ route('gross.suppliers.statement', $voucher->supplier) }}">{{ $voucher->supplier->name }}</a>
                    </dd>
                @endif
                @if (! $voucher->isSupplierPurpose() && ! $voucher->isCourierCodPurpose() && ! $voucher->isOtherPurpose() && $voucher->expenseType)
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

    @if ($voucher->attachments->isNotEmpty())
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h2 class="h6 mb-3">{{ __('gross.treasury.attachments') }}</h2>
                <ul class="list-group list-group-flush">
                    @foreach ($voucher->attachments as $attachment)
                        <li class="list-group-item px-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <i class="bi {{ str_contains($attachment->mime_type, 'pdf') ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image' }} text-body-secondary" aria-hidden="true"></i>
                                <span class="text-truncate">{{ $attachment->original_name }}</span>
                                <span class="small text-body-secondary num-ltr">({{ number_format($attachment->size_bytes / 1024, 1) }} KB)</span>
                            </div>
                            <a href="{{ route('gross.treasury.attachments.download', [$voucher, $attachment]) }}"
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('documents.download') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
@endsection
