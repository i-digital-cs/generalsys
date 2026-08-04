@php
    /** @var \App\Models\User|null $employee */
    $editing = $employee?->exists ?? false;

    // old() wins over the model so failed validation keeps user input.
    $val = fn (string $key, $default = '') => old($key, $editing ? data_get($employee, $key) : $default);
    $dateVal = fn (string $key) => old($key, $editing ? data_get($employee, $key)?->format('Y-m-d') : '');

    $qualificationRows = old('qualifications', $editing
        ? $employee->qualifications->map->only(['degree', 'specialization', 'graduation_year'])->all()
        : []);
    $qualificationBlank = ['degree' => '', 'specialization' => '', 'graduation_year' => ''];

    $contactRows = old('contacts', $editing
        ? $employee->emergencyContacts->map->only(['name', 'phone', 'relationship'])->all()
        : []);
    $contactBlank = ['name' => '', 'phone' => '', 'relationship' => ''];
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Account --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('employees.sections.account') }}</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="name_ar" class="form-label">{{ __('employees.fields.name_ar') }}</label>
                <input type="text" id="name_ar" name="name_ar" value="{{ $val('name_ar') }}"
                       class="form-control @error('name_ar') is-invalid @enderror" required maxlength="150">
                @error('name_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="name_en" class="form-label">{{ __('employees.fields.name_en') }}</label>
                <input type="text" id="name_en" name="name_en" value="{{ $val('name_en') }}"
                       class="form-control @error('name_en') is-invalid @enderror" required maxlength="150">
                @error('name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label">{{ __('employees.fields.email') }}</label>
                <input type="email" id="email" name="email" value="{{ $val('email') }}" dir="ltr"
                       class="form-control @error('email') is-invalid @enderror" required maxlength="190">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="password" class="form-label">{{ __('employees.fields.password') }}</label>
                <input type="password" id="password" name="password" autocomplete="new-password"
                       class="form-control @error('password') is-invalid @enderror" @required(! $editing)>
                @if ($editing)
                    <div class="form-text">{{ __('employees.fields.password_hint') }}</div>
                @endif
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="role" class="form-label">{{ __('employees.fields.role') }}</label>
                <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                    @foreach (['EMPLOYEE', 'TEAM_LEADER'] as $option)
                        <option value="{{ $option }}" @selected($val('role', 'EMPLOYEE') === $option)>
                            {{ __("employees.roles.{$option}") }}
                        </option>
                    @endforeach
                </select>
                @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="team_leader_id" class="form-label">{{ __('employees.team_leader') }}</label>
                <select id="team_leader_id" name="team_leader_id" class="form-select @error('team_leader_id') is-invalid @enderror">
                    <option value="">{{ __('employees.no_team_leader') }}</option>
                    @foreach ($teamLeaders as $leader)
                        <option value="{{ $leader->id }}" @selected((string) $val('team_leader_id') === (string) $leader->id)>
                            {{ app()->getLocale() === 'ar' ? $leader->name_ar : $leader->name_en }}
                        </option>
                    @endforeach
                </select>
                @error('team_leader_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="status" class="form-label">{{ __('employees.fields.status') }}</label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    @foreach (['ACTIVE', 'INACTIVE'] as $option)
                        <option value="{{ $option }}" @selected($val('status', 'ACTIVE') === $option)>
                            {{ __("employees.statuses.{$option}") }}
                        </option>
                    @endforeach
                </select>
                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="language_preference" class="form-label">{{ __('employees.fields.language_preference') }}</label>
                <select id="language_preference" name="language_preference" class="form-select @error('language_preference') is-invalid @enderror" required>
                    @foreach (['ar', 'en'] as $option)
                        <option value="{{ $option }}" @selected($val('language_preference', 'ar') === $option)>
                            {{ __("employees.languages.{$option}") }}
                        </option>
                    @endforeach
                </select>
                @error('language_preference') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="shift_type_default" class="form-label">{{ __('employees.fields.shift_type_default') }}</label>
                <select id="shift_type_default" name="shift_type_default" class="form-select @error('shift_type_default') is-invalid @enderror" required>
                    @foreach (['FULL_TIME', 'ONLINE_HALF', 'ONLINE_FULL'] as $option)
                        <option value="{{ $option }}" @selected($val('shift_type_default', 'FULL_TIME') === $option)>
                            {{ __("employees.shifts.{$option}") }}
                        </option>
                    @endforeach
                </select>
                @error('shift_type_default') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Work schedule (مواعيد العمل: حضور / انصراف) --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('employees.sections.work_schedule') }}</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="work_start_time" class="form-label">{{ __('employees.fields.work_start_time') }}</label>
                <input type="time" id="work_start_time" name="work_start_time" dir="ltr"
                       value="{{ $val('work_start_time') }}" class="form-control @error('work_start_time') is-invalid @enderror">
                @error('work_start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="work_end_time" class="form-label">{{ __('employees.fields.work_end_time') }}</label>
                <input type="time" id="work_end_time" name="work_end_time" dir="ltr"
                       value="{{ $val('work_end_time') }}" class="form-control @error('work_end_time') is-invalid @enderror">
                @error('work_end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12">
                @if ($editing)
                    <span class="form-label d-block">{{ __('employees.rest_days.title') }}</span>
                    <p class="small text-body-secondary">{{ __('employees.rest_days.hint') }}</p>
                    <form method="POST" action="{{ route('admin.employees.rest-days.store', $employee) }}"
                          class="row g-2 align-items-end mb-3">
                        @csrf
                        <div class="col-auto">
                            <label for="rest_day_date" class="form-label">{{ __('employees.rest_days.fields.date') }}</label>
                            <input type="date" name="date" id="rest_day_date" dir="ltr"
                                   class="form-control @error('date') is-invalid @enderror"
                                   value="{{ old('date') }}" required>
                            @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('employees.rest_days.add') }}</button>
                        </div>
                    </form>
                    @php $restDays = $employee->restDays()->orderBy('date')->get(); @endphp
                    @if ($restDays->isEmpty())
                        <p class="text-body-secondary mb-0">{{ __('employees.rest_days.empty') }}</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($restDays as $restDay)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span dir="ltr">{{ $restDay->date->toDateString() }}</span>
                                    <form method="POST"
                                          action="{{ route('admin.employees.rest-days.destroy', [$employee, $restDay]) }}"
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
                @else
                    <p class="small text-body-secondary mb-0">{{ __('employees.rest_days.hint') }}</p>
                    <p class="small text-body-secondary">{{ __('employees.rest_days.empty') }} — {{ __('general.save') }} {{ __('employees.edit_title') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Rates (MANAGER-only screen — never rendered to TEAM_LEADER) --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('employees.sections.rates') }}</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="currency" class="form-label">{{ __('employees.fields.currency') }}</label>
                <select id="currency" name="currency" class="form-select @error('currency') is-invalid @enderror" required>
                    @foreach (['EGP', 'AED'] as $option)
                        <option value="{{ $option }}" @selected($val('currency', 'EGP') === $option)>
                            {{ __("employees.currencies.{$option}") }}
                        </option>
                    @endforeach
                </select>
                @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            @foreach (['base_salary', 'online_half_day_rate', 'online_full_day_rate', 'overtime_hourly_rate'] as $rateField)
                <div class="col-md-4 col-lg-2">
                    <label for="{{ $rateField }}" class="form-label">{{ __("employees.fields.{$rateField}") }}</label>
                    <input type="number" step="0.01" min="0" id="{{ $rateField }}" name="{{ $rateField }}"
                           value="{{ $val($rateField, '0') }}" dir="ltr"
                           class="form-control @error($rateField) is-invalid @enderror" required>
                    @error($rateField) <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Profile --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('employees.sections.profile') }}</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="photo" class="form-label">{{ __('employees.fields.photo') }}</label>
                @if ($editing && $employee->profile?->personal_photo_path)
                    <div class="mb-2">
                        <img src="{{ route('employees.photo', $employee) }}" alt="{{ __('employees.fields.photo') }}"
                             class="rounded-circle object-fit-cover" style="width: 4rem; height: 4rem;">
                    </div>
                @endif
                <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png"
                       class="form-control @error('photo') is-invalid @enderror">
                <div class="form-text">{{ __('employees.fields.photo_hint') }}</div>
                @error('photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="employee_code" class="form-label">{{ __('employees.fields.employee_code') }}</label>
                <input type="text" id="employee_code" dir="ltr" readonly
                       value="{{ $editing ? $val('profile.employee_code') : ($nextEmployeeCode ?? '') }}"
                       class="form-control bg-body-secondary">
                <div class="form-text">{{ __('employees.fields.employee_code_hint') }}</div>
            </div>
            <div class="col-md-4">
                <label for="national_id" class="form-label">{{ __('employees.fields.national_id') }}</label>
                <input type="text" id="national_id" name="profile[national_id]" value="{{ $val('profile.national_id') }}" dir="ltr"
                       class="form-control @error('profile.national_id') is-invalid @enderror" maxlength="20">
                @error('profile.national_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="birth_date" class="form-label">{{ __('employees.fields.birth_date') }}</label>
                <input type="date" id="birth_date" name="profile[birth_date]" value="{{ $dateVal('profile.birth_date') }}" dir="ltr"
                       class="form-control @error('profile.birth_date') is-invalid @enderror">
                @error('profile.birth_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="address" class="form-label">{{ __('employees.fields.address') }}</label>
                <input type="text" id="address" name="profile[address]" value="{{ $val('profile.address') }}"
                       class="form-control @error('profile.address') is-invalid @enderror" maxlength="255">
                @error('profile.address') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="phone" class="form-label">{{ __('employees.fields.phone') }}</label>
                <input type="text" id="phone" name="profile[phone]" value="{{ $val('profile.phone') }}" dir="ltr"
                       class="form-control @error('profile.phone') is-invalid @enderror" maxlength="30">
                @error('profile.phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="gender" class="form-label">{{ __('employees.fields.gender') }}</label>
                <select id="gender" name="profile[gender]" class="form-select @error('profile.gender') is-invalid @enderror">
                    <option value="">{{ __('general.none') }}</option>
                    @foreach (['MALE', 'FEMALE'] as $option)
                        <option value="{{ $option }}" @selected($val('profile.gender') === $option)>
                            {{ __("employees.genders.{$option}") }}
                        </option>
                    @endforeach
                </select>
                @error('profile.gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="religion" class="form-label">{{ __('employees.fields.religion') }}</label>
                <input type="text" id="religion" name="profile[religion]" value="{{ $val('profile.religion') }}"
                       class="form-control @error('profile.religion') is-invalid @enderror" maxlength="50">
                @error('profile.religion') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="marital_status" class="form-label">{{ __('employees.fields.marital_status') }}</label>
                <select id="marital_status" name="profile[marital_status]" class="form-select @error('profile.marital_status') is-invalid @enderror">
                    <option value="">{{ __('general.none') }}</option>
                    @foreach (['SINGLE', 'MARRIED', 'DIVORCED', 'WIDOWED'] as $option)
                        <option value="{{ $option }}" @selected($val('profile.marital_status') === $option)>
                            {{ __("employees.marital_statuses.{$option}") }}
                        </option>
                    @endforeach
                </select>
                @error('profile.marital_status') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Contract --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('employees.sections.contract') }}</div>
    <div class="card-body">
        <div class="row g-3">
            @foreach (['hire_date', 'contract_start', 'contract_end'] as $dateField)
                <div class="col-md-3">
                    <label for="{{ $dateField }}" class="form-label">{{ __("employees.fields.{$dateField}") }}</label>
                    <input type="date" id="{{ $dateField }}" name="contract[{{ $dateField }}]"
                           value="{{ $dateVal("contract.{$dateField}") ?: $dateVal("activeContract.{$dateField}") }}" dir="ltr"
                           class="form-control @error("contract.{$dateField}") is-invalid @enderror" required>
                    @error("contract.{$dateField}") <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endforeach
            <div class="col-md-3">
                <label for="contract_type" class="form-label">{{ __('employees.fields.contract_type') }}</label>
                <input type="text" id="contract_type" name="contract[contract_type]"
                       value="{{ old('contract.contract_type', $editing ? $employee->activeContract?->contract_type : '') }}"
                       class="form-control @error('contract.contract_type') is-invalid @enderror" required maxlength="100">
                @error('contract.contract_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- Qualifications (dynamic rows — indexed names; JS reindexes on add/remove) --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('employees.sections.qualifications') }}</div>
    <div class="card-body">
        <div id="qualification-rows" data-prefix="qualifications">
            @foreach ($qualificationRows ?: [$qualificationBlank] as $i => $row)
                <div class="row g-3 mb-2" data-row>
                    <div class="col-md-5">
                        <label class="form-label @if ($i > 0) visually-hidden @endif">{{ __('employees.fields.degree') }}</label>
                        <input type="text" data-field="degree" name="qualifications[{{ $i }}][degree]"
                               value="{{ $row['degree'] ?? '' }}" class="form-control" maxlength="150"
                               autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label @if ($i > 0) visually-hidden @endif">{{ __('employees.fields.specialization') }}</label>
                        <input type="text" data-field="specialization" name="qualifications[{{ $i }}][specialization]"
                               value="{{ $row['specialization'] ?? '' }}" class="form-control" maxlength="150"
                               autocomplete="off">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label @if ($i > 0) visually-hidden @endif">{{ __('employees.fields.graduation_year') }}</label>
                        <input type="number" data-field="graduation_year" name="qualifications[{{ $i }}][graduation_year]"
                               value="{{ $row['graduation_year'] ?? '' }}" class="form-control" dir="ltr" min="1950" max="2100"
                               autocomplete="off">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row
                                aria-label="{{ __('employees.rows.remove') }}">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <template id="qualification-row-template">
            <div class="row g-3 mb-2" data-row>
                <div class="col-md-5">
                    <label class="form-label visually-hidden">{{ __('employees.fields.degree') }}</label>
                    <input type="text" data-field="degree" name="qualifications[__INDEX__][degree]" class="form-control" maxlength="150" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label visually-hidden">{{ __('employees.fields.specialization') }}</label>
                    <input type="text" data-field="specialization" name="qualifications[__INDEX__][specialization]" class="form-control" maxlength="150" autocomplete="off">
                </div>
                <div class="col-md-2">
                    <label class="form-label visually-hidden">{{ __('employees.fields.graduation_year') }}</label>
                    <input type="number" data-field="graduation_year" name="qualifications[__INDEX__][graduation_year]" class="form-control" dir="ltr" min="1950" max="2100" autocomplete="off">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row
                            aria-label="{{ __('employees.rows.remove') }}">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </template>

        <button type="button" class="btn btn-outline-primary btn-sm mt-1"
                data-add-row="qualification-row-template" data-target="qualification-rows"
                data-prefix="qualifications">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('employees.rows.add') }}
        </button>
    </div>
</div>

{{-- Emergency contacts (dynamic rows — indexed names) --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('employees.sections.contacts') }}</div>
    <div class="card-body">
        <div id="contact-rows" data-prefix="contacts">
            @foreach ($contactRows ?: [$contactBlank] as $i => $row)
                <div class="row g-3 mb-2" data-row>
                    <div class="col-md-4">
                        <label class="form-label @if ($i > 0) visually-hidden @endif">{{ __('employees.fields.contact_name') }}</label>
                        <input type="text" data-field="name" name="contacts[{{ $i }}][name]"
                               value="{{ $row['name'] ?? '' }}" class="form-control" maxlength="150"
                               autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label @if ($i > 0) visually-hidden @endif">{{ __('employees.fields.contact_phone') }}</label>
                        <input type="text" data-field="phone" name="contacts[{{ $i }}][phone]"
                               value="{{ $row['phone'] ?? '' }}" class="form-control" dir="ltr" maxlength="30"
                               autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label @if ($i > 0) visually-hidden @endif">{{ __('employees.fields.relationship') }}</label>
                        <input type="text" data-field="relationship" name="contacts[{{ $i }}][relationship]"
                               value="{{ $row['relationship'] ?? '' }}" class="form-control" maxlength="100"
                               autocomplete="off">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row
                                aria-label="{{ __('employees.rows.remove') }}">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <template id="contact-row-template">
            <div class="row g-3 mb-2" data-row>
                <div class="col-md-4">
                    <label class="form-label visually-hidden">{{ __('employees.fields.contact_name') }}</label>
                    <input type="text" data-field="name" name="contacts[__INDEX__][name]" class="form-control" maxlength="150" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label visually-hidden">{{ __('employees.fields.contact_phone') }}</label>
                    <input type="text" data-field="phone" name="contacts[__INDEX__][phone]" class="form-control" dir="ltr" maxlength="30" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label visually-hidden">{{ __('employees.fields.relationship') }}</label>
                    <input type="text" data-field="relationship" name="contacts[__INDEX__][relationship]" class="form-control" maxlength="100" autocomplete="off">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row
                            aria-label="{{ __('employees.rows.remove') }}">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </template>

        <button type="button" class="btn btn-outline-primary btn-sm mt-1"
                data-add-row="contact-row-template" data-target="contact-rows"
                data-prefix="contacts">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('employees.rows.add') }}
        </button>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('admin.employees.index') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
</div>
