<?php

return [

    /*
    |--------------------------------------------------------------------------
    | رسائل التحقق من الإدخال
    |--------------------------------------------------------------------------
    |
    | تغطي كل القواعد المستخدمة في طلبات التحقق الحالية بالإضافة إلى القواعد
    | الأساسية الشائعة لطلبات المرحلة الثانية القادمة.
    |
    */

    'required' => 'حقل :attribute مطلوب.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صالحًا.',
    'unique' => ':attribute مُستخدم من قبل.',
    'exists' => 'القيمة المحددة لـ :attribute غير صالحة.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'array' => 'يجب أن يكون :attribute قائمة.',
    'file' => 'يجب أن يكون :attribute ملفًا.',
    'date' => 'يجب أن يكون :attribute تاريخًا صالحًا.',
    'date_format' => 'يجب أن يطابق :attribute الصيغة :format.',
    'before' => 'يجب أن يكون :attribute تاريخًا يسبق :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخًا لاحقًا لـ :date أو مساويًا له.',
    'in' => 'القيمة المحددة لـ :attribute غير صالحة.',
    'mimes' => 'يجب أن يكون :attribute ملفًا من النوع: :values.',
    'confirmed' => 'تأكيد :attribute غير متطابق.',

    'min' => [
        'numeric' => 'يجب ألا يقل :attribute عن :min.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
        'string' => 'يجب ألا يقل :attribute عن :min أحرف.',
        'array' => 'يجب أن يحتوي :attribute على :min عنصر على الأقل.',
    ],

    'max' => [
        'numeric' => 'يجب ألا يزيد :attribute عن :max.',
        'file' => 'يجب ألا يزيد حجم :attribute عن :max كيلوبايت.',
        'string' => 'يجب ألا يزيد :attribute عن :max حرفًا.',
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصر.',
    ],

    /*
    |--------------------------------------------------------------------------
    | رسائل مخصصة لحقول بعينها (attribute.rule — تدعم النجمة للصفوف)
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'qualifications.*.degree' => [
            'required_with' => 'أدخل الشهادة أو احذف هذا السطر.',
        ],
        'contacts.*.name' => [
            'required_with' => 'أدخل اسم جهة الاتصال أو احذف هذا السطر.',
        ],
        'contacts.*.phone' => [
            'required_with' => 'أدخل رقم هاتف جهة الاتصال أو احذف هذا السطر.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | أسماء الحقول المعروضة في الرسائل
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name_ar' => 'الاسم (بالعربية)',
        'name_en' => 'الاسم (بالإنجليزية)',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'role' => 'الدور',
        'status' => 'الحالة',
        'currency' => 'العملة',
        'language_preference' => 'اللغة',
        'team_leader_id' => 'قائد الفريق',
        'shift_type_default' => 'نوع الوردية الافتراضي',
        'work_start_time' => 'موعد الحضور',
        'work_end_time' => 'موعد الانصراف',
        'base_salary' => 'الراتب الأساسي',
        'online_half_day_rate' => 'معدل نصف اليوم أونلاين',
        'online_full_day_rate' => 'معدل اليوم الكامل أونلاين',
        'overtime_hourly_rate' => 'معدل ساعة العمل الإضافي',
        'profile.employee_code' => 'كود الموظف',
        'profile.national_id' => 'الرقم القومي',
        'profile.birth_date' => 'تاريخ الميلاد',
        'profile.address' => 'العنوان',
        'profile.phone' => 'الهاتف',
        'profile.gender' => 'النوع',
        'profile.religion' => 'الديانة',
        'profile.marital_status' => 'الحالة الاجتماعية',
        'contract.hire_date' => 'تاريخ التعيين',
        'contract.contract_start' => 'بداية العقد',
        'contract.contract_end' => 'نهاية العقد',
        'contract.contract_type' => 'نوع العقد',
        'date' => 'التاريخ',
        'reason' => 'السبب',
        'photo' => 'الصورة الشخصية',
        'documents' => 'المستندات',
        'documents.*' => 'الملف',
        'type' => 'النوع',
        'default_language' => 'اللغة الافتراضية',
        'default_currency' => 'العملة الافتراضية',
        'qualifications.*.degree' => 'الشهادة',
        'qualifications.*.specialization' => 'التخصص',
        'qualifications.*.graduation_year' => 'سنة التخرج',
        'contacts.*.name' => 'اسم جهة الاتصال',
        'contacts.*.phone' => 'هاتف جهة الاتصال',
        'contacts.*.relationship' => 'صلة القرابة',
    ],

];
