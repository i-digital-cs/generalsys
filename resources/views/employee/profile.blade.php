@extends('layouts.app')

@section('title', __('profile.title'))

@section('content')
    @php
        $profile = $employee->profile;
        $contract = $employee->activeContract;
        $localeIsAr = app()->getLocale() === 'ar';
        $displayName = $localeIsAr ? $employee->name_ar : $employee->name_en;
        $secondaryName = $localeIsAr ? $employee->name_en : $employee->name_ar;
        $birthValue = $profile?->birth_date?->translatedFormat('Y-m-d');
        if ($birthValue !== null && $profile?->age !== null) {
            $birthValue .= ' ('.__('profile.age').': '.$profile->age.' '.__('profile.years').')';
        }
        $genderValue = $profile?->gender ? __("employees.genders.{$profile->gender}") : null;
        $maritalValue = $profile?->marital_status ? __("employees.marital_statuses.{$profile->marital_status}") : null;
        $workHours = $employee->work_start_time && $employee->work_end_time
            ? $employee->work_start_time.' – '.$employee->work_end_time
            : null;
        $documentIcons = [
            'ID_CARD' => 'bi-person-vcard',
            'QUALIFICATION' => 'bi-mortarboard',
            'BIRTH_CERTIFICATE' => 'bi-file-earmark-person',
            'CRIMINAL_RECORD' => 'bi-shield-check',
            'CONTRACT_PDF' => 'bi-file-earmark-pdf',
            'OTHER' => 'bi-file-earmark',
        ];
    @endphp

    <div class="row g-3">
        {{-- Identity sidebar (sticky on desktop) --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm sticky-lg-top" style="top: 1rem;">
                <div class="card-body text-center pb-3">
                    @if ($profile?->personal_photo_path)
                        <img src="{{ route('employees.photo', $employee) }}" alt="{{ $displayName }}"
                             class="rounded-circle object-fit-cover border border-2 border-light shadow-sm mb-3"
                             style="width: 6rem; height: 6rem;">
                    @else
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary fw-bold fs-2 mb-3"
                              style="width: 6rem; height: 6rem;" aria-hidden="true">
                            {{ mb_substr($displayName, 0, 1) }}
                        </span>
                    @endif
                    <h1 class="h5 mb-1">{{ $displayName }}</h1>
                    <p class="text-body-secondary small mb-3">{{ $secondaryName }}</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        @if ($profile?->employee_code)
                            <span class="badge text-bg-primary">
                                <i class="bi bi-upc-scan me-1" aria-hidden="true"></i><span dir="ltr">{{ $profile->employee_code }}</span>
                            </span>
                        @endif
                        <span class="badge text-bg-secondary">
                            <i class="bi bi-person-badge me-1" aria-hidden="true"></i>{{ __("employees.roles.{$employee->role}") }}
                        </span>
                        @if ($employee->shift_type_default)
                            <span class="badge text-bg-info">
                                <i class="bi bi-clock-history me-1" aria-hidden="true"></i>{{ __("employees.shifts.{$employee->shift_type_default}") }}
                            </span>
                        @endif
                        @if ($workHours)
                            <span class="badge text-bg-light text-body-secondary border">
                                <i class="bi bi-clock me-1" aria-hidden="true"></i><span dir="ltr">{{ $workHours }}</span>
                            </span>
                        @endif
                    </div>
                </div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <i class="bi bi-envelope text-primary" aria-hidden="true"></i>
                        <span class="text-truncate" dir="ltr">{{ $employee->email }}</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <i class="bi bi-telephone text-primary" aria-hidden="true"></i>
                        <span dir="ltr">{{ $profile?->phone ?? __('general.not_available') }}</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <i class="bi bi-people text-primary" aria-hidden="true"></i>
                        <span class="text-body-secondary">{{ __('employees.team_leader') }}:</span>
                        <span class="text-truncate">
                            {{ $employee->teamLeader ? ($localeIsAr ? $employee->teamLeader->name_ar : $employee->teamLeader->name_en) : __('general.not_available') }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Details column --}}
        <div class="col-12 col-lg-8">
            {{-- Personal information (no salary/rates — §1.8 privacy rule) --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-person-vcard me-1" aria-hidden="true"></i>{{ __('profile.personal_info') }}
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-profile-item icon="bi-card-text" :label="__('employees.fields.national_id')"
                                        :value="$profile?->national_id" :ltr="true" />
                        <x-profile-item icon="bi-calendar-event" :label="__('employees.fields.birth_date')"
                                        :value="$birthValue" :ltr="true" />
                        <x-profile-item icon="bi-geo-alt" :label="__('employees.fields.address')"
                                        :value="$profile?->address" />
                        <x-profile-item icon="bi-gender-ambiguous" :label="__('employees.fields.gender')"
                                        :value="$genderValue" />
                        <x-profile-item icon="bi-heart" :label="__('employees.fields.marital_status')"
                                        :value="$maritalValue" />
                        <x-profile-item icon="bi-moon-stars" :label="__('employees.fields.religion')"
                                        :value="$profile?->religion" />
                    </div>
                </div>
            </div>

            {{-- Work information --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-briefcase me-1" aria-hidden="true"></i>{{ __('profile.work_info') }}
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-profile-item icon="bi-box-arrow-in-right" :label="__('employees.fields.work_start_time')"
                                        :value="$employee->work_start_time" :ltr="true" />
                        <x-profile-item icon="bi-box-arrow-left" :label="__('employees.fields.work_end_time')"
                                        :value="$employee->work_end_time" :ltr="true" />
                    </div>
                </div>
            </div>

            {{-- Contract --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>{{ __('profile.contract_info') }}
                </div>
                <div class="card-body">
                    @if ($contract)
                        <div class="row g-3">
                            <x-profile-item icon="bi-calendar-plus" :label="__('employees.fields.hire_date')"
                                            :value="$contract->hire_date->translatedFormat('Y-m-d')" :ltr="true" />
                            <x-profile-item icon="bi-calendar-check" :label="__('employees.fields.contract_start')"
                                            :value="$contract->contract_start->translatedFormat('Y-m-d')" :ltr="true" />
                            <x-profile-item icon="bi-calendar-x" :label="__('employees.fields.contract_end')"
                                            :value="$contract->contract_end->translatedFormat('Y-m-d')" :ltr="true" />
                            <x-profile-item icon="bi-file-earmark-ruled" :label="__('employees.fields.contract_type')"
                                            :value="$contract->contract_type" />
                        </div>
                    @else
                        <x-empty-state :title="__('profile.no_contract')" icon="bi-file-earmark-x" />
                    @endif
                </div>
            </div>

            {{-- Qualifications --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-mortarboard me-1" aria-hidden="true"></i>{{ __('profile.qualifications') }}
                </div>
                <div class="card-body">
                    @if ($employee->qualifications->isEmpty())
                        <x-empty-state :title="__('profile.no_qualifications')" icon="bi-mortarboard" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-hr table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('employees.fields.degree') }}</th>
                                        <th scope="col">{{ __('employees.fields.specialization') }}</th>
                                        <th scope="col" class="col-num">{{ __('employees.fields.graduation_year') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($employee->qualifications as $qualification)
                                        <tr>
                                            <td>{{ $qualification->degree }}</td>
                                            <td>{{ $qualification->specialization ?? __('general.not_available') }}</td>
                                            <td class="col-num">
                                                <span class="num-ltr">{{ $qualification->graduation_year ?? __('general.not_available') }}</span>
                                            </td>
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
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-telephone-plus me-1" aria-hidden="true"></i>{{ __('profile.emergency_contacts') }}
                </div>
                <div class="card-body">
                    @if ($employee->emergencyContacts->isEmpty())
                        <x-empty-state :title="__('profile.no_contacts')" icon="bi-telephone-plus" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-hr table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('employees.fields.contact_name') }}</th>
                                        <th scope="col" class="col-num">{{ __('employees.fields.contact_phone') }}</th>
                                        <th scope="col">{{ __('employees.fields.relationship') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($employee->emergencyContacts as $contact)
                                        <tr>
                                            <td>{{ $contact->name }}</td>
                                            <td class="col-num"><span class="num-ltr">{{ $contact->phone }}</span></td>
                                            <td>{{ $contact->relationship ?? __('general.not_available') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Rest days (read-only, grouped by month) --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-calendar2-week me-1" aria-hidden="true"></i>{{ __('profile.rest_days') }}
                </div>
                <div class="card-body">
                    @php
                        $restByMonth = $employee->restDays()->orderBy('date')->limit(20)->get()
                            ->groupBy(fn ($restDay) => $restDay->date->format('Y-m'));
                    @endphp
                    @if ($restByMonth->isEmpty())
                        <x-empty-state :title="__('employees.rest_days.empty')" icon="bi-calendar2-week" />
                    @else
                        @foreach ($restByMonth as $month => $days)
                            <div class="small fw-semibold text-body-secondary mb-1">
                                {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y') }}
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach ($days as $restDay)
                                    <span class="badge text-bg-secondary py-2 px-3">
                                        {{ $restDay->date->translatedFormat('l') }}
                                        <span class="num-ltr">{{ $restDay->date->toDateString() }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- Documents (authorized download links) --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-folder2-open me-1" aria-hidden="true"></i>{{ __('profile.documents') }}
                </div>
                <div class="card-body">
                    @if ($employee->documents->isEmpty())
                        <x-empty-state :title="__('documents.no_documents')" icon="bi-folder2-open" />
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($employee->documents as $document)
                                <li class="list-group-item px-0 d-flex align-items-center gap-3">
                                    <span class="d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-primary bg-opacity-10 text-primary"
                                          style="width: 2.5rem; height: 2.5rem;" aria-hidden="true">
                                        <i class="bi {{ $documentIcons[$document->type] ?? 'bi-file-earmark' }}"></i>
                                    </span>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-semibold text-truncate">{{ __("documents.types.{$document->type}") }}</div>
                                        <div class="small text-body-secondary">
                                            <span class="num-ltr">{{ $document->uploaded_at?->translatedFormat('Y-m-d H:i') }}</span>
                                        </div>
                                    </div>
                                    <a href="{{ route('documents.download', $document) }}"
                                       class="btn btn-sm btn-outline-primary flex-shrink-0">
                                        <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('documents.download') }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
