<?php

return [
    'title' => 'السلف',
    'subtitle' => 'سلف الرواتب. يُسحب الخصم الشهري إلى مسودة الراتب ويُنقص الرصيد المتبقي عند الاعتماد.',
    'list' => 'السلف',
    'create' => 'إضافة سلفة',
    'edit' => 'تعديل سلفة',
    'my_title' => 'سلفي',
    'my_subtitle' => 'سلف الرواتب والرصيد المتبقي.',
    'empty_title' => 'لا توجد سلف بعد',
    'empty_text' => 'أنشئ سلفة لموظف ليتم خصمها في الراتب التالي.',
    'my_empty_title' => 'لا توجد سلف',
    'my_empty_text' => 'لا توجد سلف مسجلة لحسابك.',
    'fields' => [
        'employee' => 'الموظف',
        'amount' => 'المبلغ',
        'date_given' => 'تاريخ الصرف',
        'deduction_amount_monthly' => 'الخصم الشهري',
        'remaining_amount' => 'المتبقي',
        'status' => 'الحالة',
    ],
    'status' => [
        'ACTIVE' => 'نشطة',
        'FULLY_DEDUCTED' => 'مخصومة بالكامل',
    ],
    'flash' => [
        'created' => 'تم إنشاء السلفة.',
        'updated' => 'تم تحديث السلفة.',
    ],
];
