<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'role' => ['required', Rule::in([User::ROLE_EMPLOYEE, User::ROLE_TEAM_LEADER])],
            'currency' => ['required', Rule::in(['EGP', 'AED'])],
            'language_preference' => ['required', Rule::in(['ar', 'en'])],
            'team_leader_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', User::ROLE_TEAM_LEADER),
            ],
            'shift_type_default' => ['required', Rule::in(['FULL_TIME', 'ONLINE_HALF', 'ONLINE_FULL'])],
            'dynamic_day_off' => ['nullable', Rule::in(['SAT', 'SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI'])],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],

            // Rates (T-1.5) — MANAGER-only screen, never rendered to TEAM_LEADER
            'base_salary' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'online_half_day_rate' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'online_full_day_rate' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'overtime_hourly_rate' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],

            // Profile
            'profile.employee_code' => [
                'required', 'string', 'max:50',
                Rule::unique('employee_profiles', 'employee_code')->ignore($employee?->profile?->id),
            ],
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

            // Qualifications (blank rows are dropped by EmployeeService)
            'qualifications' => ['nullable', 'array'],
            'qualifications.*.degree' => ['nullable', 'string', 'max:150'],
            'qualifications.*.specialization' => ['nullable', 'string', 'max:150'],
            'qualifications.*.graduation_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],

            // Emergency contacts (blank rows are dropped by EmployeeService)
            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => ['nullable', 'string', 'max:150'],
            'contacts.*.phone' => ['nullable', 'string', 'max:30', 'required_with:contacts.*.name'],
            'contacts.*.relationship' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    abstract protected function passwordRules(): array;
}
