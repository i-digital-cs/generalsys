@extends('layouts.app')

@section('title', __('permissions.tl.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('permissions.tl.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('permissions.tl.subtitle') }}</p>
        </div>
    </div>

    <ul class="nav nav-pills flex-wrap gap-2 mb-3">
        <li class="nav-item">
            <a class="nav-link @if ($filter === 'pending') active @endif"
               href="{{ route('team-leader.approvals', ['filter' => 'pending']) }}">
                {{ __('permissions.tl.filters.pending') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if ($filter === 'decided') active @endif"
               href="{{ route('team-leader.approvals', ['filter' => 'decided']) }}">
                {{ __('permissions.tl.filters.decided') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if ($filter === 'all') active @endif"
               href="{{ route('team-leader.approvals', ['filter' => 'all']) }}">
                {{ __('permissions.tl.filters.all') }}
            </a>
        </li>
    </ul>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h2 class="h6 mb-0">{{ __('permissions.tl.section_permissions') }}</h2>
        </div>
        <div class="card-body">
            @if ($permissions->isEmpty())
                <x-empty-state
                    :title="__('permissions.tl.empty_title')"
                    :text="__('permissions.tl.empty_text')"
                    icon="bi-check2-square"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('shifts.employee') }}</th>
                                <th scope="col">{{ __('permissions.fields.request_date') }}</th>
                                <th scope="col">{{ __('permissions.fields.type') }}</th>
                                <th scope="col">{{ __('permissions.fields.interval') }}</th>
                                <th scope="col">{{ __('permissions.fields.status') }}</th>
                                <th scope="col">{{ __('permissions.fields.reason') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permissions as $row)
                                @php
                                    $name = app()->getLocale() === 'ar' ? $row->user?->name_ar : $row->user?->name_en;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $name }}</td>
                                    <td dir="ltr" class="text-nowrap">{{ $row->request_date->toDateString() }}</td>
                                    <td>{{ __('permissions.types.'.$row->type) }}</td>
                                    <td dir="ltr" class="text-nowrap">{{ $row->from_time }}–{{ $row->to_time }}</td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-warning' => $row->status === 'PENDING',
                                            'text-bg-success' => $row->status === 'ACCEPTED',
                                            'text-bg-danger' => $row->status === 'REJECTED',
                                            'text-bg-dark' => $row->status === 'FORCED',
                                        ])>
                                            {{ __('permissions.statuses.'.$row->status) }}
                                        </span>
                                    </td>
                                    <td class="text-body-secondary small">{{ filled($row->reason) ? $row->reason : '—' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex flex-nowrap gap-2 justify-content-end">
                                            @if ($row->status === 'PENDING')
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.permissions.approve', $row) }}">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        {{ __('permissions.tl.accept') }}
                                                    </button>
                                                </form>
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.permissions.reject', $row) }}"
                                                      onsubmit="return confirm(@json(__('permissions.tl.reject_confirm')))">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        {{ __('permissions.tl.reject') }}
                                                    </button>
                                                </form>
                                            @elseif ($row->status === 'REJECTED' && ($canForce ?? false))
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.permissions.force', $row) }}"
                                                      onsubmit="return confirm(@json(__('permissions.force_confirm')))">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-dark">
                                                        {{ __('permissions.force') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $permissions->links() }}
                </div>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h2 class="h6 mb-0">{{ __('vacations.tl.section') }}</h2>
            <p class="small text-body-secondary mb-0 mt-1">{{ __('vacations.tl.credit_hint') }}</p>
        </div>
        <div class="card-body">
            @if ($vacations->isEmpty())
                <x-empty-state
                    :title="__('vacations.tl.empty_title')"
                    :text="__('vacations.tl.empty_text')"
                    icon="bi-calendar2-check"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('shifts.employee') }}</th>
                                <th scope="col">{{ __('vacations.fields.type') }}</th>
                                <th scope="col">{{ __('vacations.fields.range') }}</th>
                                <th scope="col">{{ __('vacations.fields.days_requested') }}</th>
                                <th scope="col">{{ __('vacations.fields.replace_rest_day') }}</th>
                                <th scope="col">{{ __('vacations.fields.status') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vacations as $row)
                                @php
                                    $name = app()->getLocale() === 'ar' ? $row->user?->name_ar : $row->user?->name_en;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $name }}</td>
                                    <td>{{ __('vacations.types.'.$row->type) }}</td>
                                    <td dir="ltr" class="text-nowrap">
                                        {{ $row->start_date->toDateString() }}
                                    </td>
                                    <td dir="ltr">{{ number_format((float) $row->days_requested, 1) }}</td>
                                    <td dir="ltr" class="text-nowrap">
                                        {{ $row->replace_rest_date?->toDateString() ?? '—' }}
                                    </td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-warning' => $row->status === 'PENDING',
                                            'text-bg-success' => $row->status === 'APPROVED',
                                            'text-bg-danger' => $row->status === 'REJECTED',
                                            'text-bg-dark' => $row->status === 'FORCED',
                                        ])>
                                            {{ __('vacations.statuses.'.$row->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex flex-nowrap gap-2 justify-content-end">
                                            @if ($row->status === 'PENDING')
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.vacations.approve', $row) }}">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        {{ __('vacations.tl.accept') }}
                                                    </button>
                                                </form>
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.vacations.reject', $row) }}"
                                                      onsubmit="return confirm(@json(__('vacations.tl.reject_confirm')))">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        {{ __('vacations.tl.reject') }}
                                                    </button>
                                                </form>
                                            @elseif ($row->status === 'REJECTED' && ($canForce ?? false))
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.vacations.force', $row) }}"
                                                      onsubmit="return confirm(@json(__('vacations.force_confirm')))">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-dark">
                                                        {{ __('vacations.force') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $vacations->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
