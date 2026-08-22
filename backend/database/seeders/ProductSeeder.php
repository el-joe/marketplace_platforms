<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    // ── Helpers ─────────────────────────────────────────────────────────────

    private array $catIds = [];
    private array $brandIds = [];
    private array $attrIds = [];
    private array $avIds = [];   // [attr_code][value_en] => id
    private array $countryIds = [];
    private array $vendorIds = [];

    private function loadCaches(): void
    {
        $this->catIds = cache('seeder_cat_ids') ?? [];
        $this->brandIds = cache('seeder_brand_ids') ?? [];
        $this->attrIds = cache('seeder_attr_ids') ?? [];
        $this->avIds = cache('seeder_attr_value_ids') ?? [];
        $this->vendorIds = cache('seeder_vendor_ids') ?? [];

        $raw = cache('seeder_country_ids') ?? [];
        if (empty($raw)) {
            foreach (['SA', 'AE', 'EG', 'KW'] as $iso) {
                $r = DB::table('countries')->where('iso_code_2', $iso)->first();
                if ($r)
                    $raw[$iso] = $r->id;
            }
        }
        $this->countryIds = $raw;
    }

    private function catId(string $code): ?string
    {
        return $this->catIds[$code] ?? null;
    }
    private function brandId(string $name): ?string
    {
        return $this->brandIds[$name] ?? null;
    }
    private function avId(string $attr, string $value): ?string
    {
        return $this->avIds[$attr][$value] ?? null;
    }
    private function attrId(string $code): ?string
    {
        return $this->attrIds[$code] ?? null;
    }

    /** Insert a product + variants + attributes + countries + vendor listings. */
    private function insertProduct(array $p): void
    {
        $slug = $p['slug'];
        if (DB::table('products')->where('slug', $slug)->exists())
            return;

        $adminId = DB::table('admins')->first()?->id;
        $productId = Str::uuid()->toString();

        DB::table('products')->insert([
            'id' => $productId,
            'category_id' => $p['category_id'],
            'brand_id' => $p['brand_id'],
            'name_en' => $p['name_en'],
            'name_ar' => $p['name_ar'],
            'slug' => $slug,
            'model_number' => $p['model_number'] ?? null,
            'description_en' => $p['description_en'] ?? null,
            'description_ar' => $p['description_ar'] ?? null,
            'short_desc_en' => $p['short_desc_en'] ?? null,
            'short_desc_ar' => $p['short_desc_ar'] ?? null,
            'status' => 'active',
            'has_variants' => count($p['variants']) > 1,
            'is_featured' => $p['is_featured'] ?? false,
            'is_age_restricted' => false,
            'is_hazardous' => false,
            'total_sold' => rand(50, 10000),
            'view_count' => rand(1000, 100000),
            'seller_count' => count($p['vendor_listings'] ?? []),
            'seo_title_en' => $p['name_en'] . ' - noon',
            'seo_title_ar' => $p['name_ar'] . ' - نون',
            'published_at' => now()->subDays(rand(1, 180)),
            'created_by_admin_id' => $adminId,
            'created_at' => now()->subDays(rand(30, 365)),
            'updated_at' => now(),
        ]);

        // Variants
        $variantIds = [];
        foreach ($p['variants'] as $vi => $v) {
            $variantId = Str::uuid()->toString();
            $variantIds[$vi] = $variantId;
            $sku = strtoupper(substr(md5($productId . $vi), 0, 10));
            DB::table('product_variants')->insert([
                'id' => $variantId,
                'product_id' => $productId,
                'sku' => $sku,
                'variant_name' => $v['name'] ?? null,
                'weight_grams' => $v['weight_grams'] ?? 500,
                'is_default' => $vi === 0,
                'is_active' => true,
                'position' => $vi,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Variant attributes
            foreach ($v['attrs'] ?? [] as $attrCode => $valueName) {
                $attrId = $this->attrId($attrCode);
                $avId = $this->avId($attrCode, $valueName);
                if (!$attrId)
                    continue;
                DB::table('product_variant_attributes')->insert([
                    'id' => Str::uuid(),
                    'product_variant_id' => $variantId,
                    'attribute_id' => $attrId,
                    'attribute_value_id' => $avId,
                    'value_text_en' => $avId ? null : $valueName,
                    'value_text_ar' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Product images (placeholder paths)
        $imageSlug = Str::slug($p['name_en']);
        DB::table('product_images')->insert([
            'id' => Str::uuid(),
            'product_id' => $productId,
            'product_variant_id' => null,
            'path' => "products/{$imageSlug}/main.jpg",
            'disk' => 'public',
            'mime_type' => 'image/jpeg',
            'size_bytes' => rand(50000, 500000),
            'alt_text_en' => $p['name_en'],
            'alt_text_ar' => $p['name_ar'],
            'position' => 0,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Make available in all launched countries
        $launchedIsos = ['SA', 'AE', 'EG', 'KW'];
        foreach ($launchedIsos as $iso) {
            $countryId = $this->countryIds[$iso] ?? null;
            if (!$countryId)
                continue;
            $existsPs = DB::table('product_countries')
                ->where('product_id', $productId)
                ->where('country_id', $countryId)
                ->exists();
            if (!$existsPs) {
                DB::table('product_countries')->insert([
                    'id' => Str::uuid(),
                    'product_id' => $productId,
                    'country_id' => $countryId,
                    'is_available' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Vendor listings
        foreach ($p['vendor_listings'] ?? [] as $listing) {
            $vendorId = $listing['vendor_id'];
            $countryIso = $listing['country_iso'];
            $countryId = $this->countryIds[$countryIso] ?? null;
            if (!$vendorId || !$countryId)
                continue;

            // One listing per variant (use first variant by default)
            $variantId = $variantIds[0] ?? null;
            if (!$variantId)
                continue;

            $existsListing = DB::table('vendor_listings')
                ->where('vendor_id', $vendorId)
                ->where('product_variant_id', $variantId)
                ->where('country_id', $countryId)
                ->exists();

            if (!$existsListing) {
                DB::table('vendor_listings')->insert([
                    'id' => Str::uuid(),
                    'vendor_id' => $vendorId,
                    'product_variant_id' => $variantId,
                    'country_id' => $countryId,
                    'price' => $listing['price'],
                    'cost_price' => (int) round($listing['price'] * 0.65),
                    'currency' => $listing['currency'],
                    'condition' => 'new',
                    'fulfillment_model' => $listing['fulfillment'] ?? 'fbm',
                    'status' => 'active',
                    'low_stock_threshold' => 5,
                    'total_sold' => rand(10, 1000),
                    'rating_avg' => number_format(rand(35, 50) / 10, 2),
                    'rating_count' => rand(10, 2000),
                    'approved_at' => now()->subDays(rand(1, 30)),
                    'created_at' => now()->subDays(rand(1, 60)),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    // ── Main run ─────────────────────────────────────────────────────────────

    public function run(): void
    {
        $this->loadCaches();

        $v = fn(string $slug) => $this->vendorIds[$slug] ?? null;

        // ════════════════════════════════════════════════════════════════════
        //  ELECTRONICS → SMARTPHONES
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => 'iPhone 15 Pro',
            'name_ar' => 'آيفون 15 برو',
            'slug' => 'iphone-15-pro',
            'model_number' => 'A3290',
            'category_id' => $this->catId('smartphones'),
            'brand_id' => $this->brandId('Apple'),
            'is_featured' => true,
            'short_desc_en' => 'Apple iPhone 15 Pro with A17 Pro chip, titanium design and ProRAW camera.',
            'short_desc_ar' => 'آيفون 15 برو بشريحة A17 Pro وتصميم تيتانيوم وكاميرا ProRAW.',
            'description_en' => '<p>The iPhone 15 Pro redefines smartphone excellence with its A17 Pro chip built on 3nm technology, a durable titanium frame, and a 48MP main camera system with ProRAW and ProRes video capabilities. Available in Natural Titanium, Blue Titanium, White Titanium, and Black Titanium.</p>',
            'description_ar' => '<p>يعيد آيفون 15 برو تعريف التميز في الهواتف الذكية بشريحة A17 Pro المبنية على تقنية 3nm، وإطار تيتانيوم متين، ونظام كاميرا 48MP مع إمكانيات ProRAW وفيديو ProRes.</p>',
            'variants' => [
                ['name' => 'Natural Titanium / 128GB', 'weight_grams' => 187, 'attrs' => ['color' => 'Gold', 'storage' => '128GB']],
                ['name' => 'Black Titanium / 256GB', 'weight_grams' => 187, 'attrs' => ['color' => 'Black', 'storage' => '256GB']],
                ['name' => 'White Titanium / 512GB', 'weight_grams' => 187, 'attrs' => ['color' => 'White', 'storage' => '512GB']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('gadget-galaxy'), 'country_iso' => 'SA', 'price' => 499900, 'currency' => 'SAR', 'fulfillment' => 'fbn'],
                ['vendor_id' => $v('techhub-arabia'), 'country_iso' => 'AE', 'price' => 399900, 'currency' => 'AED', 'fulfillment' => 'fbn'],
            ],
        ]);

        $this->insertProduct([
            'name_en' => 'Samsung Galaxy S24 Ultra',
            'name_ar' => 'سامسونج جالاكسي S24 الترا',
            'slug' => 'samsung-galaxy-s24-ultra',
            'model_number' => 'SM-S928B',
            'category_id' => $this->catId('smartphones'),
            'brand_id' => $this->brandId('Samsung'),
            'is_featured' => true,
            'short_desc_en' => 'Samsung Galaxy S24 Ultra with 200MP camera, built-in S Pen and Snapdragon 8 Gen 3.',
            'short_desc_ar' => 'سامسونج جالاكسي S24 الترا بكاميرا 200 ميجابكسل وقلم S Pen مدمج وسنابدراجون 8 الجيل الثالث.',
            'description_en' => '<p>The Galaxy S24 Ultra brings the ultimate Samsung experience with a 200MP camera, integrated S Pen, Snapdragon 8 Gen 3 processor, and 5000mAh battery.</p>',
            'description_ar' => '<p>يجلب جالاكسي S24 الترا التجربة المثلى من سامسونج بكاميرا 200 ميجابكسل وقلم S Pen مدمج.</p>',
            'variants' => [
                ['name' => 'Titanium Black / 256GB', 'weight_grams' => 232, 'attrs' => ['color' => 'Black', 'storage' => '256GB', 'ram' => '12GB']],
                ['name' => 'Titanium Silver / 512GB', 'weight_grams' => 232, 'attrs' => ['color' => 'Silver', 'storage' => '512GB', 'ram' => '12GB']],
                ['name' => 'Titanium Yellow / 1TB', 'weight_grams' => 232, 'attrs' => ['color' => 'Yellow', 'storage' => '1TB', 'ram' => '12GB']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('gadget-galaxy'), 'country_iso' => 'SA', 'price' => 469900, 'currency' => 'SAR'],
                ['vendor_id' => $v('techhub-arabia'), 'country_iso' => 'AE', 'price' => 379900, 'currency' => 'AED'],
            ],
        ]);

        $this->insertProduct([
            'name_en' => 'Xiaomi 14 Ultra',
            'name_ar' => 'شاومي 14 الترا',
            'slug' => 'xiaomi-14-ultra',
            'model_number' => 'Xiaomi 14 Ultra',
            'category_id' => $this->catId('smartphones'),
            'brand_id' => $this->brandId('Xiaomi'),
            'short_desc_en' => 'Xiaomi 14 Ultra with Leica Summilux optics and Snapdragon 8 Gen 3.',
            'short_desc_ar' => 'شاومي 14 الترا مع بصريات لايكا سوميلوكس وسنابدراجون 8 الجيل الثالث.',
            'description_en' => '<p>Powered by Snapdragon 8 Gen 3 with Leica Summilux lenses and a 50MP main sensor.</p>',
            'description_ar' => '<p>مدعوم بسنابدراجون 8 الجيل الثالث مع عدسات لايكا سوميلوكس.</p>',
            'variants' => [
                ['name' => 'Black / 256GB', 'weight_grams' => 220, 'attrs' => ['color' => 'Black', 'storage' => '256GB', 'ram' => '16GB']],
                ['name' => 'White / 512GB', 'weight_grams' => 220, 'attrs' => ['color' => 'White', 'storage' => '512GB', 'ram' => '16GB']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('techhub-arabia'), 'country_iso' => 'AE', 'price' => 289900, 'currency' => 'AED'],
                ['vendor_id' => $v('gadget-galaxy'), 'country_iso' => 'SA', 'price' => 349900, 'currency' => 'SAR'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  ELECTRONICS → LAPTOPS
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => 'MacBook Pro 16" M3 Max',
            'name_ar' => 'ماك بوك برو 16 بوصة M3 ماكس',
            'slug' => 'macbook-pro-16-m3-max',
            'model_number' => 'MRW13',
            'category_id' => $this->catId('laptops'),
            'brand_id' => $this->brandId('Apple'),
            'is_featured' => true,
            'short_desc_en' => 'The most powerful MacBook Pro ever with M3 Max chip, 16" Liquid Retina XDR display.',
            'short_desc_ar' => 'أقوى ماك بوك برو على الإطلاق بشريحة M3 ماكس وشاشة Liquid Retina XDR 16 بوصة.',
            'description_en' => '<p>MacBook Pro with M3 Max delivers exceptional performance for demanding workflows with up to 128GB unified memory.</p>',
            'description_ar' => '<p>ماك بوك برو بشريحة M3 ماكس يقدم أداءً استثنائياً للعمليات المتطلبة مع ذاكرة موحدة تصل إلى 128 جيجابايت.</p>',
            'variants' => [
                ['name' => 'Space Black / 512GB', 'weight_grams' => 2150, 'attrs' => ['color' => 'Black', 'storage' => '512GB', 'ram' => '16GB']],
                ['name' => 'Silver / 1TB', 'weight_grams' => 2150, 'attrs' => ['color' => 'Silver', 'storage' => '1TB', 'ram' => '16GB']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('gadget-galaxy'), 'country_iso' => 'SA', 'price' => 1099900, 'currency' => 'SAR', 'fulfillment' => 'fbn'],
                ['vendor_id' => $v('techhub-arabia'), 'country_iso' => 'AE', 'price' => 899900, 'currency' => 'AED', 'fulfillment' => 'fbn'],
            ],
        ]);

        $this->insertProduct([
            'name_en' => 'Samsung Galaxy Book4 Pro 16"',
            'name_ar' => 'سامسونج جالاكسي بوك 4 برو 16 بوصة',
            'slug' => 'samsung-galaxy-book4-pro-16',
            'category_id' => $this->catId('laptops'),
            'brand_id' => $this->brandId('Samsung'),
            'short_desc_en' => 'Samsung Galaxy Book4 Pro with Intel Core Ultra 7, AMOLED display and AI features.',
            'short_desc_ar' => 'سامسونج جالاكسي بوك 4 برو مع معالج إنتل كور الترا 7 وشاشة AMOLED.',
            'description_en' => '<p>Ultra-thin and light laptop with Intel Core Ultra 7, 16GB LPDDR5 RAM, and Dynamic AMOLED 2X display.</p>',
            'description_ar' => '<p>حاسوب محمول رفيع للغاية مع معالج إنتل كور الترا 7 وذاكرة LPDDR5 16 جيجابايت وشاشة Dynamic AMOLED 2X.</p>',
            'variants' => [
                ['name' => 'Moon Gray / 512GB', 'weight_grams' => 1780, 'attrs' => ['color' => 'Silver', 'storage' => '512GB', 'ram' => '16GB']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('gadget-galaxy'), 'country_iso' => 'SA', 'price' => 549900, 'currency' => 'SAR'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  ELECTRONICS → HEADPHONES
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => 'Sony WH-1000XM5 Wireless Headphones',
            'name_ar' => 'سماعات سوني WH-1000XM5 اللاسلكية',
            'slug' => 'sony-wh-1000xm5',
            'category_id' => $this->catId('headphones'),
            'brand_id' => $this->brandId('Sony'),
            'short_desc_en' => 'Industry-leading noise canceling headphones with 30-hour battery life.',
            'short_desc_ar' => 'سماعات رائدة في صناعة إلغاء الضوضاء مع عمر بطارية 30 ساعة.',
            'description_en' => '<p>Sony WH-1000XM5 features industry-leading noise cancellation powered by two processors and eight microphones for an immersive listening experience.</p>',
            'description_ar' => '<p>تتميز سماعات سوني WH-1000XM5 بإلغاء الضوضاء الرائد في الصناعة.</p>',
            'variants' => [
                ['name' => 'Black', 'weight_grams' => 250, 'attrs' => ['color' => 'Black']],
                ['name' => 'Silver', 'weight_grams' => 250, 'attrs' => ['color' => 'Silver']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('techhub-arabia'), 'country_iso' => 'AE', 'price' => 144900, 'currency' => 'AED'],
                ['vendor_id' => $v('gadget-galaxy'), 'country_iso' => 'SA', 'price' => 179900, 'currency' => 'SAR'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'EG', 'price' => 749900, 'currency' => 'EGP'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  FASHION → MEN'S CLOTHING
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => 'Classic Slim Fit Oxford Shirt',
            'name_ar' => 'قميص أوكسفورد ضيق الملاءمة الكلاسيكي',
            'slug' => 'classic-slim-fit-oxford-shirt',
            'category_id' => $this->catId('mens_clothing'),
            'brand_id' => $this->brandId("Levi's"),
            'short_desc_en' => 'Crisp cotton Oxford shirt with a modern slim fit for everyday wear.',
            'short_desc_ar' => 'قميص أوكسفورد قطني أنيق بقصة ضيقة عصرية للارتداء اليومي.',
            'description_en' => '<p>Made from premium 100% cotton, this Oxford shirt features a button-down collar, chest pocket, and modern slim fit that flatters any build.</p>',
            'description_ar' => '<p>مصنوع من القطن المميز 100%، يتميز هذا القميص بياقة أزرار وجيب صدر وقصة ضيقة عصرية.</p>',
            'variants' => [
                ['name' => 'White / S', 'weight_grams' => 250, 'attrs' => ['color' => 'White', 'size' => 'S', 'material' => 'Cotton', 'gender' => 'Men']],
                ['name' => 'White / M', 'weight_grams' => 250, 'attrs' => ['color' => 'White', 'size' => 'M', 'material' => 'Cotton', 'gender' => 'Men']],
                ['name' => 'White / L', 'weight_grams' => 260, 'attrs' => ['color' => 'White', 'size' => 'L', 'material' => 'Cotton', 'gender' => 'Men']],
                ['name' => 'White / XL', 'weight_grams' => 270, 'attrs' => ['color' => 'White', 'size' => 'XL', 'material' => 'Cotton', 'gender' => 'Men']],
                ['name' => 'Blue / M', 'weight_grams' => 250, 'attrs' => ['color' => 'Blue', 'size' => 'M', 'material' => 'Cotton', 'gender' => 'Men']],
                ['name' => 'Blue / L', 'weight_grams' => 260, 'attrs' => ['color' => 'Blue', 'size' => 'L', 'material' => 'Cotton', 'gender' => 'Men']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'SA', 'price' => 18900, 'currency' => 'SAR'],
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'AE', 'price' => 17900, 'currency' => 'AED'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'EG', 'price' => 89900, 'currency' => 'EGP'],
            ],
        ]);

        $this->insertProduct([
            'name_en' => "Levi's 511 Slim Fit Jeans",
            'name_ar' => "جينز ليفايز 511 ضيق الملاءمة",
            'slug' => 'levis-511-slim-fit-jeans',
            'category_id' => $this->catId('mens_clothing'),
            'brand_id' => $this->brandId("Levi's"),
            'short_desc_en' => "Levi's 511 slim fit jeans in classic stretch denim.",
            'short_desc_ar' => "جينز ليفايز 511 بقصة ضيقة من الدنيم المطاطي الكلاسيكي.",
            'description_en' => '<p>The 511 Slim Fit Jean sits just below the waist and fits through the thigh and knee with a leg opening that is narrower than our straight-fit jeans.</p>',
            'description_ar' => '<p>جينز 511 بقصة ضيقة يجلس أسفل الخصر ويتميز بفتحة ساق أضيق من جينز القصة المستقيمة.</p>',
            'variants' => [
                ['name' => 'Dark Indigo / 32×32', 'weight_grams' => 650, 'attrs' => ['color' => 'Blue', 'size' => '32', 'material' => 'Cotton', 'gender' => 'Men']],
                ['name' => 'Dark Indigo / 34×32', 'weight_grams' => 680, 'attrs' => ['color' => 'Blue', 'size' => '34', 'material' => 'Cotton', 'gender' => 'Men']],
                ['name' => 'Black / 32×32', 'weight_grams' => 650, 'attrs' => ['color' => 'Black', 'size' => '32', 'material' => 'Cotton', 'gender' => 'Men']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'SA', 'price' => 28900, 'currency' => 'SAR'],
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'AE', 'price' => 24900, 'currency' => 'AED'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  FASHION → WOMEN'S CLOTHING
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => "Women's Floral Maxi Dress",
            'name_ar' => "فستان ماكسي نسائي بنقشة زهور",
            'slug' => 'womens-floral-maxi-dress',
            'category_id' => $this->catId('womens_clothing'),
            'brand_id' => $this->brandId('Zara'),
            'short_desc_en' => 'Elegant floral maxi dress perfect for summer occasions.',
            'short_desc_ar' => 'فستان ماكسي أنيق بنقشة زهور مثالي لمناسبات الصيف.',
            'description_en' => '<p>This beautiful floral maxi dress is crafted from lightweight, breathable fabric perfect for warm weather. Features a V-neck and adjustable straps.</p>',
            'description_ar' => '<p>هذا الفستان الماكسي الجميل بنقشة الزهور مصنوع من قماش خفيف الوزن وقابل للتنفس مثالي للطقس الدافئ.</p>',
            'variants' => [
                ['name' => 'Multicolor / XS', 'weight_grams' => 350, 'attrs' => ['color' => 'Pink', 'size' => 'XS', 'gender' => 'Women']],
                ['name' => 'Multicolor / S', 'weight_grams' => 360, 'attrs' => ['color' => 'Pink', 'size' => 'S', 'gender' => 'Women']],
                ['name' => 'Multicolor / M', 'weight_grams' => 370, 'attrs' => ['color' => 'Pink', 'size' => 'M', 'gender' => 'Women']],
                ['name' => 'Multicolor / L', 'weight_grams' => 390, 'attrs' => ['color' => 'Pink', 'size' => 'L', 'gender' => 'Women']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'SA', 'price' => 24900, 'currency' => 'SAR'],
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'AE', 'price' => 22900, 'currency' => 'AED'],
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'EG', 'price' => 99900, 'currency' => 'EGP'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  FASHION → MEN'S SHOES
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => 'Nike Air Max 270',
            'name_ar' => 'نايك اير ماكس 270',
            'slug' => 'nike-air-max-270',
            'category_id' => $this->catId('mens_shoes'),
            'brand_id' => $this->brandId('Nike'),
            'is_featured' => true,
            'short_desc_en' => 'Nike Air Max 270 with the biggest Air unit yet for all-day comfort.',
            'short_desc_ar' => 'نايك اير ماكس 270 بأكبر وحدة هواء حتى الآن لراحة طوال اليوم.',
            'description_en' => '<p>The Nike Air Max 270 delivers lifestyle innovation with a full-length Air unit in the heel for incredible underfoot cushioning.</p>',
            'description_ar' => '<p>توفر نايك اير ماكس 270 ابتكار أسلوب الحياة مع وحدة هواء كاملة الطول في الكعب لتوسيد لا مثيل له.</p>',
            'variants' => [
                ['name' => 'Black / 40', 'weight_grams' => 500, 'attrs' => ['color' => 'Black', 'size' => '40', 'gender' => 'Men']],
                ['name' => 'Black / 41', 'weight_grams' => 510, 'attrs' => ['color' => 'Black', 'size' => '41', 'gender' => 'Men']],
                ['name' => 'Black / 42', 'weight_grams' => 520, 'attrs' => ['color' => 'Black', 'size' => '42', 'gender' => 'Men']],
                ['name' => 'Black / 43', 'weight_grams' => 530, 'attrs' => ['color' => 'Black', 'size' => '43', 'gender' => 'Men']],
                ['name' => 'White / 41', 'weight_grams' => 510, 'attrs' => ['color' => 'White', 'size' => '41', 'gender' => 'Men']],
                ['name' => 'White / 42', 'weight_grams' => 520, 'attrs' => ['color' => 'White', 'size' => '42', 'gender' => 'Men']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'SA', 'price' => 55900, 'currency' => 'SAR'],
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'AE', 'price' => 49900, 'currency' => 'AED'],
                ['vendor_id' => $v('sports-zone'), 'country_iso' => 'SA', 'price' => 57900, 'currency' => 'SAR'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  HOME & LIVING → KITCHEN
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => 'Tefal Easy Fry Air Fryer 4.2L',
            'name_ar' => 'طاجن هواء تيفال إيزي فراي 4.2 لتر',
            'slug' => 'tefal-easy-fry-air-fryer-4l',
            'category_id' => $this->catId('kitchen'),
            'brand_id' => $this->brandId('Tefal'),
            'short_desc_en' => 'Tefal Easy Fry air fryer with 4.2L capacity for crispy results using up to 99% less oil.',
            'short_desc_ar' => 'قلاية هواء تيفال إيزي فراي بسعة 4.2 لتر لنتائج مقرمشة باستخدام زيت أقل بنسبة 99%.',
            'description_en' => '<p>Cook healthier with the Tefal Easy Fry. Uses up to 99% less oil than traditional frying with its patented technology.</p>',
            'description_ar' => '<p>اطبخ بشكل أكثر صحة مع تيفال إيزي فراي. تستخدم زيتاً أقل بنسبة 99% من القلي التقليدي.</p>',
            'variants' => [
                ['name' => 'Black', 'weight_grams' => 3200, 'attrs' => ['color' => 'Black', 'voltage' => '220V']],
                ['name' => 'White', 'weight_grams' => 3200, 'attrs' => ['color' => 'White', 'voltage' => '220V']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'EG', 'price' => 249900, 'currency' => 'EGP'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'SA', 'price' => 35900, 'currency' => 'SAR'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'AE', 'price' => 29900, 'currency' => 'AED'],
            ],
        ]);

        $this->insertProduct([
            'name_en' => 'Philips Espresso Machine Series 3300',
            'name_ar' => 'ماكينة إسبريسو فيليبس سلسلة 3300',
            'slug' => 'philips-espresso-series-3300',
            'category_id' => $this->catId('kitchen'),
            'brand_id' => $this->brandId('Philips'),
            'short_desc_en' => 'Fully automatic espresso machine with LatteGo milk system and intuitive touch display.',
            'short_desc_ar' => 'ماكينة إسبريسو أوتوماتيكية بالكامل مع نظام LatteGo للحليب وشاشة لمسية.',
            'description_en' => '<p>The Philips Series 3300 fully automatic espresso machine features the unique LatteGo milk system and a wide range of specialty coffees.</p>',
            'description_ar' => '<p>تتميز ماكينة فيليبس سلسلة 3300 بنظام LatteGo الفريد للحليب ومجموعة واسعة من قهوة التخصص.</p>',
            'variants' => [
                ['name' => 'Black', 'weight_grams' => 7800, 'attrs' => ['color' => 'Black', 'voltage' => '220V']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'SA', 'price' => 199900, 'currency' => 'SAR'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'AE', 'price' => 179900, 'currency' => 'AED'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  BEAUTY → SKINCARE
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => "Nivea Men's Energy Face Wash",
            'name_ar' => "غسول وجه نيفيا للرجال طاقة",
            'slug' => 'nivea-mens-energy-face-wash',
            'category_id' => $this->catId('skincare'),
            'brand_id' => $this->brandId('Nivea'),
            'short_desc_en' => "Invigorating face wash for men with mint extract for a refreshed feeling.",
            'short_desc_ar' => "غسول وجه منعش للرجال بمستخلص النعناع للشعور بالانتعاش.",
            'description_en' => '<p>Start your day energized with Nivea Men Energy Face Wash. Formulated with mint extract to deeply cleanse and invigorate your skin.</p>',
            'description_ar' => '<p>ابدأ يومك بنشاط مع غسول وجه نيفيا للرجال طاقة. مُصاغ بمستخلص النعناع لتنظيف بشرتك بعمق.</p>',
            'variants' => [
                ['name' => '100ml', 'weight_grams' => 150, 'attrs' => ['gender' => 'Men']],
                ['name' => '200ml', 'weight_grams' => 280, 'attrs' => ['gender' => 'Men']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('beauty-corner'), 'country_iso' => 'KW', 'price' => 2900, 'currency' => 'KWD'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'EG', 'price' => 15900, 'currency' => 'EGP'],
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'SA', 'price' => 1900, 'currency' => 'SAR'],
            ],
        ]);

        $this->insertProduct([
            'name_en' => "L'Oréal Paris Revitalift Serum",
            'name_ar' => "سيروم لوريال باريس ريفيتاليفت",
            'slug' => 'loreal-revitalift-serum',
            'category_id' => $this->catId('skincare'),
            'brand_id' => $this->brandId("L'Oréal"),
            'short_desc_en' => "Anti-aging serum with pure Vitamin C and Hyaluronic Acid for radiant skin.",
            'short_desc_ar' => "سيروم مضاد للشيخوخة بفيتامين C النقي وحمض الهيالورونيك للبشرة المشرقة.",
            'description_en' => '<p>Revitalift 1.5% Pure Vitamin C Serum with Hyaluronic Acid visibly brightens and reduces wrinkles in just 1 week.</p>',
            'description_ar' => '<p>سيروم ريفيتاليفت بتركيز 1.5% فيتامين C النقي مع حمض الهيالورونيك يضيء بشرتك بشكل ملحوظ ويقلل التجاعيد في أسبوع واحد فقط.</p>',
            'variants' => [
                ['name' => '30ml', 'weight_grams' => 120, 'attrs' => ['gender' => 'Women']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('beauty-corner'), 'country_iso' => 'KW', 'price' => 5900, 'currency' => 'KWD'],
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'SA', 'price' => 8900, 'currency' => 'SAR'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'EG', 'price' => 39900, 'currency' => 'EGP'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  SPORTS & OUTDOORS → FITNESS
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => 'Adidas Ultraboost 23 Running Shoes',
            'name_ar' => 'حذاء أديداس الترابوست 23 للجري',
            'slug' => 'adidas-ultraboost-23',
            'category_id' => $this->catId('sportswear'),
            'brand_id' => $this->brandId('Adidas'),
            'is_featured' => true,
            'short_desc_en' => 'Adidas Ultraboost 23 with responsive BOOST midsole and Primeknit+ upper.',
            'short_desc_ar' => 'حذاء أديداس الترابوست 23 مع نعل BOOST الوسطي المتجاوب وجزء علوي Primeknit+.',
            'description_en' => '<p>Every step in these running shoes returns energy to your stride with BOOST technology and a supportive Primeknit+ upper that adapts to your foot.</p>',
            'description_ar' => '<p>كل خطوة في هذه الأحذية الجري تعيد الطاقة إلى خطاك مع تقنية BOOST وجزء علوي Primeknit+ داعم يتكيف مع قدمك.</p>',
            'variants' => [
                ['name' => 'Core Black / 41', 'weight_grams' => 480, 'attrs' => ['color' => 'Black', 'size' => '41', 'gender' => 'Men']],
                ['name' => 'Core Black / 42', 'weight_grams' => 490, 'attrs' => ['color' => 'Black', 'size' => '42', 'gender' => 'Men']],
                ['name' => 'Core Black / 43', 'weight_grams' => 500, 'attrs' => ['color' => 'Black', 'size' => '43', 'gender' => 'Men']],
                ['name' => 'Cloud White / 41', 'weight_grams' => 480, 'attrs' => ['color' => 'White', 'size' => '41', 'gender' => 'Men']],
                ['name' => 'Cloud White / 42', 'weight_grams' => 490, 'attrs' => ['color' => 'White', 'size' => '42', 'gender' => 'Men']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('sports-zone'), 'country_iso' => 'SA', 'price' => 65900, 'currency' => 'SAR'],
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'AE', 'price' => 59900, 'currency' => 'AED'],
            ],
        ]);

        $this->insertProduct([
            'name_en' => 'Dyson V15 Detect Absolute',
            'name_ar' => 'دايسون V15 ديتكت أبسوليوت',
            'slug' => 'dyson-v15-detect-absolute',
            'category_id' => $this->catId('home'),
            'brand_id' => $this->brandId('Dyson'),
            'is_featured' => true,
            'short_desc_en' => 'Dyson V15 Detect cordless vacuum with laser technology to reveal invisible dust.',
            'short_desc_ar' => 'مكنسة دايسون V15 ديتكت اللاسلكية بتقنية الليزر للكشف عن الغبار غير المرئي.',
            'description_en' => '<p>The Dyson V15 Detect automatically adapts suction to the floor type. Its laser technology reveals microscopic dust invisible to the human eye.</p>',
            'description_ar' => '<p>تتكيف مكنسة دايسون V15 ديتكت تلقائياً مع نوع الأرضية. تقنية الليزر الخاصة بها تكشف عن الغبار المجهري.</p>',
            'variants' => [
                ['name' => 'Gold / Nickel', 'weight_grams' => 3100, 'attrs' => ['color' => 'Gold', 'voltage' => '220V']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'SA', 'price' => 289900, 'currency' => 'SAR', 'fulfillment' => 'fbn'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'AE', 'price' => 249900, 'currency' => 'AED', 'fulfillment' => 'fbn'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  TOYS → KIDS' TOYS
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => 'LEGO Technic Bugatti Bolide Set',
            'name_ar' => 'مجموعة ليغو تكنيك بوغاتي بوليد',
            'slug' => 'lego-technic-bugatti-bolide',
            'category_id' => $this->catId('kids_toys'),
            'brand_id' => $this->brandId('Noon Brand'),
            'short_desc_en' => 'LEGO Technic Bugatti Bolide with 905 pieces for an authentic hypercar building experience.',
            'short_desc_ar' => 'مجموعة ليغو تكنيك بوغاتي بوليد بـ 905 قطعة لتجربة بناء سيارة فائقة السرعة أصيلة.',
            'description_en' => '<p>Build an incredible replica of the Bugatti Bolide hypercar with this LEGO Technic set, featuring 905 pieces including a distinctive W16 engine.</p>',
            'description_ar' => '<p>ابنِ نسخة رائعة من سيارة بوغاتي بوليد الفائقة مع مجموعة ليغو تكنيك هذه، بـ 905 قطعة تشمل محرك W16 المميز.</p>',
            'variants' => [
                ['name' => 'Standard', 'weight_grams' => 850, 'attrs' => []],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('kids-world'), 'country_iso' => 'AE', 'price' => 19900, 'currency' => 'AED'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'EG', 'price' => 89900, 'currency' => 'EGP'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  HEALTH → VITAMINS
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => 'Vitamin D3 + K2 Supplement 5000 IU',
            'name_ar' => 'مكمل فيتامين D3 + K2 5000 وحدة دولية',
            'slug' => 'vitamin-d3-k2-5000iu',
            'category_id' => $this->catId('vitamins'),
            'brand_id' => $this->brandId('Noon Brand'),
            'short_desc_en' => 'Premium Vitamin D3 with K2 for strong bones, immune support, and heart health.',
            'short_desc_ar' => 'فيتامين D3 مع K2 لتقوية العظام ودعم المناعة وصحة القلب.',
            'description_en' => '<p>Each capsule provides 5000 IU of Vitamin D3 combined with 100mcg of K2 (MK-7 form) for optimal calcium absorption and cardiovascular health.</p>',
            'description_ar' => '<p>كل كبسولة تحتوي على 5000 وحدة دولية من فيتامين D3 مع 100 ميكروغرام من K2.</p>',
            'variants' => [
                ['name' => '60 capsules', 'weight_grams' => 120, 'attrs' => []],
                ['name' => '120 capsules', 'weight_grams' => 220, 'attrs' => []],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('beauty-corner'), 'country_iso' => 'KW', 'price' => 8900, 'currency' => 'KWD'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'EG', 'price' => 29900, 'currency' => 'EGP'],
                ['vendor_id' => $v('fashion-palace'), 'country_iso' => 'SA', 'price' => 4900, 'currency' => 'SAR'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  AUTOMOTIVE → CAR ACCESSORIES
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => 'Bosch S5 Car Battery 74Ah',
            'name_ar' => 'بطارية سيارة بوش S5 74 أمبير',
            'slug' => 'bosch-s5-car-battery-74ah',
            'category_id' => $this->catId('car_accessories'),
            'brand_id' => $this->brandId('Bosch'),
            'short_desc_en' => 'Bosch S5 premium car battery 74Ah for reliable starts in all conditions.',
            'short_desc_ar' => 'بطارية سيارة بوش S5 المتميزة 74 أمبير لبدء تشغيل موثوق في جميع الظروف.',
            'description_en' => '<p>The Bosch S5 battery offers superior starting power and excellent cycling stability. Ideal for modern vehicles with multiple electronic systems.</p>',
            'description_ar' => '<p>تقدم بطارية بوش S5 قدرة بدء تشغيل فائقة واستقرار دوري ممتاز. مثالية للمركبات الحديثة ذات الأنظمة الإلكترونية المتعددة.</p>',
            'variants' => [
                ['name' => '74Ah', 'weight_grams' => 17500, 'attrs' => ['voltage' => '220V']],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'SA', 'price' => 38900, 'currency' => 'SAR'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'AE', 'price' => 34900, 'currency' => 'AED'],
            ],
        ]);

        // ════════════════════════════════════════════════════════════════════
        //  GROCERY → BEVERAGES
        // ════════════════════════════════════════════════════════════════════
        $this->insertProduct([
            'name_en' => 'Nescafé Gold Blend Instant Coffee 200g',
            'name_ar' => 'قهوة نسكافيه جولد بلند فورية 200 جم',
            'slug' => 'nescafe-gold-blend-200g',
            'category_id' => $this->catId('beverages'),
            'brand_id' => $this->brandId('Noon Brand'),
            'short_desc_en' => 'Nescafé Gold Blend rich instant coffee with a smooth, balanced taste.',
            'short_desc_ar' => 'قهوة نسكافيه جولد بلند الفورية الغنية بطعم ناعم ومتوازن.',
            'description_en' => '<p>Nescafé Gold Blend is a premium instant coffee made from a blend of Arabica and Robusta beans, expertly roasted for a rich, smooth taste.</p>',
            'description_ar' => '<p>نسكافيه جولد بلند هي قهوة فورية ممتازة مصنوعة من مزيج حبوب أرابيكا وروبوستا.</p>',
            'variants' => [
                ['name' => '200g', 'weight_grams' => 220, 'attrs' => []],
                ['name' => '400g', 'weight_grams' => 420, 'attrs' => []],
            ],
            'vendor_listings' => [
                ['vendor_id' => $v('organic-bazaar'), 'country_iso' => 'EG', 'price' => 14900, 'currency' => 'EGP'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'SA', 'price' => 2900, 'currency' => 'SAR'],
                ['vendor_id' => $v('home-essentials'), 'country_iso' => 'AE', 'price' => 2500, 'currency' => 'AED'],
            ],
        ]);
    }
}
