<?php

return [
    'title' => 'لوحة شركة الشحن (المشرف)',
    'meta' => 'الرابط: <code>carrier.{domain}</code> &middot; الحارس: <code>shipping_company_supervisors</code> &middot; تابع لـ: <code>shipping_companies</code>',

    'dashboard' => [
        'title' => 'لوحة المعلومات',
        'summary' => 'التوصيلات النشطة، عدد العناصر، مؤشرات SLA',
    ],

    'agents' => [
        'title' => 'العناصر',
        'agents' => 'إنشاء/إدارة عناصر التوصيل لشركة الشحن هذه',
        'unlimited' => 'عدد غير محدود من العناصر — لا يوجد حد أقصى',
        'toggle' => 'تعليق/تفعيل العناصر',
    ],

    'supervisors' => [
        'title' => 'المشرفون',
        'supervisors' => 'إدارة المشرفين ضمن نفس شركة الشحن',
        'owner_only' => 'المشرف على مستوى المالك فقط يمكنه إنشاء مشرفين آخرين (مقيّد في المتحكم)',
    ],

    'assignments' => [
        'title' => 'المهام',
        'unassigned' => 'الطلبات التي لم تُسند بعد لعنصر',
        'assign' => 'إسناد الشحنات للعناصر؛ إعادة الإسناد عند الحاجة',
        'all' => 'جميع المهام مع تتبع الحالة',
        'detail' => 'عرض تفصيلي لكل مهمة',
    ],
];
