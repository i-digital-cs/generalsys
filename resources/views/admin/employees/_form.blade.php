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
                    @foreach (auth()->user()->staffRolesTheyMayAssign() as $option)
                        <option value="{{ $option }}" @selected($val('role', 'EMPLOYEE') === $option)>
                            {{ __("employees.roles.{$option}") }}
                        </option>
                    @endforeach
                </select>
                @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="job_title_ar" class="form-label">{{ __('employees.fields.job_title_ar') }}</label>
                <input type="text" id="job_title_ar" name="job_title_ar" value="{{ $val('job_title_ar') }}"
                       class="form-control @error('job_title_ar') is-invalid @enderror" maxlength="150">
                @error('job_title_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="job_title_en" class="form-label">{{ __('employees.fields.job_title_en') }}</label>
                <input type="text" id="job_title_en" name="job_title_en" value="{{ $val('job_title_en') }}"
                       class="form-control @error('job_title_en') is-invalid @enderror" maxlength="150">
                @error('job_title_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            @php
                $savedLeaderIds = $editing
                    ? $employee->teamLeaders->pluck('id')->all()
                    : [];
                $postedLeaderIds = old('team_leader_ids');
                $leaderIds = is_array($postedLeaderIds)
                    ? array_values($postedLeaderIds)
                    : $savedLeaderIds;
                $firstLeaderId = $leaderIds[0] ?? old('team_leader_id', '');
                $secondLeaderId = $leaderIds[1] ?? '';
                $hideTeamLeaderFields = ! \App\Models\User::roleMayHaveTeamLeaders((string) $val('role', 'EMPLOYEE'));
            @endphp
            <div class="col-12 col-md-6 @if ($hideTeamLeaderFields) d-none @endif" data-team-leader-fields>
                <label for="team_leader_ids_0" class="form-label">{{ __('employees.team_leader_1') }}</label>
                <select id="team_leader_ids_0" name="team_leader_ids[0]"
                        class="form-select @error('team_leader_ids.0') is-invalid @enderror">
                    <option value="">{{ __('employees.no_team_leader') }}</option>
                    @foreach ($teamLeaders as $leader)
                        <option value="{{ $leader->id }}" @selected((string) $firstLeaderId === (string) $leader->id)>
                            {{ $leader->localizedName() }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('employees.team_leader_1_hint') }}</div>
                @error('team_leader_ids.0') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12 col-md-6 @if ($hideTeamLeaderFields) d-none @endif" data-team-leader-fields>
                <label for="team_leader_ids_1" class="form-label">{{ __('employees.team_leader_2') }}</label>
                <select id="team_leader_ids_1" name="team_leader_ids[1]"
                        class="form-select @error('team_leader_ids.1') is-invalid @enderror">
                    <option value="">{{ __('employees.no_second_team_leader') }}</option>
                    @foreach ($teamLeaders as $leader)
                        <option value="{{ $leader->id }}" @selected((string) $secondLeaderId === (string) $leader->id)>
                            {{ $leader->localizedName() }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('employees.team_leader_2_hint') }}</div>
                @error('team_leader_ids.1') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
        </div>
    </div>
</div>

