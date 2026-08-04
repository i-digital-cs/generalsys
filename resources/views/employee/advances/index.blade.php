@extends('layouts.app')

@section('title', __('advances.my_title'))

@section('content')
    <div class="mb-3">
        <h1 class="h4 mb-1">{{ __('advances.my_title') }}</h1>
        <p class="text-body-secondary small mb-0">{{ __('advances.my_subtitle') }}</p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if ($advances->isEmpty())
                <x-empty-state
                    :title="__('advances.my_empty_title')"
                    :text="__('advances.my_empty_text')"
                    icon="bi-cash-coin"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('advances.fields.amount') }}</th>
                                <th scope="col">{{ __('advances.fields.deduction_amount_monthly') }}</th>
                                <th scope="col">{{ __('advances.fields.remaining_amount') }}</th>
                                <th scope="col">{{ __('advances.fields.date_given') }}</th>
                                <th scope="col">{{ __('advances.fields.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($advances as $row)
                                @php
                                    $currency = auth()->user()->currency ?? 'EGP';
                                @endphp
                                <tr>
                                    <td dir="ltr"><x-money :amount="$row->amount" :currency="$currency" /></td>
                                    <td dir="ltr"><x-money :amount="$row->deduction_amount_monthly" :currency="$currency" /></td>
                                    <td dir="ltr" class="fw-semibold">
                                        <x-money :amount="$row->remaining_amount" :currency="$currency" />
                                    </td>
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
