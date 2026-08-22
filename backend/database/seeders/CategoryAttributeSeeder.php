<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryAttributeSeeder extends Seeder
{
    public function run(): void
    {
        // ── Attributes ──────────────────────────────────────────────────────
        $attributes = [
            // Variant-defining (used as variant axes)
            ['code' => 'color', 'name_en' => 'Color', 'name_ar' => 'اللون', 'type' => 'color', 'is_variant_attribute' => true, 'is_filterable' => true, 'sort_order' => 1],
            ['code' => 'size', 'name_en' => 'Size', 'name_ar' => 'المقاس', 'type' => 'select', 'is_variant_attribute' => true, 'is_filterable' => true, 'sort_order' => 2],
            ['code' => 'storage', 'name_en' => 'Storage', 'name_ar' => 'التخزين', 'type' => 'select', 'is_variant_attribute' => true, 'is_filterable' => true, 'sort_order' => 3],
            ['code' => 'ram', 'name_en' => 'RAM', 'name_ar' => 'ذاكرة الوصول', 'type' => 'select', 'is_variant_attribute' => true, 'is_filterable' => true, 'sort_order' => 4],
            // Spec attributes
            ['code' => 'brand_model', 'name_en' => 'Model', 'name_ar' => 'الموديل', 'type' => 'text', 'is_variant_attribute' => false, 'is_filterable' => false, 'sort_order' => 10],
            ['code' => 'material', 'name_en' => 'Material', 'name_ar' => 'الخامة', 'type' => 'select', 'is_variant_attribute' => false, 'is_filterable' => true, 'sort_order' => 11],
            ['code' => 'screen_size', 'name_en' => 'Screen Size', 'name_ar' => 'حجم الشاشة', 'type' => 'number', 'is_variant_attribute' => false, 'is_filterable' => true, 'unit' => 'inch', 'sort_order' => 12],
            ['code' => 'battery', 'name_en' => 'Battery', 'name_ar' => 'البطارية', 'type' => 'number', 'is_variant_attribute' => false, 'is_filterable' => false, 'unit' => 'mAh', 'sort_order' => 13],
            ['code' => 'processor', 'name_en' => 'Processor', 'name_ar' => 'المعالج', 'type' => 'text', 'is_variant_attribute' => false, 'is_filterable' => false, 'sort_order' => 14],
            ['code' => 'os', 'name_en' => 'Operating System', 'name_ar' => 'نظام التشغيل', 'type' => 'select', 'is_variant_attribute' => false, 'is_filterable' => true, 'sort_order' => 15],
            ['code' => 'weight', 'name_en' => 'Weight', 'name_ar' => 'الوزن', 'type' => 'number', 'is_variant_attribute' => false, 'is_filterable' => false, 'unit' => 'kg', 'sort_order' => 16],
            ['code' => 'gender', 'name_en' => 'Gender', 'name_ar' => 'الجنس', 'type' => 'select', 'is_variant_attribute' => false, 'is_filterable' => true, 'sort_order' => 17],
            ['code' => 'fragrance', 'name_en' => 'Fragrance', 'name_ar' => 'العطر', 'type' => 'text', 'is_variant_attribute' => false, 'is_filterable' => false, 'sort_order' => 18],
            ['code' => 'voltage', 'name_en' => 'Voltage', 'name_ar' => 'الجهد', 'type' => 'select', 'is_variant_attribute' => false, 'is_filterable' => false, 'unit' => 'V', 'sort_order' => 19],
            ['code' => 'warranty', 'name_en' => 'Warranty', 'name_ar' => 'الضمان', 'type' => 'select', 'is_variant_attribute' => false, 'is_filterable' => true, 'sort_order' => 20],
        ];

        $attrIds = [];
        foreach ($attributes as $a) {
            $existing = DB::table('attributes')->where('code', $a['code'])->first();
            if ($existing) {
                $attrIds[$a['code']] = $existing->id;
                continue;
            }
            $id = Str::uuid()->toString();
            $attrIds[$a['code']] = $id;
            DB::table('attributes')->insert(array_merge([
                'id' => $id,
                'unit' => null,
                'is_required' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ], $a));
        }

        // ── Attribute Values ────────────────────────────────────────────────
        $values = [
            'color' => [
                ['value_en' => 'Black', 'value_ar' => 'أسود', 'code_hex' => '#000000'],
                ['value_en' => 'White', 'value_ar' => 'أبيض', 'code_hex' => '#FFFFFF'],
                ['value_en' => 'Gold', 'value_ar' => 'ذهبي', 'code_hex' => '#FFD700'],
                ['value_en' => 'Silver', 'value_ar' => 'فضي', 'code_hex' => '#C0C0C0'],
                ['value_en' => 'Blue', 'value_ar' => 'أزرق', 'code_hex' => '#0000FF'],
                ['value_en' => 'Red', 'value_ar' => 'أحمر', 'code_hex' => '#FF0000'],
                ['value_en' => 'Green', 'value_ar' => 'أخضر', 'code_hex' => '#008000'],
                ['value_en' => 'Pink', 'value_ar' => 'وردي', 'code_hex' => '#FFC0CB'],
                ['value_en' => 'Purple', 'value_ar' => 'بنفسجي', 'code_hex' => '#800080'],
                ['value_en' => 'Yellow', 'value_ar' => 'أصفر', 'code_hex' => '#FFFF00'],
                ['value_en' => 'Orange', 'value_ar' => 'برتقالي', 'code_hex' => '#FFA500'],
                ['value_en' => 'Brown', 'value_ar' => 'بني', 'code_hex' => '#A52A2A'],
            ],
            'size' => [
                ['value_en' => 'XS', 'value_ar' => 'XS'],
                ['value_en' => 'S', 'value_ar' => 'S'],
                ['value_en' => 'M', 'value_ar' => 'M'],
                ['value_en' => 'L', 'value_ar' => 'L'],
                ['value_en' => 'XL', 'value_ar' => 'XL'],
                ['value_en' => 'XXL', 'value_ar' => 'XXL'],
                ['value_en' => '36', 'value_ar' => '36'],
                ['value_en' => '37', 'value_ar' => '37'],
                ['value_en' => '38', 'value_ar' => '38'],
                ['value_en' => '39', 'value_ar' => '39'],
                ['value_en' => '40', 'value_ar' => '40'],
                ['value_en' => '41', 'value_ar' => '41'],
                ['value_en' => '42', 'value_ar' => '42'],
                ['value_en' => '43', 'value_ar' => '43'],
                ['value_en' => '44', 'value_ar' => '44'],
            ],
            'storage' => [
                ['value_en' => '64GB', 'value_ar' => '64 جيجا'],
                ['value_en' => '128GB', 'value_ar' => '128 جيجا'],
                ['value_en' => '256GB', 'value_ar' => '256 جيجا'],
                ['value_en' => '512GB', 'value_ar' => '512 جيجا'],
                ['value_en' => '1TB', 'value_ar' => '1 تيرابايت'],
            ],
            'ram' => [
                ['value_en' => '4GB', 'value_ar' => '4 جيجا'],
                ['value_en' => '6GB', 'value_ar' => '6 جيجا'],
                ['value_en' => '8GB', 'value_ar' => '8 جيجا'],
                ['value_en' => '12GB', 'value_ar' => '12 جيجا'],
                ['value_en' => '16GB', 'value_ar' => '16 جيجا'],
            ],
            'material' => [
                ['value_en' => 'Cotton', 'value_ar' => 'قطن'],
                ['value_en' => 'Polyester', 'value_ar' => 'بوليستر'],
                ['value_en' => 'Leather', 'value_ar' => 'جلد'],
                ['value_en' => 'Stainless Steel', 'value_ar' => 'فولاذ مقاوم للصدأ'],
                ['value_en' => 'Silicone', 'value_ar' => 'سيليكون'],
                ['value_en' => 'Wood', 'value_ar' => 'خشب'],
                ['value_en' => 'Plastic', 'value_ar' => 'بلاستيك'],
                ['value_en' => 'Aluminum', 'value_ar' => 'ألمنيوم'],
                ['value_en' => 'Glass', 'value_ar' => 'زجاج'],
            ],
            'os' => [
                ['value_en' => 'iOS', 'value_ar' => 'iOS'],
                ['value_en' => 'Android', 'value_ar' => 'أندرويد'],
                ['value_en' => 'Windows', 'value_ar' => 'ويندوز'],
                ['value_en' => 'macOS', 'value_ar' => 'ماك'],
                ['value_en' => 'Linux', 'value_ar' => 'لينكس'],
            ],
            'gender' => [
                ['value_en' => 'Men', 'value_ar' => 'رجال'],
                ['value_en' => 'Women', 'value_ar' => 'نساء'],
                ['value_en' => 'Unisex', 'value_ar' => 'للجنسين'],
                ['value_en' => 'Kids', 'value_ar' => 'أطفال'],
            ],
            'voltage' => [
                ['value_en' => '110V', 'value_ar' => '110 فولت'],
                ['value_en' => '220V', 'value_ar' => '220 فولت'],
                ['value_en' => '110-240V', 'value_ar' => '110-240 فولت'],
            ],
            'warranty' => [
                ['value_en' => 'No warranty', 'value_ar' => 'بدون ضمان'],
                ['value_en' => '6 months', 'value_ar' => '6 أشهر'],
                ['value_en' => '1 year', 'value_ar' => 'سنة واحدة'],
                ['value_en' => '2 years', 'value_ar' => 'سنتان'],
                ['value_en' => '3 years', 'value_ar' => '3 سنوات'],
            ],
        ];

        $attrValueIds = []; // [attr_code][value_en] => id
        foreach ($values as $attrCode => $vals) {
            $attrId = $attrIds[$attrCode] ?? null;
            if (!$attrId)
                continue;
            $attrValueIds[$attrCode] = [];
            foreach ($vals as $i => $v) {
                $existing = DB::table('attribute_values')
                    ->where('attribute_id', $attrId)
                    ->where('value_en', $v['value_en'])
                    ->first();
                if ($existing) {
                    $attrValueIds[$attrCode][$v['value_en']] = $existing->id;
                    continue;
                }
                $vid = Str::uuid()->toString();
                $attrValueIds[$attrCode][$v['value_en']] = $vid;
                DB::table('attribute_values')->insert([
                    'id' => $vid,
                    'attribute_id' => $attrId,
                    'value_en' => $v['value_en'],
                    'value_ar' => $v['value_ar'],
                    'code_hex' => $v['code_hex'] ?? null,
                    'sort_order' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        cache()->put('seeder_attr_ids', $attrIds, 600);
        cache()->put('seeder_attr_value_ids', $attrValueIds, 600);

        // ── Categories (full tree) ──────────────────────────────────────────
        // Format: [code, name_en, name_ar, commission, children[]]
        $tree = [
            [
                'electronics',
                'Electronics',
                'الإلكترونيات',
                8.00,
                [
                    ['smartphones', 'Smartphones', 'هواتف ذكية', 8.00, []],
                    ['laptops', 'Laptops', 'حاسوب محمول', 8.00, []],
                    ['tablets', 'Tablets', 'أجهزة لوحية', 8.00, []],
                    ['headphones', 'Headphones & Audio', 'سماعات وصوتيات', 8.00, []],
                    ['cameras', 'Cameras', 'كاميرات', 8.00, []],
                    ['smart_home', 'Smart Home', 'المنزل الذكي', 8.00, []],
                    ['gaming', 'Gaming', 'ألعاب الفيديو', 8.00, []],
                    ['accessories_el', 'Accessories', 'إكسسوارات', 8.00, []],
                ]
            ],
            [
                'fashion',
                'Fashion',
                'الأزياء',
                12.00,
                [
                    ['mens_clothing', "Men's Clothing", 'ملابس رجالية', 12.00, []],
                    ['womens_clothing', "Women's Clothing", 'ملابس نسائية', 12.00, []],
                    ['kids_clothing', "Kids' Clothing", 'ملابس أطفال', 12.00, []],
                    ['mens_shoes', "Men's Shoes", 'أحذية رجالية', 12.00, []],
                    ['womens_shoes', "Women's Shoes", 'أحذية نسائية', 12.00, []],
                    ['bags', 'Bags & Luggage', 'حقائب وأمتعة', 12.00, []],
                    ['jewellery', 'Jewellery & Watches', 'مجوهرات وساعات', 15.00, []],
                    ['sunglasses', 'Sunglasses', 'نظارات شمسية', 12.00, []],
                ]
            ],
            [
                'home',
                'Home & Living',
                'المنزل والمعيشة',
                10.00,
                [
                    ['furniture', 'Furniture', 'أثاث', 10.00, []],
                    ['kitchen', 'Kitchen & Dining', 'المطبخ وأدوات الطعام', 10.00, []],
                    ['bedding', 'Bedding & Bath', 'مستلزمات الغرفة والحمام', 10.00, []],
                    ['lighting', 'Lighting', 'الإضاءة', 10.00, []],
                    ['decor', 'Home Décor', 'ديكور المنزل', 10.00, []],
                    ['garden', 'Garden & Outdoor', 'الحديقة والهواء الطلق', 10.00, []],
                ]
            ],
            [
                'beauty',
                'Beauty & Personal Care',
                'الجمال والعناية الشخصية',
                15.00,
                [
                    ['skincare', 'Skincare', 'العناية بالبشرة', 15.00, []],
                    ['haircare', 'Hair Care', 'العناية بالشعر', 15.00, []],
                    ['makeup', 'Makeup', 'مستحضرات التجميل', 15.00, []],
                    ['fragrances', 'Fragrances', 'العطور', 15.00, []],
                    ['mens_grooming', "Men's Grooming", 'العناية بالرجل', 15.00, []],
                ]
            ],
            [
                'sports',
                'Sports & Outdoors',
                'الرياضة والهواء الطلق',
                10.00,
                [
                    ['fitness', 'Fitness Equipment', 'معدات اللياقة', 10.00, []],
                    ['outdoor_sports', 'Outdoor Sports', 'الرياضات الخارجية', 10.00, []],
                    ['sportswear', 'Sportswear', 'ملابس رياضية', 10.00, []],
                    ['swimming', 'Swimming', 'السباحة', 10.00, []],
                ]
            ],
            [
                'toys',
                'Toys & Games',
                'الألعاب',
                12.00,
                [
                    ['kids_toys', "Kids' Toys", 'ألعاب الأطفال', 12.00, []],
                    ['board_games', 'Board Games', 'ألعاب الطاولة', 12.00, []],
                    ['educational', 'Educational', 'تعليمية', 12.00, []],
                ]
            ],
            [
                'grocery',
                'Grocery & Food',
                'البقالة والطعام',
                5.00,
                [
                    ['food_staples', 'Food Staples', 'أساسيات الغذاء', 5.00, []],
                    ['beverages', 'Beverages', 'المشروبات', 5.00, []],
                    ['snacks', 'Snacks & Sweets', 'الوجبات الخفيفة والحلويات', 5.00, []],
                    ['organic', 'Organic & Natural', 'عضوي وطبيعي', 5.00, []],
                ]
            ],
            [
                'automotive',
                'Automotive',
                'السيارات',
                8.00,
                [
                    ['car_accessories', 'Car Accessories', 'إكسسوارات السيارات', 8.00, []],
                    ['car_care', 'Car Care', 'العناية بالسيارة', 8.00, []],
                    ['parts', 'Parts & Tools', 'قطع الغيار والأدوات', 8.00, []],
                ]
            ],
            [
                'books',
                'Books & Stationery',
                'الكتب والقرطاسية',
                5.00,
                [
                    ['arabic_books', 'Arabic Books', 'كتب عربية', 5.00, []],
                    ['english_books', 'English Books', 'كتب إنجليزية', 5.00, []],
                    ['stationery', 'Stationery', 'القرطاسية', 5.00, []],
                ]
            ],
            [
                'health',
                'Health & Wellness',
                'الصحة والعافية',
                10.00,
                [
                    ['vitamins', 'Vitamins & Supplements', 'الفيتامينات والمكملات', 10.00, []],
                    ['medical_devices', 'Medical Devices', 'الأجهزة الطبية', 10.00, []],
                    ['baby_care', 'Baby Care', 'العناية بالطفل', 10.00, []],
                ]
            ],
        ];

        $catIds = [];

        $insertCategory = function (array $node, ?string $parentId, int $depth) use (&$insertCategory, &$catIds): void {
            [$code, $nameEn, $nameAr, $commission, $children] = $node;
            $slug = $code;

            $existing = DB::table('categories')->where('slug', $slug)->first();
            if ($existing) {
                $catIds[$code] = $existing->id;
            } else {
                $id = Str::uuid()->toString();
                $catIds[$code] = $id;
                DB::table('categories')->insert([
                    'id' => $id,
                    'parent_id' => $parentId,
                    'name_en' => $nameEn,
                    'name_ar' => $nameAr,
                    'slug' => $slug,
                    'commission_rate' => $commission,
                    'depth' => $depth,
                    'sort_order' => 0,
                    'is_active' => true,
                    'is_visible' => true,
                    'is_featured' => $depth === 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($children as $child) {
                $insertCategory($child, $catIds[$code], $depth + 1);
            }
        };

        foreach ($tree as $node) {
            $insertCategory($node, null, 0);
        }

        \App\Models\Category::fixTree();

        cache()->put('seeder_cat_ids', $catIds, 600);

        // ── Category ↔ Attribute links ──────────────────────────────────────
        $catAttrMap = [
            'smartphones' => ['color', 'storage', 'ram', 'os', 'screen_size', 'battery', 'processor', 'warranty'],
            'laptops' => ['color', 'storage', 'ram', 'os', 'screen_size', 'processor', 'warranty'],
            'tablets' => ['color', 'storage', 'ram', 'os', 'screen_size', 'battery', 'warranty'],
            'headphones' => ['color', 'material', 'warranty'],
            'cameras' => ['color', 'warranty'],
            'gaming' => ['color', 'os', 'storage', 'warranty'],
            'accessories_el' => ['color', 'material'],
            'mens_clothing' => ['color', 'size', 'material', 'gender'],
            'womens_clothing' => ['color', 'size', 'material', 'gender'],
            'kids_clothing' => ['color', 'size', 'material', 'gender'],
            'mens_shoes' => ['color', 'size', 'material', 'gender'],
            'womens_shoes' => ['color', 'size', 'material', 'gender'],
            'bags' => ['color', 'material'],
            'jewellery' => ['color', 'material'],
            'sunglasses' => ['color', 'material', 'gender'],
            'kitchen' => ['color', 'material', 'voltage', 'warranty'],
            'fitness' => ['color', 'material'],
            'fragrances' => ['gender', 'fragrance'],
            'makeup' => ['color'],
        ];

        foreach ($catAttrMap as $catCode => $attrCodes) {
            $catId = $catIds[$catCode] ?? null;
            if (!$catId)
                continue;
            foreach ($attrCodes as $i => $attrCode) {
                $attrId = $attrIds[$attrCode] ?? null;
                if (!$attrId)
                    continue;
                $exists = DB::table('category_attributes')
                    ->where('category_id', $catId)
                    ->where('attribute_id', $attrId)
                    ->exists();
                if (!$exists) {
                    DB::table('category_attributes')->insert([
                        'id' => Str::uuid(),
                        'category_id' => $catId,
                        'attribute_id' => $attrId,
                        'is_required' => false,
                        'sort_order' => $i,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
