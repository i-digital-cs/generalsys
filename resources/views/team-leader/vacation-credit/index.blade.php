@extends('layouts.app')

@section('title', __('vacations.credit.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('vacations.credit.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('vacations.credit.subtitle') }}</p>
        </div>
    </div>

    @if ($employees->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <x-empty-state
                    :title="__('vacations.credit.empty_title')"
                    :text="__('vacations.credit.empty_text')"
                    icon="bi-sun"
                />
            </div>
        </div>
    @else
        <div data-table-filter-root>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body py-3">
                    <x-table-toolbar
                        id="vacation-credit-team"
                        :search-placeholder="__('dashboard.hours.search_name')"
                        class="mb-0"
                    />
                </div>
            </div>

            <div class="row g-3">
                @foreach ($employees as $member)
                    @php
                        $name = app()->getLocale() === 'ar' ? $member->name_ar : $member->name_en;
                        $balance = $member->vacationBalances->first();
                        $remaining = $balance ? (float) $balance->remaining_days : 0;
                        $accrued = $balance ? (float) $balance->accrued_days : 0;
                        $used = $balance ? (float) $balance->used_days : 0;
                        $pending = $balance ? (float) $balance->pending_days : 0;
                    @endphp
                    <div class="col-12 col-md-6 col-xl-4" data-filterable-item
                         data-filter-text="{{ $name }} {{ $member->name_ar }} {{ $member->name_en }}">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-primary-subtle text-primary fw-semibold"
                                          style="width: 2.25rem; height: 2.25rem;" aria-hidden="true">
                                        {{ mb_substr($name, 0, 1) }}
                                    </span>
                                    <h2 class="h6 mb-0 text-truncate">{{ $name }}</h2>
                                    <span class="badge text-bg-secondary ms-auto" dir="ltr">
                                        {{ number_format($remaining, 1) }} {{ __('vacations.credit_remaining') }}
                                    </span>
                                </div>

                                <dl class="row small mb-3 g-2">
                                    <dt class="col-6 text-body-secondary">{{ __('vacations.credit_accrued') }}</dt>
                                    <dd class="col-6 mb-0 text-end num-ltr">{{ number_format($accrued, 1) }}</dd>
                                    <dt class="col-6 text-body-secondary">{{ __('vacations.credit_used') }}</dt>
                                    <dd class="col-6 mb-0 text-end num-ltr">{{ number_format($used, 1) }}</dd>
                                    <dt class="col-6 text-body-secondary">{{ __('vacations.credit_pending') }}</dt>
                                    <dd class="col-6 mb-0 text-end num-ltr">{{ number_format($pending, 1) }}</dd>
                                </dl>

                                <form method="POST" action="{{ route('team-leader.vacation-credit.store', $member) }}"
                                      class="mt-auto">
                                    @csrf
                                    <input type="hidden" name="year" value="{{ $year }}">
                                    <div class="mb-2">
                                        <label class="form-label small mb-1" for="amount-{{ $member->id }}">
                                            {{ __('vacations.credit.fields.amount_days') }}
                                        </label>
                                        <input type="number" step="0.5" min="0.5" max="365"
                                               id="amount-{{ $member->id }}" name="amount_days"
                                               class="form-control form-control-sm @error('amount_days') is-invalid @enderror"
                                               required dir="ltr">
                                        <div class="form-text">{{ __('vacations.credit.amount_hint') }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small mb-1" for="reason-{{ $member->id }}">
                                            {{ __('vacations.credit.fields.reason') }}
                                        </label>
                                        <input type="text" id="reason-{{ $member->id }}" name="reason"
                                               class="form-control form-control-sm" maxlength="2000">
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            {{ __('vacations.credit.submit') }}
                                        </button>
                                        <a href="{{ route('team-leader.vacation-credit.history', [$member, 'year' => $year]) }}"
                                           class="btn btn-sm btn-outline-secondary">
                                            {{ __('vacations.credit.history') }}
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
