@extends('layouts.app')

@section('title', __('employees.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('employees.title') }}</h1>
        <a href="{{ route('admin.employees.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('employees.add_new') }}
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.employees.index') }}" class="row g-2 mb-3">
                <div class="col-12 col-md-6">
                    <label for="q" class="visually-hidden">{{ __('general.search') }}</label>
                    <input type="search" id="q" name="q" value="{{ $q }}" class="form-control"
                           placeholder="{{ __('employees.search_placeholder') }}">
                </div>
                <div class="col-6 col-md-3">
                    <label for="status" class="visually-hidden">{{ __('employees.status') }}</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach (['ACTIVE', 'INACTIVE'] as $option)
                            <option value="{{ $option }}" @selected($status === $option)>
                                {{ __("employees.statuses.{$option}") }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 d-grid">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1" aria-hidden="true"></i>{{ __('general.search') }}
                    </button>
                </div>
            </form>

            @if ($employees->isEmpty())
                <x-empty-state :title="__('employees.empty_title')" :text="__('employees.empty_text')" icon="bi-people" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('employees.code') }}</th>
                                <th scope="col">{{ __('employees.name') }}</th>
                                <th scope="col">{{ __('employees.fields.phone') }}</th>
                                <th scope="col">{{ __('employees.fields.shift_type_default') }}</th>
                                <th scope="col">{{ __('employees.email') }}</th>
                                <th scope="col">{{ __('employees.role') }}</th>
                                <th scope="col">{{ __('employees.status') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employees as $employee)
                                @php
                                    $displayName = app()->getLocale() === 'ar' ? $employee->name_ar : $employee->name_en;
                                @endphp
                                <tr>
                                    <td>{{ $employee->profile?->employee_code ?? __('general.not_available') }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if ($employee->profile?->personal_photo_path)
                                                <img src="{{ route('employees.photo', $employee) }}"
                                                     alt="{{ $displayName }}"
                                                     class="rounded-circle object-fit-cover flex-shrink-0"
                                                     style="width: 2rem; height: 2rem;">
                                            @else
                                                <span class="d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-primary-subtle text-primary fw-semibold small"
                                                      style="width: 2rem; height: 2rem;" aria-hidden="true">
                                                    {{ mb_substr($displayName, 0, 1) }}
                                                </span>
                                            @endif
                                            <span>{{ $displayName }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($employee->profile?->phone)
                                            <span class="num-ltr">{{ $employee->profile->phone }}</span>
                                        @else
                                            {{ __('general.not_available') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($employee->shift_type_default)
                                            {{ __("employees.shifts.{$employee->shift_type_default}") }}
                                        @else
                                            {{ __('general.not_available') }}
                                        @endif
                                    </td>
                                    <td><span class="num-ltr">{{ $employee->email }}</span></td>
                                    <td>{{ __("employees.roles.{$employee->role}") }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $employee->isActive() ? 'success' : 'secondary' }}">
                                            {{ __("employees.statuses.{$employee->status}") }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.employees.edit', $employee) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('general.edit') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $employees->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
