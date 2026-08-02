<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Covers every rule used by the app's Form Requests plus the common core
    | for upcoming Phase-2 requests. Keep keys in sync with lang/ar.
    |
    */

    'required' => 'The :attribute field is required.',
    'required_with' => 'The :attribute field is required when :values is present.',
    'email' => 'The :attribute must be a valid email address.',
    'unique' => 'The :attribute has already been taken.',
    'exists' => 'The selected :attribute is invalid.',
    'integer' => 'The :attribute must be an integer.',
    'numeric' => 'The :attribute must be a number.',
    'string' => 'The :attribute must be a string.',
    'array' => 'The :attribute must be an array.',
    'file' => 'The :attribute must be a file.',
    'date' => 'The :attribute must be a valid date.',
    'date_format' => 'The :attribute must match the format :format.',
    'before' => 'The :attribute must be a date before :date.',
    'after_or_equal' => 'The :attribute must be a date after or equal to :date.',
    'in' => 'The selected :attribute is invalid.',
    'mimes' => 'The :attribute must be a file of type: :values.',
    'confirmed' => 'The :attribute confirmation does not match.',

    'min' => [
        'numeric' => 'The :attribute must be at least :min.',
        'file' => 'The :attribute must be at least :min kilobytes.',
        'string' => 'The :attribute must be at least :min characters.',
        'array' => 'The :attribute must have at least :min items.',
    ],

    'max' => [
        'numeric' => 'The :attribute must not be greater than :max.',
        'file' => 'The :attribute must not be greater than :max kilobytes.',
        'string' => 'The :attribute must not be greater than :max characters.',
        'array' => 'The :attribute must not have more than :max items.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Messages (per attribute.rule; wildcards supported)
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'qualifications.*.degree' => [
            'required_with' => 'Enter the degree or remove this row.',
        ],
        'contacts.*.name' => [
            'required_with' => 'Enter the contact name or remove this row.',
        ],
        'contacts.*.phone' => [
            'required_with' => 'Enter the contact phone number or remove this row.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Friendly Attribute Names
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name_ar' => 'name (Arabic)',
        'name_en' => 'name (English)',
        'email' => 'email',
        'password' => 'password',
        'role' => 'role',
        'status' => 'status',
        'currency' => 'currency',
        'language_preference' => 'language',
        'team_leader_id' => 'team leader',
        'shift_type_default' => 'default shift type',
        'work_start_time' => 'work start time',
        'work_end_time' => 'work end time',
        'base_salary' => 'base salary',
        'online_half_day_rate' => 'online half-day rate',
        'online_full_day_rate' => 'online full-day rate',
        'overtime_hourly_rate' => 'overtime hourly rate',
        'profile.employee_code' => 'employee code',
        'profile.national_id' => 'national ID',
        'profile.birth_date' => 'birth date',
        'profile.address' => 'address',
        'profile.phone' => 'phone',
        'profile.gender' => 'gender',
        'profile.religion' => 'religion',
        'profile.marital_status' => 'marital status',
        'contract.hire_date' => 'hire date',
        'contract.contract_start' => 'contract start',
        'contract.contract_end' => 'contract end',
        'contract.contract_type' => 'contract type',
        'date' => 'date',
        'reason' => 'reason',
        'photo' => 'personal photo',
        'documents' => 'documents',
        'documents.*' => 'file',
        'type' => 'type',
        'default_language' => 'default language',
        'default_currency' => 'default currency',
        'qualifications.*.degree' => 'degree',
        'qualifications.*.specialization' => 'specialization',
        'qualifications.*.graduation_year' => 'graduation year',
        'contacts.*.name' => 'contact name',
        'contacts.*.phone' => 'contact phone',
        'contacts.*.relationship' => 'relationship',
    ],

];
