@extends('layouts.app')

@section('title', __('profile.title'))

@section('content')
    @php
        $profile = $employee->profile;
        $contract = $employee->activeContract;
        $localeIsAr = app()->getLocale() === 'ar';
    @endphp

    <h1 class="h4 mb-3">{{ __('profile.title') }}</h1>

    {{-- Personal information (no salary/rates — §1.8 privacy rule) --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent fw-semibold">{{ __('profile.personal_info') }}</div>
        <div class="card-body">
            @if ($profile?->personal_photo_path)
                <div class="mb-3">
                    <img src="{{ route('employees.photo', $employee) }}" alt="{{ __('employees.fields.photo') }}"
                         class="rounded-circle object-fit-cover" style="width: 6rem; height: 6rem;">
                </div>
            @endif
            <dl class="row mb-0">
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.name_ar') }}</dt>
                <dd class="col-sm-8 col-md-9">{{ $employee->name_ar }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.name_en') }}</dt>
                <dd class="col-sm-8 col-md-9">{{ $employee->name_en }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.email') }}</dt>
                <dd class="col-sm-8 col-md-9" dir="ltr">{{ $employee->email }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.employee_code') }}</dt>
                <dd class="col-sm-8 col-md-9">{{ $profile?->employee_code ?? __('general.not_available') }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.national_id') }}</dt>
                <dd class="col-sm-8 col-md-9">{{ $profile?->national_id ?? __('general.not_available') }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.birth_date') }}</dt>
                <dd class="col-sm-8 col-md-9">
                    {{ $profile?->birth_date?->translatedFormat('Y-m-d') ?? __('general.not_available') }}
                    @if ($profile?->age !== null)
                        ({{ __('profile.age') }}: {{ $profile->age }} {{ __('profile.years') }})
                    @endif
                </dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.phone') }}</dt>
                <dd class="col-sm-8 col-md-9" dir="ltr">{{ $profile?->phone ?? __('general.not_available') }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.address') }}</dt>
                <dd class="col-sm-8 col-md-9">{{ $profile?->address ?? __('general.not_available') }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.gender') }}</dt>
                <dd class="col-sm-8 col-md-9">{{ $profile?->gender ? __("employees.genders.{$profile->gender}") : __('general.not_available') }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.marital_status') }}</dt>
                <dd class="col-sm-8 col-md-9">{{ $profile?->marital_status ? __("employees.marital_statuses.{$profile->marital_status}") : __('general.not_available') }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.religion') }}</dt>
                <dd class="col-sm-8 col-md-9">{{ $profile?->religion ?? __('general.not_available') }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.work_start_time') }}</dt>
                <dd class="col-sm-8 col-md-9" dir="ltr">{{ $employee->work_start_time ?? __('general.not_available') }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.fields.work_end_time') }}</dt>
                <dd class="col-sm-8 col-md-9" dir="ltr">{{ $employee->work_end_time ?? __('general.not_available') }}</dd>
                <dt class="col-sm-4 col-md-3">{{ __('employees.team_leader') }}</dt>
                <dd class="col-sm-8 col-md-9">
                    {{ $employee->teamLeader ? ($localeIsAr ? $employee->teamLeader->name_ar : $employee->teamLeader->name_en) : __('general.not_available') }}
                </dd>
            </dl>
        </div>
    </div>

    {{-- Contract --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent fw-semibold">{{ __('profile.contract_info') }}</div>
        <div class="card-body">
            @if ($contract)
                <dl class="row mb-0">
                    <dt class="col-sm-4 col-md-3">{{ __('employees.fields.hire_date') }}</dt>
                    <dd class="col-sm-8 col-md-9">{{ $contract->hire_date->translatedFormat('Y-m-d') }}</dd>
                    <dt class="col-sm-4 col-md-3">{{ __('employees.fields.contract_start') }}</dt>
                    <dd class="col-sm-8 col-md-9">{{ $contract->contract_start->translatedFormat('Y-m-d') }}</dd>
                    <dt class="col-sm-4 col-md-3">{{ __('employees.fields.contract_end') }}</dt>
                    <dd class="col-sm-8 col-md-9">{{ $contract->contract_end->translatedFormat('Y-m-d') }}</dd>
                    <dt class="col-sm-4 col-md-3">{{ __('employees.fields.contract_type') }}</dt>
                    <dd class="col-sm-8 col-md-9">{{ $contract->contract_type }}</dd>
                </dl>
            @else
                <p class="text-body-secondary mb-0">{{ __('profile.no_contract') }}</p>
            @endif
        </div>
    </div>

    {{-- Qualifications --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent fw-semibold">{{ __('profile.qualifications') }}</div>
        <div class="card-body">
            @if ($employee->qualifications->isEmpty())
                <p class="text-body-secondary mb-0">{{ __('profile.no_qualifications') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('employees.fields.degree') }}</th>
                                <th scope="col">{{ __('employees.fields.specialization') }}</th>
                                <th scope="col">{{ __('employees.fields.graduation_year') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employee->qualifications as $qualification)
                                <tr>
                                    <td>{{ $qualification->degree }}</td>
                                    <td>{{ $qualification->specialization ?? __('general.not_available') }}</td>
                                    <td>{{ $qualification->graduation_year ?? __('general.not_available') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Emergency contacts --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent fw-semibold">{{ __('profile.emergency_contacts') }}</div>
        <div class="card-body">
            @if ($employee->emergencyContacts->isEmpty())
                <p class="text-body-secondary mb-0">{{ __('profile.no_contacts') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('employees.fields.contact_name') }}</th>
                                <th scope="col">{{ __('employees.fields.contact_phone') }}</th>
                                <th scope="col">{{ __('employees.fields.relationship') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employee->emergencyContacts as $contact)
                                <tr>
                                    <td>{{ $contact->name }}</td>
                                    <td dir="ltr">{{ $contact->phone }}</td>
                                    <td>{{ $contact->relationship ?? __('general.not_available') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Weekly rest days (read-only) --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent fw-semibold">{{ __('profile.rest_days') }}</div>
        <div class="card-body">
            @php
                $restDays = $employee->restDays()->orderBy('date')->limit(20)->get();
            @endphp
            @if ($restDays->isEmpty())
                <p class="text-body-secondary mb-0">{{ __('employees.rest_days.empty') }}</p>
            @else
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($restDays as $restDay)
                        <span class="badge text-bg-secondary py-2 px-3" dir="ltr">{{ $restDay->date->toDateString() }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Documents (authorized download links) --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent fw-semibold">{{ __('profile.documents') }}</div>
        <div class="card-body">
            @if ($employee->documents->isEmpty())
                <p class="text-body-secondary mb-0">{{ __('documents.no_documents') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('documents.type') }}</th>
                                <th scope="col">{{ __('documents.uploaded_at') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employee->documents as $document)
                                <tr>
                                    <td>{{ __("documents.types.{$document->type}") }}</td>
                                    <td>{{ $document->uploaded_at?->translatedFormat('Y-m-d H:i') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('documents.download', $document) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('documents.download') }}
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
