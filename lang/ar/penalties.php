<?php

return [
    'title' => 'قواعد الخصم',
    'subtitle' => 'معدلات حسب العملة تُستخدم عند رفض أو إجبار إذن الانصراف/التأخير. قادة الفريق لا يرون هذه المبالغ.',
    'list' => 'القواعد',
    'create' => 'إضافة قاعدة',
    'edit' => 'تعديل القاعدة',
    'empty_title' => 'لا توجد قواعد خصم بعد',
    'empty_text' => 'ازرع القيم الافتراضية أو أضف قاعدة للبدء.',
    'fields' => [
        'name' => 'الاسم',
        'type' => 'النوع',
        'currency' => 'العملة',
        'amount_per_unit' => 'المبلغ لكل وحدة',
        'unit' => 'الوحدة',
        'double_penalty_multiplier' => 'مضاعف الإجبار',
        'is_active' => 'مفعّلة',
        'status' => 'الحالة',
    ],
    'types' => [
        'LATE_ARRIVAL' => 'تأخير',
        'LEAVE_PERMISSION' => 'إذن انصراف',
        'LATE_PERMISSION' => 'إذن تأخير',
        'EXCUSED_ABSENCE' => 'غياب بعذر',
        'UNEXCUSED_ABSENCE' => 'غياب بدون عذر',
    ],
    'units' => [
        'MINUTE' => 'لكل دقيقة',
        'HOUR' => 'لكل ساعة',
        'DAY' => 'لكل يوم',
    ],
    'active' => 'مفعّلة',
    'inactive' => 'غير مفعّلة',
    'flash' => [
        'created' => 'تم إنشاء قاعدة الخصم.',
        'updated' => 'تم تحديث قاعدة الخصم.',
    ],
];
