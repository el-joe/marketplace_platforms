<?php

return [

    'ads' => [
        'block_hidden' => [
            'title' => 'تم إخفاء إعلانك',
            'message' => 'قام أحد المسؤولين بإخفاء البلوك المستضيف لمساحة إعلانك ":slot_code". توقف عرض إعلانك، لكن حجزك ما زال نشطًا.',
        ],
        'position_shifted' => [
            'title' => 'تغيّر موضع إعلانك',
            'message' => 'انتقلت مساحة إعلانك ":slot_code" من الموضع :old_rank إلى الموضع :new_rank بعد إعادة ترتيب بلوك الصفحة.',
        ],
        'page_archived' => [
            'title' => 'إعلانك لم يعد ظاهرًا',
            'message' => 'تمت أرشفة الصفحة ":page_name" المستضيفة لمساحة إعلانك ":slot_code". توقف عرض إعلانك، لكن حجزك ما زال نشطًا.',
        ],
        'booking_submitted' => [
            'title' => 'حجز إعلان جديد للمراجعة',
            'message' => 'قام :advertiser بتقديم حجز الإعلان :reference للمراجعة.',
        ],
        'booking_approved' => [
            'title' => 'تمت الموافقة على حجز الإعلان',
            'scheduled' => 'تمت الموافقة على حجز إعلانك :reference وهو مجدول للظهور بتاريخ :date.',
            'live' => 'تمت الموافقة على حجز إعلانك :reference وهو نشط الآن.',
            'payment_due' => 'تمت الموافقة على حجز إعلانك :reference. يرجى الدفع قبل :payment_due_at ليصبح نشطًا.',
        ],
        'booking_rejected' => [
            'title' => 'تم رفض حجز الإعلان',
            'message' => 'تم رفض حجز إعلانك :reference. السبب: :reason',
        ],
        'creative_rejected' => [
            'title' => 'تم رفض التصميم الإعلاني',
            'message' => 'تم رفض التصميم الإعلاني لحجز :reference (:code). السبب: :reason',
            'no_code' => 'بدون رمز',
        ],
        'payment_reminder' => [
            'title' => 'موعد الدفع يقترب',
            'message' => 'موعد دفع حجز الإعلان :reference هو :payment_due_at. ادفع الآن لتجنب فقدان المساحة الإعلانية.',
        ],
        'booking_live' => [
            'title' => 'إعلانك نشط الآن',
            'message' => 'حجز الإعلان :reference أصبح نشطًا الآن.',
        ],
        'booking_paused' => [
            'title' => 'تم إيقاف حجز الإعلان مؤقتًا',
            'message' => 'تم إيقاف حجز الإعلان :reference مؤقتًا. السبب: :reason',
        ],
        'booking_completed' => [
            'title' => 'اكتمل حجز الإعلان',
            'message' => 'انتهى حجز الإعلان :reference: :impressions ظهور، :clicks نقرة، :spend :currency تم إنفاقها.',
        ],
        'booking_cancelled' => [
            'title' => 'تم إلغاء حجز الإعلان',
            'message' => 'تم إلغاء حجز الإعلان :reference. المبلغ المسترد: :refund :currency.',
        ],
        'booking_expired' => [
            'title' => 'انتهت صلاحية حجز الإعلان',
            'message' => 'انتهت صلاحية حجز الإعلان :reference قبل إتمام الدفع.',
        ],
    ],

];
