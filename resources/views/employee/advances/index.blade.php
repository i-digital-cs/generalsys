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
                <div data-table-filter-root>
                    <x-table-toolbar
                        id="employee-advances-list"
                        :search-placeholder="__('general.search_placeholder')"
                        :filters="[[
                            'name' => 'status',
                            'label' => __('advances.fields.status'),
                            'options' => [
                                'ACTIVE' => __('advances.status.ACTIVE'),
                                'FULLY_DEDUCTED' => __('advances.status.FULLY_DEDUCTED'),
                            ],
                        ]]"
                    />
                    <div class="table-responsive">
                        <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
                            <thead>
                                <tr>
                                    <th scope="col" class="col-num">{{ __('advances.fields.amount') }}</th>
                                    <th scope="col" class="col-num">{{ __('advances.fields.deduction_amount_monthly') }}</th>
                                    <th scope="col" class="col-num">{{ __('advances.fields.remaining_amount') }}</th>
                                    <th scope="col" class="col-num">{{ __('advances.fields.date_given') }}</th>
                                    <th scope="col">{{ __('advances.fields.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($advances as $row)
                                    @php
                                        $currency = auth()->user()->currency ?? 'EGP';
                                    @endphp
                                    <tr data-filter-text="{{ $row->date_given->toDateString() }} {{ $row->amount }} {{ $row->remaining_amount }}"
                                        data-filter-status="{{ $row->status }}">
                                        <td class="col-num"><span class="num-ltr"><x-money :amount="$row->amount" :currency="$currency" /></span></td>
                                        <td class="col-num"><span class="num-ltr"><x-money :amount="$row->deduction_amount_monthly" :currency="$currency" /></span></td>
                                        <td class="col-num fw-semibold">
                                            <span class="num-ltr"><x-money :amount="$row->remaining_amount" :currency="$currency" /></span>
                                        </td>
                                        <td class="col-num"><span class="num-ltr">{{ $row->date_given->translatedFormat('d M Y') }}</span></td>
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
                                <tr data-table-empty-row class="d-none">
                                    <td colspan="5" class="text-body-secondary text-center py-3">
                                        {{ __('general.no_filter_results') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-3">{{ $advances->links() }}</div>
            @endif
        </div>
    </div>
@endsection
