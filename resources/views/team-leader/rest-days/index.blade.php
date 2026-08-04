@extends('layouts.app')

@section('title', __('employees.rest_days.tl_title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('employees.rest_days.tl_title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('employees.rest_days.tl_subtitle') }}</p>
        </div>
    </div>

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
        <div class="row g-3">
            @foreach ($employees as $member)
                @php
                    $name = app()->getLocale() === 'ar' ? $member->name_ar : $member->name_en;
                @endphp
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h6 mb-3">{{ $name }}</h2>
                            <form method="POST" action="{{ route('team-leader.rest-days.store', $member) }}"
                                  class="row g-2 align-items-end mb-3">
                                @csrf
                                <div class="col">
                                    <label for="rest_date_{{ $member->id }}" class="form-label">
                                        {{ __('employees.rest_days.fields.date') }}
                                    </label>
                                    <input type="date" name="date" id="rest_date_{{ $member->id }}"
                                           class="form-control form-control-sm" dir="ltr" required>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        {{ __('employees.rest_days.add') }}
                                    </button>
                                </div>
                            </form>
                            @if ($member->restDays->isEmpty())
                                <p class="small text-body-secondary mb-0">{{ __('employees.rest_days.empty') }}</p>
                            @else
                                <ul class="list-group list-group-flush">
                                    @foreach ($member->restDays as $restDay)
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                            <span dir="ltr">{{ $restDay->date->toDateString() }}</span>
                                            <form method="POST"
                                                  action="{{ route('team-leader.rest-days.destroy', [$member, $restDay]) }}"
                                                  onsubmit="return confirm(@json(__('employees.rest_days.remove').'?'))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    {{ __('employees.rest_days.remove') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
