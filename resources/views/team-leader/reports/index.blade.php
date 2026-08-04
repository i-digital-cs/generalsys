@extends('layouts.app')

@section('title', __('report.tl.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('report.tl.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('report.tl.subtitle') }}</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('team-leader.reports') }}" id="reports-filter"
                  class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="employee_id" class="form-label">{{ __('report.tl.employee_filter') }}</label>
                    <select id="employee_id" name="employee_id" class="form-select"
                            onchange="this.form.submit()">
                        <option value="">{{ __('report.tl.all_employees') }}</option>
                        @foreach ($employees as $member)
                            @php
                                $label = app()->getLocale() === 'ar' ? $member->name_ar : $member->name_en;
                            @endphp
                            <option value="{{ $member->id }}" @selected((int) $employeeId === (int) $member->id)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label for="filter" class="form-label">{{ __('report.tl.filter') }}</label>
                    <select id="filter" name="filter" class="form-select" onchange="this.form.submit()">
                        <option value="all" @selected($filter === 'all')>{{ __('report.tl.filters.all') }}</option>
                        <option value="pending" @selected($filter === 'pending')>{{ __('report.tl.filters.pending') }}</option>
                        <option value="replied" @selected($filter === 'replied')>{{ __('report.tl.filters.replied') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex flex-wrap gap-2">
                    <a href="{{ route('team-leader.reports') }}" class="btn btn-outline-secondary">
                        {{ __('report.tl.clear_filters') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex flex-wrap justify-content-between gap-2">
            <span class="fw-semibold">{{ __('report.tl.list_title') }}</span>
            <span class="small text-body-secondary">
                {{ __('report.tl.count', ['count' => $reports->total()]) }}
            </span>
        </div>
        <div class="card-body">
            @if ($reports->isEmpty())
                <x-empty-state
                    :title="__('report.tl.empty_title')"
                    :text="__('report.tl.empty_text')"
                    icon="bi-journal-text"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('report.tl.employee') }}</th>
                                <th scope="col">{{ __('report.date') }}</th>
                                <th scope="col">{{ __('report.tl.preview') }}</th>
                                <th scope="col">{{ __('report.tl.status') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reports as $row)
                                @php
                                    $name = app()->getLocale() === 'ar' ? $row->user->name_ar : $row->user->name_en;
                                    $preview = \Illuminate\Support\Str::limit(strip_tags((string) $row->daily_report), 80);
                                    $statusKey = filled($row->team_leader_response) ? 'replied' : 'waiting';
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $name }}</td>
                                    <td dir="ltr" class="text-nowrap">{{ $row->date->toDateString() }}</td>
                                    <td class="text-body-secondary small">{{ $preview }}</td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-success' => $statusKey === 'replied',
                                            'text-bg-warning' => $statusKey === 'waiting',
                                        ])>
                                            {{ __('report.status.'.$statusKey) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('team-leader.reports.show', $row) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            {{ filled($row->team_leader_response) ? __('report.tl.view') : __('report.tl.reply') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
