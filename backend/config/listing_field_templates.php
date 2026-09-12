<?php

return [

    'abaya_measurements' => [
        'label_en' => 'Abaya Measurements',
        'label_ar' => 'مقاسات العباءة',
        'fields' => [
            ['field_type' => 'number', 'label_en' => 'Bust', 'label_ar' => 'عرض الصدر', 'placeholder_en' => 'e.g. 94', 'placeholder_ar' => 'مثال: 94', 'unit' => 'cm', 'is_required' => true],
            ['field_type' => 'number', 'label_en' => 'Waist', 'label_ar' => 'الخصر', 'placeholder_en' => 'e.g. 78', 'placeholder_ar' => 'مثال: 78', 'unit' => 'cm', 'is_required' => true],
            ['field_type' => 'number', 'label_en' => 'Hip', 'label_ar' => 'محيط الحوض', 'placeholder_en' => 'e.g. 100', 'placeholder_ar' => 'مثال: 100', 'unit' => 'cm', 'is_required' => true],
            ['field_type' => 'number', 'label_en' => 'Item Length', 'label_ar' => 'طول الملابس', 'placeholder_en' => 'e.g. 145', 'placeholder_ar' => 'مثال: 145', 'unit' => 'cm', 'is_required' => true],
            ['field_type' => 'number', 'label_en' => 'Sleeve from Neck', 'label_ar' => 'الكم من الرقبة', 'placeholder_en' => 'e.g. 72', 'placeholder_ar' => 'مثال: 72', 'unit' => 'cm', 'is_required' => false],
            ['field_type' => 'number', 'label_en' => 'Sleeve from Shoulder', 'label_ar' => 'الكم من الكتف', 'placeholder_en' => 'e.g. 60', 'placeholder_ar' => 'مثال: 60', 'unit' => 'cm', 'is_required' => false],
            ['field_type' => 'number', 'label_en' => 'Sleeve Width', 'label_ar' => 'عرض الكم', 'placeholder_en' => 'e.g. 18', 'placeholder_ar' => 'مثال: 18', 'unit' => 'cm', 'is_required' => false],
        ],
    ],

    'clothing_basic' => [
        'label_en' => 'Basic Clothing Measurements',
        'label_ar' => 'المقاسات الأساسية',
        'fields' => [
            ['field_type' => 'number', 'label_en' => 'Bust', 'label_ar' => 'الصدر', 'unit' => 'cm', 'is_required' => true],
            ['field_type' => 'number', 'label_en' => 'Waist', 'label_ar' => 'الخصر', 'unit' => 'cm', 'is_required' => true],
            ['field_type' => 'number', 'label_en' => 'Hip', 'label_ar' => 'الحوض', 'unit' => 'cm', 'is_required' => true],
            ['field_type' => 'number', 'label_en' => 'Item Length', 'label_ar' => 'طول القطعة', 'unit' => 'cm', 'is_required' => true],
        ],
    ],

    'shoe_size' => [
        'label_en' => 'Shoe Size',
        'label_ar' => 'مقاس الحذاء',
        'fields' => [
            ['field_type' => 'number', 'label_en' => 'Shoe Size (EU)', 'label_ar' => 'مقاس الحذاء (أوروبي)', 'unit' => 'EU', 'is_required' => true],
        ],
    ],

];
