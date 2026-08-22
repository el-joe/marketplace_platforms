<?php

return [

    'validation_failed' => 'فشل التحقق من صحة البيانات.',
    'not_implemented'   => 'غير مُنفَّذ بعد.',

    'checkout' => [
        'calculation_failed'          => 'فشل حساب الدفع.',
        'preview_calculated'          => 'تم حساب معاينة الدفع',
        'invalid_coupon_code'         => 'كود الكوبون غير صالح.',
        'coupon_not_active'           => 'الكوبون غير مفعّل.',
        'coupon_not_valid_now'        => 'الكوبون غير صالح في الوقت الحالي.',
        'coupon_usage_limit_reached'  => 'تم بلوغ الحد الأقصى لاستخدام الكوبون.',
        'coupon_max_uses_reached'     => 'لقد استخدمت هذا الكوبون بالفعل الحد الأقصى المسموح به من المرات.',
        'coupon_currency_mismatch'    => 'عملة الكوبون لا تتطابق مع دولتك.',
        'coupon_valid'                => 'الكوبون صالح',
        'order_already_placed'        => 'تم إنشاء الطلب مسبقًا.',
        'insufficient_stock'          => 'الكمية غير كافية لعنصر واحد أو أكثر. يرجى تحديث سلتك.',
        'gift_card_balance_changed'   => 'تغيّر رصيد بطاقة الهدايا. يرجى المحاولة مرة أخرى.',
        'wallet_balance_changed'      => 'تغيّر رصيد المحفظة أو أن المحفظة مجمّدة. يرجى المحاولة مرة أخرى.',
        'order_placed_successfully'   => 'تم إنشاء الطلب بنجاح',
    ],

    'coupon' => [
        'applied' => 'تم تطبيق الكوبون بنجاح',
        'removed' => 'تمت إزالة الكوبون بنجاح',
    ],

    'gift_card_store' => [
        'purchased'             => 'تم شراء بطاقة الهدايا بنجاح.',
        'purchase_not_found'    => 'عملية شراء بطاقة الهدايا غير موجودة.',
        'not_yet_available'     => 'بطاقة الهدايا غير متاحة بعد للتسليم.',
        'max_resend_reached'    => 'تم بلوغ الحد الأقصى لمحاولات إعادة الإرسال. يرجى التواصل مع الدعم.',
        'delivery_email_resent' => 'تمت إعادة إرسال بريد التسليم.',
    ],

    'customer_wallet' => [
        'gift_card_redeemed' => 'تم استبدال بطاقة الهدايا. تمت إضافة :currency :amount إلى محفظتك.',
        'voucher_redeemed'   => 'تم استبدال القسيمة. تمت إضافة :currency :amount إلى محفظتك.',
    ],

    'gift_card' => [
        'invalid' => 'بطاقة الهدايا غير صالحة أو منتهية الصلاحية أو غير قابلة للاستخدام بهذه العملة.',
    ],

    'listing' => [
        'country_not_found'           => 'الدولة غير موجودة أو غير مفعّلة.',
        'not_found'                   => 'المنتج غير موجود أو غير متاح في هذه الدولة.',
        'shipping_zone_unresolvable'  => 'تعذّر تحديد منطقة الشحن لهذا العنوان.',
        'shipping_method_not_found'   => 'طريقة الشحن غير موجودة.',
    ],

    'misc' => [
        'country_not_found' => 'الدولة غير موجودة.',
    ],

    'newsletter' => [
        'subscribed'    => 'تم اشتراكك في نشرتنا البريدية.',
        'resubscribed'  => 'مرحبًا بعودتك! تم إعادة اشتراكك في نشرتنا البريدية.',
        'unsubscribed'  => 'تم إلغاء اشتراكك في نشرتنا البريدية.',
        'invalid_token' => 'رابط إلغاء الاشتراك غير صالح أو منتهي الصلاحية.',
    ],

    'notification' => [
        'not_found'             => 'الإشعار غير موجود.',
        'all_marked_read'       => 'تم تعليم جميع الإشعارات كمقروءة.',
        'device_registered'     => 'تم تسجيل الجهاز.',
        'device_removed'        => 'تمت إزالة الجهاز.',
        'preferences_updated'   => 'تم تحديث تفضيلات الإشعارات.',
    ],

    'order' => [
        'retrieved'              => 'تم استرجاع الطلبات',
        'not_found'              => 'الطلب غير موجود.',
        'single_retrieved'       => 'تم استرجاع الطلب',
        'sub_order_not_found'    => 'الطلب الفرعي غير موجود.',
        'sub_order_retrieved'    => 'تم استرجاع الطلب الفرعي',
        'tracking_retrieved'     => 'تم استرجاع بيانات التتبع',
        'cannot_cancel_status'   => 'لا يمكن إلغاء هذا الطلب في حالته الحالية.',
        'cancelled_successfully' => 'تم إلغاء الطلب بنجاح',
        'invoice_retrieved'      => 'تم استرجاع بيانات الفاتورة',
    ],

    'product_detail' => [
        'not_found'          => 'المنتج غير موجود.',
        'country_not_found'  => 'الدولة غير موجودة أو غير مفعّلة.',
        'listing_not_found'  => 'العرض غير موجود',
    ],

    'return_request' => [
        'submitted'    => 'تم تقديم طلب الإرجاع.',
        'not_found'    => 'طلب الإرجاع غير موجود.',
        'message_sent' => 'تم إرسال الرسالة.',
    ],

    'security' => [
        'current_password_incorrect' => 'كلمة المرور الحالية غير صحيحة.',
        'password_changed'           => 'تم تغيير كلمة المرور بنجاح. يرجى تسجيل الدخول مرة أخرى.',
        'email_already_verified'     => 'البريد الإلكتروني موثّق بالفعل.',
        'otp_sent_to'                => 'تم إرسال رمز التحقق إلى :destination',
        'invalid_or_expired_otp'     => 'رمز التحقق غير صالح أو منتهي الصلاحية.',
        'email_verified'             => 'تم توثيق البريد الإلكتروني بنجاح.',
        'phone_already_verified'     => 'رقم الهاتف موثّق بالفعل.',
        'phone_verified'             => 'تم توثيق رقم الهاتف بنجاح.',
        'sessions_retrieved'         => 'تم استرجاع الجلسات النشطة.',
        'device_not_found'           => 'الجهاز غير موجود.',
        'device_revoked'             => 'تم إلغاء صلاحية الجهاز.',
        'all_devices_revoked'        => 'تم إلغاء صلاحية جميع الأجهزة. يرجى تسجيل الدخول مرة أخرى.',
        'otp_wait'                   => 'يرجى الانتظار :seconds ثانية قبل طلب رمز تحقق آخر.',
    ],

    'warranty' => [
        'order_item_not_found' => 'عنصر الطلب غير موجود.',
        'already_has_warranty' => 'هذا العنصر لديه ضمان بالفعل.',
        'no_plans_available'   => 'لا توجد خطط ضمان متاحة لهذا العنصر.',
        'plans_retrieved'      => 'تم استرجاع خطط الضمان.',
        'claim_submitted'      => 'تم تقديم مطالبة الضمان.',
        'claim_not_found'      => 'مطالبة الضمان غير موجودة.',
        'claim_closed'         => 'مطالبة الضمان هذه مغلقة ولا تقبل رسائل جديدة.',
        'message_sent'         => 'تم إرسال الرسالة.',
    ],

    'wishlist' => [
        'max_groups_reached'    => 'يمكنك إنشاء 20 مجموعة رغبات كحد أقصى.',
        'group_created'         => 'تم إنشاء مجموعة الرغبات.',
        'group_not_found'       => 'مجموعة الرغبات غير موجودة.',
        'name_empty'            => 'لا يمكن ترك الاسم فارغًا.',
        'group_updated'         => 'تم تحديث المجموعة.',
        'cannot_delete_default' => 'لا يمكن حذف قائمة رغباتك الافتراضية.',
        'group_deleted'         => 'تم حذف المجموعة. تم نقل العناصر إلى قائمة رغباتك الافتراضية.',
        'country_not_found'     => 'الدولة غير موجودة أو غير مفعّلة.',
        'listing_not_found'     => 'المنتج غير موجود أو غير متاح.',
        'already_in_wishlist'   => 'موجود بالفعل في قائمة الرغبات هذه.',
        'added_to_wishlist'     => 'تمت الإضافة إلى قائمة الرغبات.',
        'item_not_found'        => 'عنصر قائمة الرغبات غير موجود.',
        'target_group_not_found' => 'مجموعة الرغبات المستهدفة غير موجودة.',
        'items_moved'           => 'تم نقل :count عنصر.',
    ],

];
