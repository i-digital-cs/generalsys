@extends('layouts.app')

@section('title', __('vacations.credit.history_title', [
    'name' => app()->getLocale() === 'ar' ? $employee->name_ar : $employee->name_en,
    'year' => $year,
]))

@section('content')
    @php
        $name = app()->getLocale() === 'ar' ? $employee->name_ar : $employee->name_en;
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('vacations.credit.history_title', ['name' => $name, 'year' => $year]) }}</h1>
            <p class="text-body-secondary mb-0">
                {{ __('vacations.credit_remaining') }}:
                <span class="num-ltr fw-semibold">{{ number_format((float) $balance->remaining_days, 1) }}</span>
            </p>
        </div>
        <a href="{{ route('team-leader.vacation-credit') }}" class="btn btn-outline-secondary btn-sm">
            {{ __('general.back') }}
        </a>
    </div>

    @if ($logs->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <x-empty-state
                    :title="__('vacations.credit.history_empty_title')"
                    :text="__('vacations.credit.history_empty_text')"
                    icon="bi-clock-history"
                />
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hr table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="col-num">{{ __('vacations.credit.fields.when') }}</th>
                            <th scope="col" class="col-num">{{ __('vacations.credit.fields.amount_days') }}</th>
                            <th scope="col">{{ __('vacations.credit.fields.added_by') }}</th>
                            <th scope="col">{{ __('vacations.credit.fields.reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="col-num">
                                    <span class="num-ltr">{{ $log->created_at?->translatedFormat('Y-m-d H:i') ?? '—' }}</span>
                                </td>
                                <td class="col-num num-ltr">+{{ number_format((float) $log->amount_days, 1) }}</td>
                                <td>{{ $log->addedBy?->localizedName() ?? '—' }}</td>
                                <td>{{ $log->reason ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
