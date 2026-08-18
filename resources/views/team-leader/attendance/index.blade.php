@extends('layouts.app')

@section('title', __('attendance.history.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('attendance.history.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('attendance.history.subtitle') }}</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('team-leader.attendance') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-4 col-xl-3">
                    <label for="employee_id" class="form-label">{{ __('attendance.history.employee') }}</label>
                    <select id="employee_id" name="employee_id" class="form-select">
                        <option value="">{{ __('attendance.history.all_employees') }}</option>
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
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="from" class="form-label">{{ __('attendance.history.from') }}</label>
                    <input type="date" id="from" name="from" value="{{ $from }}"
                           class="form-control @error('from') is-invalid @enderror" required>
                    @error('from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="to" class="form-label">{{ __('attendance.history.to') }}</label>
                    <input type="date" id="to" name="to" value="{{ $to }}"
                           class="form-control @error('to') is-invalid @enderror" required>
                    @error('to') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="kind" class="form-label">{{ __('attendance.history.kind') }}</label>
                    <select id="kind" name="kind" class="form-select">
                        @foreach (['all', 'present', 'absent', 'rest', 'vacation'] as $option)
                            <option value="{{ $option }}" @selected($kind === $option)>
                                {{ __('attendance.history.kinds.'.$option) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="evidence" class="form-label">{{ __('attendance.history.evidence') }}</label>
                    <select id="evidence" name="evidence" class="form-select">
                        @foreach (['all', 'photos', 'location', 'outside'] as $option)
                            <option value="{{ $option }}" @selected($evidence === $option)>
                                {{ __('attendance.history.evidence_options.'.$option) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-xl-1 d-grid">
                    <button type="submit" class="btn btn-primary">{{ __('attendance.history.apply') }}</button>
                </div>
                <div class="col-12">
                    <a href="{{ route('team-leader.attendance') }}" class="btn btn-sm btn-outline-secondary">
                        {{ __('attendance.history.clear') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex flex-wrap justify-content-between gap-2">
            <span class="fw-semibold">{{ __('attendance.history.list_title') }}</span>
            <span class="small text-body-secondary">
                {{ __('attendance.history.count', ['count' => $days->total()]) }}
            </span>
        </div>
        <div class="card-body">
            @if ($days->isEmpty())
                <x-empty-state
                    :title="__('attendance.history.empty_title')"
                    :text="__('attendance.history.empty_text')"
                    icon="bi-calendar-check"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('attendance.history.employee') }}</th>
                                <th scope="col" class="col-num">{{ __('attendance.history.date') }}</th>
                                <th scope="col">{{ __('attendance.history.kind') }}</th>
                                <th scope="col" class="col-num">{{ __('dashboard.hours.should_spend') }}</th>
                                <th scope="col" class="col-num">{{ __('dashboard.hours.spent') }}</th>
                                <th scope="col" class="col-num">{{ __('attendance.history.absent_hours') }}</th>
                                <th scope="col">{{ __('clock.photos.title') }} / {{ __('clock.location.title') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($days as $row)
                                @php
                                    $name = app()->getLocale() === 'ar' ? $row['user']->name_ar : $row['user']->name_en;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $name }}</td>
                                    <td class="col-num text-nowrap">
                                        <span class="num-ltr">{{ \Carbon\Carbon::parse($row['date'])->translatedFormat('d M Y') }}</span>
                                    </td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-success' => $row['kind'] === 'present',
                                            'text-bg-danger' => $row['kind'] === 'absent',
                                            'text-bg-warning text-dark' => $row['kind'] === 'rest',
                                            'text-bg-info' => $row['kind'] === 'vacation',
                                        ])>
                                            {{ __('attendance.history.kinds.'.$row['kind']) }}
                                        </span>
                                    </td>
                                    <td class="col-num"><span class="num-ltr">{{ number_format($row['planned_hours'], 2) }}h</span></td>
                                    <td class="col-num"><span class="num-ltr">{{ number_format($row['actual_hours'], 2) }}h</span></td>
                                    <td class="col-num">
                                        <span @class(['num-ltr', 'text-danger fw-semibold' => $row['absent_hours'] > 0.01])>
                                            {{ number_format($row['absent_hours'], 2) }}h
                                        </span>
                                    </td>
                                    <td>
                                        @if ($row['has_photos'])
                                            <x-attendance-photos :photos="$row['photos']" compact />
                                        @endif
                                        @if ($row['attendance'])
                                            <x-attendance-location :attendance="$row['attendance']" compact class="mt-1" />
                                        @endif
                                        @if (! $row['has_photos'] && ! $row['has_location'])
                                            <span class="small text-body-secondary">{{ __('clock.photos.none') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                    <p class="small text-body-secondary mb-0">
                        {{ __('attendance.history.showing', [
                            'from' => $days->firstItem() ?? 0,
                            'to' => $days->lastItem() ?? 0,
                            'total' => $days->total(),
                        ]) }}
                    </p>
                    <nav class="overflow-auto" aria-label="{{ __('attendance.history.title') }}">
                        {{ $days->onEachSide(1)->links() }}
                    </nav>
                </div>
            @endif
        </div>
    </div>
@endsection
