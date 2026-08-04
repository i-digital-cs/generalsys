@extends('layouts.app')

@section('title', __('advances.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('advances.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('advances.subtitle') }}</p>
        </div>
        <a href="{{ route('admin.advances.create') }}" class="btn btn-primary">
            {{ __('advances.create') }}
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.advances.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="user_id" class="form-label">{{ __('advances.fields.employee') }}</label>
                    <select id="user_id" name="user_id" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($employees as $emp)
                            @php
                                $label = app()->getLocale() === 'ar' ? $emp->name_ar : $emp->name_en;
                            @endphp
                            <option value="{{ $emp->id }}" @selected((int) ($filters['user_id'] ?? 0) === $emp->id)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="status" class="form-label">{{ __('advances.fields.status') }}</label>
                    <select id="status" name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach (['ACTIVE', 'FULLY_DEDUCTED'] as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>
                                {{ __('advances.status.'.$st) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">{{ __('advances.list') }}</div>
        <div class="card-body">
            @if ($advances->isEmpty())
                <x-empty-state
                    :title="__('advances.empty_title')"
                    :text="__('advances.empty_text')"
                    icon="bi-cash-coin"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('advances.fields.employee') }}</th>
                                <th scope="col">{{ __('advances.fields.amount') }}</th>
                                <th scope="col">{{ __('advances.fields.deduction_amount_monthly') }}</th>
                                <th scope="col">{{ __('advances.fields.remaining_amount') }}</th>
                                <th scope="col">{{ __('advances.fields.date_given') }}</th>
                                <th scope="col">{{ __('advances.fields.status') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($advances as $row)
                                @php
                                    $emp = $row->user;
                                    $name = $emp
                                        ? (app()->getLocale() === 'ar' ? $emp->name_ar : $emp->name_en)
                                        : __('general.not_available');
                                    $currency = $emp->currency ?? 'EGP';
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $name }}</td>
                                    <td dir="ltr"><x-money :amount="$row->amount" :currency="$currency" /></td>
                                    <td dir="ltr"><x-money :amount="$row->deduction_amount_monthly" :currency="$currency" /></td>
                                    <td dir="ltr"><x-money :amount="$row->remaining_amount" :currency="$currency" /></td>
                                    <td>{{ $row->date_given->translatedFormat('d M Y') }}</td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-success' => $row->status === 'ACTIVE',
                                            'text-bg-secondary' => $row->status === 'FULLY_DEDUCTED',
                                        ])>
                                            {{ __('advances.status.'.$row->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if ($row->status === 'ACTIVE')
                                            <a href="{{ route('admin.advances.edit', $row) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                {{ __('general.edit') }}
                                            </a>
                                        @else
                                            {{ __('general.not_available') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $advances->links() }}</div>
            @endif
        </div>
    </div>
@endsection
