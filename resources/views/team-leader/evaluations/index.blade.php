@extends('layouts.app')

@section('title', __('evaluation.tl.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('evaluation.tl.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('evaluation.tl.subtitle') }}</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('team-leader.evaluations') }}" class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label for="month" class="form-label">{{ __('evaluation.tl.month') }}</label>
                    <select id="month" name="month" class="form-select" onchange="this.form.submit()">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((int) $month === $m)>
                                {{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="year" class="form-label">{{ __('evaluation.tl.year') }}</label>
                    <select id="year" name="year" class="form-select" onchange="this.form.submit()">
                        @for ($y = now()->year; $y >= now()->year - 2; $y--)
                            <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">{{ __('evaluation.tl.team_list') }}</div>
        <div class="card-body">
            @if ($rows->isEmpty())
                <x-empty-state
                    :title="__('evaluation.tl.empty_title')"
                    :text="__('evaluation.tl.empty_text')"
                    icon="bi-star"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('evaluation.tl.employee') }}</th>
                                <th scope="col">{{ __('evaluation.fields.total') }}</th>
                                <th scope="col">{{ __('evaluation.tl.status') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    $member = $row['employee'];
                                    $name = app()->getLocale() === 'ar' ? $member->name_ar : $member->name_en;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $name }}</td>
                                    <td dir="ltr">{{ number_format($row['scores']['total_score'], 2) }}%</td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-success' => $row['saved'],
                                            'text-bg-secondary' => ! $row['saved'],
                                        ])>
                                            {{ $row['saved'] ? __('evaluation.tl.saved') : __('evaluation.tl.draft') }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('team-leader.evaluations.show', ['employee' => $member, 'month' => $month, 'year' => $year]) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            {{ __('evaluation.tl.open') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