{{-- Default shifts = أنواع الدوام + مواعيد العمل (two assign-style rows; every workday) --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('employees.sections.work_schedule') }}</div>
    <div class="card-body">
        <div class="form-label">{{ __('employees.fields.shift_types_default') }}</div>
        <p class="small text-body-secondary mb-3">{{ __('employees.fields.shift_types_default_hint') }}</p>
        @php
            $defaultProfiles = old('default_shifts');
            if (! is_array($defaultProfiles) || $defaultProfiles === []) {
                $stored = isset($employee) ? $employee->defaultShiftProfiles() : [[
                    'type' => 'FULL_TIME',
                    'start' => '10:00',
                    'end' => '18:00',
                ]];
                $defaultProfiles = [];
                foreach ($stored as $profile) {
                    $defaultProfiles[] = [
                        'shift_type' => $profile['type'],
                        'start' => $profile['start'],
                        'end' => $profile['end'],
                    ];
                }
            }
            while (count($defaultProfiles) < 3) {
                $defaultProfiles[] = ['shift_type' => '', 'start' => '', 'end' => ''];
            }
            $defaultProfiles = array_slice($defaultProfiles, 0, 3);
            $shiftWindows = \App\Services\Attendance\ShiftConfig::DEFAULT_TIME_WINDOWS;
        @endphp
        <div class="d-flex flex-column gap-3" data-default-shifts data-shift-times="{{ json_encode($shiftWindows) }}">
            @foreach ($defaultProfiles as $index => $row)
                <div class="border rounded p-3 bg-body-tertiary" data-default-shift-row>
                    <div class="small fw-semibold mb-2">
                        @if ($index === 0)
                            {{ __('employees.fields.default_shift_primary') }}
                        @elseif ($index === 1)
                            {{ __('employees.fields.default_shift_secondary') }}
                            <span class="text-body-secondary fw-normal">({{ __('employees.fields.optional') }})</span>
                        @else
                            {{ __('employees.fields.default_shift_tertiary') }}
                            <span class="text-body-secondary fw-normal">({{ __('employees.fields.optional') }})</span>
                        @endif
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" for="default_shift_type_{{ $index }}">{{ __('employees.fields.shift_type_default') }}</label>
                            <select id="default_shift_type_{{ $index }}"
                                    name="default_shifts[{{ $index }}][shift_type]"
                                    class="form-select @error('default_shifts.'.$index.'.shift_type') is-invalid @enderror"
                                    data-default-shift-type
                                    @if ($index === 0) required @endif>
                                @if ($index > 0)
                                    <option value="">{{ __('employees.fields.default_shift_none') }}</option>
                                @endif
                                @foreach (\App\Models\User::DEFAULT_SHIFT_OPTIONS as $option)
                                    <option value="{{ $option }}" @selected(($row['shift_type'] ?? '') === $option)>
                                        {{ __("employees.shifts.{$option}") }}
                                    </option>
                                @endforeach
                            </select>
                            @error('default_shifts.'.$index.'.shift_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label" for="default_shift_start_{{ $index }}">{{ __('employees.fields.work_start_time') }}</label>
                            <input type="time" id="default_shift_start_{{ $index }}"
                                   name="default_shifts[{{ $index }}][start]"
                                   dir="ltr"
                                   value="{{ $row['start'] ?? '' }}"
                                   class="form-control @error('default_shifts.'.$index.'.start') is-invalid @enderror"
                                   data-default-shift-start
                                   @if ($index === 0) required @endif>
                            @error('default_shifts.'.$index.'.start')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label" for="default_shift_end_{{ $index }}">{{ __('employees.fields.work_end_time') }}</label>
                            <input type="time" id="default_shift_end_{{ $index }}"
                                   name="default_shifts[{{ $index }}][end]"
                                   dir="ltr"
                                   value="{{ $row['end'] ?? '' }}"
                                   class="form-control @error('default_shifts.'.$index.'.end') is-invalid @enderror"
                                   data-default-shift-end
                                   @if ($index === 0) required @endif>
                            @error('default_shifts.'.$index.'.end')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @error('default_shifts')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @unless ($editing)
            <p class="small text-body-secondary mb-0 mt-3">{{ __('employees.rest_days.hint') }}</p>
            <p class="small text-body-secondary mb-0">
                {{ __('employees.rest_days.empty') }} — {{ __('general.save') }} {{ __('employees.edit_title') }}
            </p>
        @endunless
    </div>
</div>

{{-- Rates & currency: Manager + Assistant (TL never sees money) --}}
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
            @foreach (['base_salary'] as $rateField)
                <div class="col-6 col-md-4">
                    <label for="{{ $rateField }}" class="form-label">{{ __("employees.fields.{$rateField}") }}</label>
                    {{-- text+decimal: type=number in ar/RTL rejects 5500 (grouping) while 600 works --}}
                    <input type="text" inputmode="decimal" lang="en" dir="ltr" autocomplete="off"
                           id="{{ $rateField }}" name="{{ $rateField }}"
                           value="{{ $val($rateField, '0') }}" maxlength="20"
                           class="form-control @error($rateField) is-invalid @enderror" required>
                    @error($rateField) <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endforeach
            <div class="col-12">
                <p class="form-text mb-0">{{ __('employees.rates_formula_hint') }}</p>
            </div>
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
