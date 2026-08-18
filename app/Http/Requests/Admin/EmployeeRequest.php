<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\Attendance\ShiftConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for the employee create/edit wizard
 * (account + rates + profile + contract + qualifications + contacts).
 */
abstract class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    /**
     * Drop fully blank qualification/contact placeholder rows so empty
     * add/remove slots never trigger required_with (browser autofill / unused rows).
     */
    protected function prepareForValidation(): void
    {
        $payload = [
            'qualifications' => $this->nonEmptyRows(
                $this->input('qualifications', []),
                ['degree', 'specialization', 'graduation_year'],
            ),
            'contacts' => $this->nonEmptyRows(
                $this->input('contacts', []),
                ['name', 'phone', 'relationship'],
            ),
        ];

        // Accountant reports to Admin — never assign a team leader.
        if ($this->input('role') === User::ROLE_ACCOUNTANT) {
            $payload['team_leader_id'] = null;
        }

        $rows = $this->input('default_shifts', []);
        if (! is_array($rows) || $rows === []) {
            $legacyList = $this->input('shift_types_default', []);
            if (is_array($legacyList) && $legacyList !== []) {
                $rows = [];
                foreach ($legacyList as $item) {
                    if (is_string($item)) {
                        $window = ShiftConfig::DEFAULT_TIME_WINDOWS[$item] ?? ['10:00', '18:00'];
                        $rows[] = ['shift_type' => $item, 'start' => $window[0], 'end' => $window[1]];
                    } elseif (is_array($item)) {
                        $rows[] = [
                            'shift_type' => $item['type'] ?? $item['shift_type'] ?? null,
                            'start' => $item['start'] ?? null,
                            'end' => $item['end'] ?? null,
                        ];
                    }
                }
            } elseif (is_string($this->input('shift_type_default')) && $this->input('shift_type_default') !== '') {
                $legacy = $this->input('shift_type_default');
                foreach (User::normalizeDefaultShiftProfiles([], $legacy) as $profile) {
                    $rows[] = [
                        'shift_type' => $profile['type'],
                        'start' => $profile['start'],
                        'end' => $profile['end'],
                    ];
                }
            }
        }

        $payload['default_shifts'] = array_values(is_array($rows) ? $rows : []);

        $this->merge($payload);
    }

    /**
     * @param  array<int, array<string, mixed>>|mixed  $rows
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    private function nonEmptyRows(mixed $rows, array $keys): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, function ($row) use ($keys) {
            if (! is_array($row)) {
                return false;
            }

            foreach ($keys as $key) {
                $value = $row[$key] ?? null;
                if ($value !== null && $value !== '') {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $employee */
        $employee = $this->route('employee');

        return [
            // Account
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'string', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($employee),
            ],
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::in([User::ROLE_EMPLOYEE, User::ROLE_TEAM_LEADER, User::ROLE_ACCOUNTANT])],
            'job_title_ar' => ['nullable', 'string', 'max:150'],
            'job_title_en' => ['nullable', 'string', 'max:150'],
            'currency' => ['required', Rule::in(['EGP', 'AED'])],
            'language_preference' => ['required', Rule::in(['ar', 'en'])],
            'team_leader_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', User::ROLE_TEAM_LEADER),
            ],
            'default_shifts' => ['required', 'array', 'min:1', 'max:2'],
            'default_shifts.0.shift_type' => ['required', 'string', Rule::in(User::DEFAULT_SHIFT_OPTIONS)],
            'default_shifts.0.start' => ['required', 'date_format:H:i'],
            'default_shifts.0.end' => ['required', 'date_format:H:i'],
            'default_shifts.1.shift_type' => ['nullable', 'string', Rule::in(User::DEFAULT_SHIFT_OPTIONS)],
            'default_shifts.1.start' => ['nullable', 'required_with:default_shifts.1.shift_type', 'date_format:H:i'],
            'default_shifts.1.end' => ['nullable', 'required_with:default_shifts.1.shift_type', 'date_format:H:i'],
            'work_start_time' => ['nullable', 'date_format:H:i'],
            'work_end_time' => ['nullable', 'date_format:H:i'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],

            // Rates (T-1.5) — MANAGER-only screen, never rendered to TEAM_LEADER
            'base_salary' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'online_half_day_rate' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'online_full_day_rate' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'overtime_hourly_rate' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],

            // Profile — employee_code is auto-generated by EmployeeService (ignored if posted)
            'profile.national_id' => ['nullable', 'string', 'max:20'],
            'profile.birth_date' => ['nullable', 'date', 'before:today'],
            'profile.address' => ['nullable', 'string', 'max:255'],
            'profile.phone' => ['nullable', 'string', 'max:30'],
            'profile.gender' => ['nullable', Rule::in(['MALE', 'FEMALE'])],
            'profile.religion' => ['nullable', 'string', 'max:50'],
            'profile.marital_status' => ['nullable', Rule::in(['SINGLE', 'MARRIED', 'DIVORCED', 'WIDOWED'])],

            // Contract
            'contract.hire_date' => ['required', 'date'],
            'contract.contract_start' => ['required', 'date'],
            'contract.contract_end' => ['required', 'date', 'after_or_equal:contract.contract_start'],
            'contract.contract_type' => ['required', 'string', 'max:100'],

            // Qualifications (blank rows stripped in prepareForValidation;
            // a touched row must have its degree)
            'qualifications' => ['nullable', 'array'],
            'qualifications.*.degree' => [
                'nullable', 'string', 'max:150',
                'required_with:qualifications.*.specialization,qualifications.*.graduation_year',
            ],
            'qualifications.*.specialization' => ['nullable', 'string', 'max:150'],
            'qualifications.*.graduation_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],

            // Emergency contacts (a touched row must have name + phone)
            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => [
                'nullable', 'string', 'max:150',
                'required_with:contacts.*.phone,contacts.*.relationship',
            ],
            'contacts.*.phone' => [
                'nullable', 'string', 'max:30',
                'required_with:contacts.*.name,contacts.*.relationship',
            ],
            'contacts.*.relationship' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rows = array_values($this->input('default_shifts', []) ?: []);
            $types = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $type = $row['shift_type'] ?? null;
                if (! is_string($type) || $type === '') {
                    continue;
                }
                if (isset($types[$type])) {
                    $validator->errors()->add(
                        'default_shifts',
                        __('employees.validation.default_shifts_distinct'),
                    );

                    return;
                }
                $types[$type] = true;
            }
        });
    }

    /**
     * @return array<int, mixed>
     */
    abstract protected function passwordRules(): array;
}
