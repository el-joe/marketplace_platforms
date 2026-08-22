<?php

return [
    'title' => 'المستودعات و FBN',

    'types' => [
        'heading' => '1. أنواع المستودعات',
        'platform_fbn' => 'مركز تنفيذ تابع للمنصة',
        'vendor_owned' => 'مستودع مسجّل تابع للبائع (FBM/FBP)',
        'cross_dock' => 'محطة عبور مؤقتة',
    ],

    'inventory_columns' => [
        'heading' => '2. أعمدة المخزون',
        'on_hand' => 'المخزون الفعلي (قابل للكتابة)',
        'reserved' => 'محجوز في السلال / الطلبات المعلّقة (قابل للكتابة)',
        'available' => 'قيمة افتراضية =',
        'warning' => 'لا تكتب أبدًا إلى quantity_available.',
    ],

    'movements' => [
        'heading' => '3. inventory_movements — إضافة فقط',
        'body' => 'كل تغيير في المخزون ينشئ سجلًا جديدًا. لا تحديث ولا حذف.',
        'types' => 'الأنواع:',
        'received_note' => 'received_at عند حركة الاستلام: يبدأ عداد التخزين المجاني.',
    ],

    'inbound_flow' => [
        'heading' => '4. مسار طلب الإدخال إلى FBN',
        'submit' => 'يقدّم البائع الطلب ← الحالة: draft ← submitted',
        'approve' => 'يوافق المسؤول ← approved',
        'ship' => 'يشحن البائع ← يضيف رقم التتبع',
        'receive' => 'يستلم المستودع ← الحالة: received',
        'movement_created' => 'يُنشأ inventory_movement: النوع=inbound، received_at=الآن',
        'on_hand_incremented' => 'تُزاد قيمة quantity_on_hand',
        'storage_begins' => 'تبدأ فترة التخزين المجاني',
        'orderable' => 'يصبح الإعلان قابلاً للطلب عندما تكون quantity_available > 0',
    ],

    'free_storage' => [
        'heading' => '5. فترة التخزين المجاني',
        'default' => 'warehouses.free_storage_days (الافتراضي: 30)',
    ],

    'overage_fees' => [
        'heading' => '6. رسوم التخزين الزائد اليومية',
        'after' => 'بعد free_period_ends_at:',
        'job' => 'تعمل المهمة الساعة 01:00 يوميًا ← تُدرج سجلًا في fbn_daily_overage_fees (لا يتكرر)',
        'monthly' => 'شهريًا: تجمّع GenerateFbnStorageFeesJob الرسوم ← تُنشئ فاتورة ← تُخصم من المستحقات',
    ],

    'transfers' => [
        'heading' => '7. تحويلات المخزون',
        'body' => 'نقل المخزون بين المستودعات.',
    ],
];
