@extends('layouts.app')

@section('title', __('payroll.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('payroll.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('payroll.subtitle') }}</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.payroll.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-6 col-md-3">
                    <label for="month" class="form-label">{{ __('payroll.fields.month') }}</label>
                    <select id="month" name="month" class="form-select" onchange="this.form.submit()">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((int) $month === $m)>
                                {{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="year" class="form-label">{{ __('payroll.fields.year') }}</label>
                    <select id="year" name="year" class="form-select" onchange="this.form.submit()">
                        @for ($y = now()->year; $y >= now()->year - 2; $y--)
                            <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="status" class="form-label">{{ __('payroll.fields.status') }}</label>
                    <select id="status" name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach (['DRAFT', 'APPROVED', 'PAID'] as $st)
                            <option value="{{ $st }}" @selected($status === $st)>
                                {{ __('payroll.status.'.$st) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.payroll.generate') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-play-circle me-1" aria-hidden="true"></i>
                        {{ __('payroll.generate') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">{{ __('payroll.list') }}</div>
        <div class="card-body">
            @if ($records->isEmpty())
                <x-empty-state
                    :title="__('payroll.empty_title')"
                    :text="__('payroll.empty_text')"
                    icon="bi-cash-stack"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('payroll.fields.employee') }}</th>
                                <th scope="col">{{ __('payroll.fields.status') }}</th>
                                <th scope="col">{{ __('payroll.fields.gross') }}</th>
                                <th scope="col">{{ __('payroll.fields.deductions') }}</th>
                                <th scope="col">{{ __('payroll.fields.net') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $row)
                                @php
                                    $emp = $row->user;
                                    $name = $emp
                                        ? (app()->getLocale() === 'ar' ? $emp->name_ar : $emp->name_en)
                                        : __('general.not_available');
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $name }}</td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-secondary' => $row->status === 'DRAFT',
                                            'text-bg-success' => $row->status === 'APPROVED',
                                            'text-bg-primary' => $row->status === 'PAID',
                                        ])>
                                            {{ __('payroll.status.'.$row->status) }}
                                        </span>
                                    </td>
                                    <td dir="ltr">
                                        <x-money :amount="$row->gross_pay" :currency="$row->currency" />
                                    </td>
                                    <td dir="ltr">
                                        <x-money :amount="$row->total_deductions" :currency="$row->currency" />
                                    </td>
                                    <td dir="ltr" class="fw-semibold">
                                        <x-money :amount="$row->net_pay" :currency="$row->currency" />
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.payroll.show', $row) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            {{ __('payroll.review') }}
                                        </a>
                                        @if ($row->status === 'DRAFT')
                                            <form method="POST" action="{{ route('admin.payroll.approve', $row) }}" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    {{ __('payroll.approve') }}
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
