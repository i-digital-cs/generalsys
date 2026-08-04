@extends('layouts.app')

@section('title', __('salary.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('salary.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('salary.subtitle') }}</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">{{ __('salary.list') }}</div>
        <div class="card-body">
            @if ($records->isEmpty())
                <x-empty-state
                    :title="__('salary.empty_title')"
                    :text="__('salary.empty_text')"
                    icon="bi-wallet2"
                />
            @else
                {{-- Mobile: stacked cards (T-5.4) --}}
                <div class="d-md-none employee-mobile-stack vstack gap-3">
                    @foreach ($records as $row)
                        @php
                            $period = \Carbon\Carbon::create($row->year, $row->month, 1)->translatedFormat('F Y');
                        @endphp
                        <div class="border rounded-3 p-3">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div class="fw-semibold">{{ $period }}</div>
                                <span @class([
                                    'badge',
                                    'text-bg-success' => $row->status === 'APPROVED',
                                    'text-bg-primary' => $row->status === 'PAID',
                                ])>
                                    {{ __('payroll.status.'.$row->status) }}
                                </span>
                            </div>
                            <div class="small text-body-secondary mb-1">{{ __('salary.fields.gross') }}</div>
                            <div class="mb-2" dir="ltr">
                                <x-money :amount="$row->gross_pay" :currency="$row->currency" />
                            </div>
                            <div class="small text-body-secondary mb-1">{{ __('salary.fields.net') }}</div>
                            <div class="fw-semibold mb-3" dir="ltr">
                                <x-money :amount="$row->net_pay" :currency="$row->currency" />
                            </div>
                            <a href="{{ route('employee.salary.show', $row) }}"
                               class="btn btn-outline-primary w-100">
                                {{ __('salary.open') }}
                            </a>
                        </div>
                    @endforeach
                </div>

                {{-- Desktop / tablet table --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('salary.fields.period') }}</th>
                                <th scope="col">{{ __('salary.fields.status') }}</th>
                                <th scope="col">{{ __('salary.fields.gross') }}</th>
                                <th scope="col">{{ __('salary.fields.net') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $row)
                                @php
                                    $period = \Carbon\Carbon::create($row->year, $row->month, 1)->translatedFormat('F Y');
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $period }}</td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-success' => $row->status === 'APPROVED',
                                            'text-bg-primary' => $row->status === 'PAID',
                                        ])>
                                            {{ __('payroll.status.'.$row->status) }}
                                        </span>
                                    </td>
                                    <td dir="ltr">
                                        <x-money :amount="$row->gross_pay" :currency="$row->currency" />
                                    </td>
                                    <td dir="ltr" class="fw-semibold">
                                        <x-money :amount="$row->net_pay" :currency="$row->currency" />
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('employee.salary.show', $row) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            {{ __('salary.open') }}
                                        </a>
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
