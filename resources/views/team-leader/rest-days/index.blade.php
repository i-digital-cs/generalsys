@extends('layouts.app')

@section('title', __('employees.rest_days.tl_title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('employees.rest_days.tl_title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('employees.rest_days.tl_subtitle') }}</p>
        </div>
    </div>

    @error('date')
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>{{ $message }}</span>
        </div>
    @enderror

    @if ($employees->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <x-empty-state
                    :title="__('dashboard.hours.no_team_title')"
                    :text="__('dashboard.hours.no_team_text')"
                    icon="bi-calendar-week"
                />
            </div>
        </div>
    @else
        <div data-table-filter-root>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body py-3">
                    <x-table-toolbar
                        id="rest-days-team"
                        :search-placeholder="__('dashboard.hours.search_name')"
                        class="mb-0"
                    />
                </div>
            </div>

            <div class="row g-3">
                @foreach ($employees as $member)
                    @php
                        $name = app()->getLocale() === 'ar' ? $member->name_ar : $member->name_en;
                        [$past, $upcoming] = $member->restDays->partition(
                            fn ($restDay) => $restDay->date->startOfDay()->lt(today())
                        );
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
                                    <span class="badge text-bg-secondary ms-auto">
                                        {{ $upcoming->count() }} {{ __('employees.rest_days.upcoming') }}
                                    </span>
                                </div>

                                <form method="POST" action="{{ route('team-leader.rest-days.store', $member) }}"
                                      class="row g-2 align-items-end mb-3">
                                    @csrf
                                    <div class="col">
                                        <label for="rest_date_{{ $member->id }}" class="form-label">
                                            {{ __('employees.rest_days.fields.date') }}
                                        </label>
                                        <input type="date" name="date" id="rest_date_{{ $member->id }}"
                                               class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                            {{ __('employees.rest_days.add') }}
                                        </button>
                                    </div>
                                </form>

                                @if ($member->restDays->isEmpty())
                                    <p class="small text-body-secondary mb-0">{{ __('employees.rest_days.empty') }}</p>
                                @else
                                    @if ($upcoming->isNotEmpty())
                                        <ul class="list-group list-group-flush small border-top">
                                            @foreach ($upcoming as $restDay)
                                                <li class="list-group-item px-0 d-flex align-items-center justify-content-between gap-2">
                                                    <span>
                                                        <span class="fw-semibold">{{ $restDay->date->translatedFormat('l') }}</span>
                                                        <span class="text-body-secondary">{{ $restDay->date->translatedFormat('j M Y') }}</span>
                                                        <span class="num-ltr text-body-secondary">{{ $restDay->date->toDateString() }}</span>
                                                    </span>
                                                    @if ($restDay->pendingReservation !== null)
                                                        <span class="badge text-bg-warning flex-shrink-0"
                                                              title="{{ __('employees.rest_days.reserved_title') }}">
                                                            <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                                            {{ __('employees.rest_days.reserved') }}
                                                        </span>
                                                    @else
                                                        <form method="POST" class="flex-shrink-0"
                                                              action="{{ route('team-leader.rest-days.destroy', [$member, $restDay]) }}"
                                                              onsubmit="return confirm(@json(__('employees.rest_days.remove').'?'))">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                {{ __('employees.rest_days.remove') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if ($past->isNotEmpty())
                                        <details class="small mt-auto pt-2 @if ($upcoming->isNotEmpty()) border-top @endif">
                                            <summary class="text-body-secondary">
                                                {{ __('employees.rest_days.past') }} ({{ $past->count() }})
                                            </summary>
                                            <ul class="list-group list-group-flush small">
                                                @foreach ($past as $restDay)
                                                    <li class="list-group-item px-0 d-flex align-items-center justify-content-between gap-2 text-body-secondary">
                                                        <span>
                                                            <span>{{ $restDay->date->translatedFormat('l') }}</span>
                                                            <span>{{ $restDay->date->translatedFormat('j M Y') }}</span>
                                                            <span class="num-ltr">{{ $restDay->date->toDateString() }}</span>
                                                        </span>
                                                        @if ($restDay->pendingReservation !== null)
                                                            <span class="badge text-bg-warning flex-shrink-0"
                                                                  title="{{ __('employees.rest_days.reserved_title') }}">
                                                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                                                {{ __('employees.rest_days.reserved') }}
                                                            </span>
                                                        @else
                                                            <form method="POST" class="flex-shrink-0"
                                                                  action="{{ route('team-leader.rest-days.destroy', [$member, $restDay]) }}"
                                                                  onsubmit="return confirm(@json(__('employees.rest_days.remove').'?'))">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    {{ __('employees.rest_days.remove') }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div data-table-empty-msg class="d-none card border-0 shadow-sm mt-3">
                <div class="card-body text-body-secondary text-center py-4">
                    {{ __('general.no_filter_results') }}
                </div>
            </div>
        </div>
    @endif
@endsection
