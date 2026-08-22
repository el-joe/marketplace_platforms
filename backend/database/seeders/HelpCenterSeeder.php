<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Country;
use App\Models\HelpCenterArticle;
use App\Models\HelpCenterCategory;
use Illuminate\Database\Seeder;

class HelpCenterSeeder extends Seeder
{
    public function run(): void
    {
        $author = Admin::first();
        $ae = Country::find('AE') ?? Country::where('iso_code_2', 'AE')->first();

        $categories = [
            ['slug' => 'getting-started', 'name' => 'Getting Started', 'description' => 'Everything you need to start selling on noon — account setup, listing your first product, and understanding the seller portal.', 'icon' => null, 'sort_order' => 1],
            ['slug' => 'fulfilled-by-noon-fbn', 'name' => 'Fulfilled by noon (FBN)', 'description' => 'Learn how noon stores, picks, packs and ships your products for you, and how FBN fees are calculated.', 'icon' => null, 'sort_order' => 2],
            ['slug' => 'orders-shipping', 'name' => 'Orders & Shipping', 'description' => 'Manage orders, print shipping labels, and track deliveries.', 'icon' => null, 'sort_order' => 3],
            ['slug' => 'returns', 'name' => 'Returns', 'description' => 'How customer returns work and how to process them.', 'icon' => null, 'sort_order' => 4],
            ['slug' => 'finance-and-payments', 'name' => 'Finance & Payments', 'description' => 'Statements, payouts, invoices and everything related to getting paid.', 'icon' => null, 'sort_order' => 5],
            ['slug' => 'listings-and-catalog', 'name' => 'Listings & Catalog', 'description' => 'Creating and managing your product catalog.', 'icon' => null, 'sort_order' => 6],
            ['slug' => 'account-management', 'name' => 'Account Management', 'description' => 'Manage your seller account settings, users and permissions.', 'icon' => null, 'sort_order' => 7],
            ['slug' => 'faqs', 'name' => 'FAQs', 'description' => 'Answers to the most common seller questions.', 'icon' => null, 'sort_order' => 8],
        ];

        $created = [];
        foreach ($categories as $data) {
            $created[$data['slug']] = HelpCenterCategory::firstOrCreate(
                ['slug' => $data['slug']],
                $data + ['is_active' => true]
            );
        }

        $fbnFeesSub = HelpCenterCategory::firstOrCreate(
            ['slug' => 'fbn-fees'],
            [
                'parent_id' => $created['fulfilled-by-noon-fbn']->id,
                'name' => 'FBN Fees',
                'description' => null,
                'icon' => null,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        // ── Full real article: FBN Fees in UAE ──────────────────────────────
        HelpCenterArticle::firstOrCreate(
            ['slug' => 'fulfilled-by-noon-fbn-fees-in-uae'],
            [
                'help_center_category_id' => $fbnFeesSub->id,
                'author_admin_id' => $author?->id,
                'country_id' => $ae?->site_code,
                'title' => 'رسوم الاستيفاء بواسطة نون (FBN) في الإمارات العربية المتحدة',
                'excerpt' => 'تفاصيل كافة الرسوم المطبقة على العناصر التي يتم شحنها بواسطة نون (FBN) في دولة الإمارات العربية المتحدة، اعتباراً من 1 سبتمبر 2025.',
                'body' => $this->fbnFeesUaeBody(),
                'status' => 'published',
                'published_at' => now()->subMonths(3),
                'is_featured' => true,
                'views_count' => 2140,
            ]
        );

        // ── Related FBN stub articles ────────────────────────────────────────
        $this->stub($fbnFeesSub->id, $author?->id, $ae?->site_code, 'understanding-seller-tiers-and-inbound-performance', 'دليل فهم مستويات البائعين وأداء الشحنات الواردة');
        $this->stub($fbnFeesSub->id, $author?->id, $ae?->site_code, 'return-to-vendor-rtv-report', 'تقرير الإرجاع إلى البائع (RTV)');
        $this->stub($fbnFeesSub->id, $author?->id, $ae?->site_code, 'how-can-i-track-my-rtv-shipments-and-notifications', 'كيف يمكنني تتبع شحنات المرتجعات إلى البائع (RTV) والإشعارات الخاصة بها؟');
        $this->stub($created['fulfilled-by-noon-fbn']->id, $author?->id, null, 'how-to-calculate-the-fbn-outbound-fee-and-the-inventory-removal-fee-in-uae', 'كيفية حساب رسوم FBN الصادرة ورسوم إزالة المخزون في الإمارات');
        $this->stub($created['fulfilled-by-noon-fbn']->id, $author?->id, null, 'receiving-methods-for-rtv-delivery', 'طرق استلام المرتجعات');
        $this->stub($created['fulfilled-by-noon-fbn']->id, $author?->id, null, 'partial-rtv-handover-in-noon-hubs', 'عملية تسليم المرتجعات الجزئية في مستودعات نون');

        // ── Getting Started stubs ────────────────────────────────────────────
        $this->stub($created['getting-started']->id, $author?->id, null, 'how-to-create-a-seller-account', 'How to Create a Seller Account');
        $this->stub($created['getting-started']->id, $author?->id, null, 'how-to-list-your-first-product', 'How to List Your First Product');

        // ── Orders stubs ──────────────────────────────────────────────────────
        $this->stub($created['orders-shipping']->id, $author?->id, null, 'how-to-process-an-order', 'How to Process an Order');
        $this->stub($created['orders-shipping']->id, $author?->id, null, 'how-to-print-a-shipping-label', 'How to Print a Shipping Label');

        // ── Returns stubs ─────────────────────────────────────────────────────
        $this->stub($created['returns']->id, $author?->id, null, 'how-returns-work-on-noon', 'How Returns Work on noon');

        // ── Finance stubs ────────────────────────────────────────────────────
        $this->stub($created['finance-and-payments']->id, $author?->id, null, 'new-statement-detail-report', 'New Statement Detail Report');
        $this->stub($created['finance-and-payments']->id, $author?->id, null, 'understanding-your-weekly-payout', 'Understanding Your Weekly Payout');

        // ── Related articles curation for the real FBN fees article ─────────
        $realArticle = HelpCenterArticle::where('slug', 'fulfilled-by-noon-fbn-fees-in-uae')->first();
        $relatedSlugs = [
            'understanding-seller-tiers-and-inbound-performance',
            'return-to-vendor-rtv-report',
            'how-can-i-track-my-rtv-shipments-and-notifications',
            'receiving-methods-for-rtv-delivery',
            'partial-rtv-handover-in-noon-hubs',
        ];
        $realArticle->update([
            'related_article_ids' => HelpCenterArticle::whereIn('slug', $relatedSlugs)->pluck('id')->all(),
        ]);
    }

    private function stub(string $categoryId, ?string $authorId, ?string $countryId, string $slug, string $title): void
    {
        HelpCenterArticle::firstOrCreate(
            ['slug' => $slug],
            [
                'help_center_category_id' => $categoryId,
                'author_admin_id' => $authorId,
                'country_id' => $countryId,
                'title' => $title,
                'excerpt' => null,
                'body' => '<div><p>This article is coming soon. In the meantime, explore the related articles below or browse the categories on the Help Center home page.</p></div>',
                'status' => 'published',
                'published_at' => now()->subMonths(2),
                'is_featured' => false,
                'views_count' => random_int(5, 150),
            ]
        );
    }

    private function fbnFeesUaeBody(): string
    {
        return <<<'HTML'
<div><p>يشترط لمنحك خدمة "الحقيق بواسطة نون" (FBN) على شروط وأحكام البائع وتخضع لها. يشكل الجمع بين شروط وأحكام البائع وأحكام فريق الخدمة هذا معاً العقد بين الطرفين.</p></div>

<div class="hc-callout hc-callout-info"><p>اعتباراً من 1 سبتمبر 2025، سيتم تطبيق الرسوم التالية على جميع العناصر التي يتم شحنها بواسطة نون (FBN).</p></div>

<div class="hc-callout hc-callout-note"><p><strong>ملاحظة:</strong></p><p>- جميع الرسوم لا تشمل ضريبة القيمة المضافة، وسيتم فرضها على كل وحدة بناءً على أبعاد ووزن المنتج.</p></div>

<h1 id="h_fees_1">1. الرسوم الافتراضية</h1>

<h2 id="h_fees_1_1">1. رسوم الإحالة</h2>
<div class="hc-callout hc-callout-info"><p>تطبق نسب العمولة أدناه اعتباراً من 1 سبتمبر 2025.</p></div>
<div class="hc-callout hc-callout-note"><p>1. توجد بعض الاستثناءات الخاصة بفئات المنتجات (PST) وبمستوى العلامة التجارية تطبق على الرسوم المذكورة أدناه.</p><p>2. سيتم فرض رسوم إحالة بحد أدنى 1 درهم إماراتي لكل منتج تبيعه على نون.</p></div>

<div class="hc-table"><table>
<thead><tr><th>الفئة</th><th>نسبة العمولة % من سعر البيع</th></tr></thead>
<tbody>
<tr><td>الأزياء (الملابس والأحذية)</td><td>27%</td></tr>
<tr><td>الساعات</td><td>15% حتى 5,000 درهم، ثم 5% لما يزيد عن ذلك</td></tr>
<tr><td>النظارات</td><td>15%</td></tr>
<tr><td>المجوهرات — سبائك ومعدنيات ذهبية</td><td>5%</td></tr>
<tr><td>المجوهرات — سبائك فضة</td><td>10%</td></tr>
<tr><td>المجوهرات — الفاخرة والباقي</td><td>16% حتى 1,000 درهم، ثم 5% لما يزيد عن ذلك</td></tr>
<tr><td>الحقائب والأمتعة — حقائب سفر</td><td>20%</td></tr>
<tr><td>الحقائب والأمتعة — الباقي</td><td>25%</td></tr>
<tr><td>المنزل — التنظيف والنظافة</td><td>9%</td></tr>
<tr><td>المنزل — الحياكة، الفراش، ديكور المنزل، المطبخ وغرفة الطعام</td><td>15%</td></tr>
<tr><td>الأثاث</td><td>15% حتى 750 درهم، ثم 10% لما يزيد عن ذلك</td></tr>
<tr><td>الصحة والجمال — العطور</td><td>14%</td></tr>
<tr><td>الصحة والجمال — مستحضرات التجميل، العناية بالبشرة والشعر، التغذية الصحية</td><td>8% حتى 50 درهم، ثم 15% لما يزيد عن ذلك</td></tr>
<tr><td>الإلكترونيات — أجهزة التلفزيون والعرض والبث</td><td>5%</td></tr>
<tr><td>الإلكترونيات — مشغلات الصوت والفيديو والملحقات</td><td>10% – 15%</td></tr>
<tr><td>الإلكترونيات — الهواتف المحمولة</td><td>4% – 10%</td></tr>
<tr><td>متجر الكمبيوتر</td><td>6% – 15%</td></tr>
<tr><td>الكتب والوسائط</td><td>10% – 15%</td></tr>
<tr><td>الأطفال والرضع</td><td>8% حتى 50 درهم، ثم 15% لما يزيد عن ذلك (الألعاب 14%)</td></tr>
<tr><td>حيوانات أليفة</td><td>8% – 15%</td></tr>
<tr><td>السيارات</td><td>5% – 10%</td></tr>
<tr><td>الرياضة والأنشطة الخارجية</td><td>13% – 20%</td></tr>
<tr><td>جميع الفئات الأخرى</td><td>14%</td></tr>
</tbody>
</table></div>
<p>ستجد التفاصيل الكاملة لجميع الفئات ونسب العمولة الدقيقة على <a href="https://helpcenter.noon.partners/ar/fees-revenue-calculator?tab=existing-product&country=ae">حاسبة الرسوم والإيرادات</a>.</p>

<h2 id="h_fees_1_2">2. رسوم FBN الصادرة</h2>
<p>رسوم تطبق على كل وحدة بمجرد وضع علامة "تم التسليم" أو "لم يتم التسليم" (المرتجع غير المسلَّم) على الطلب. تعتمد قيمة رسوم FBN الصادرة على مجموعة العلامة، وأبعاد المنتج، ووزنه، وسعر بيعه.</p>
<div class="hc-callout hc-callout-info"><p><a href="https://helpcenter.noon.partners/ar/fees-revenue-calculator?tab=existing-product&country=ae"><strong>اضغط هنا للانتقال إلى حاسبة الرسوم والإيرادات</strong></a></p></div>
<div class="hc-table"><table>
<thead><tr><th>رسوم FBN الصادرة</th><th>فئة الوزن</th><th>رسوم FBN لكل وحدة (درهم) — ASP ≤ 25 درهم</th><th>رسوم FBN لكل وحدة (درهم) — ASP &gt; 25 درهم</th></tr></thead>
<tbody>
<tr><td>طرود صغيرة</td><td>معدل واحد</td><td>5.0</td><td>7.0</td></tr>
<tr><td>طرود قياسية</td><td>≤ 0.1 كجم</td><td>5.5</td><td>7.5</td></tr>
<tr><td>طرود قياسية</td><td>≤ 0.25 كجم</td><td>6.0</td><td>8.0</td></tr>
<tr><td>طرود قياسية</td><td>≤ 0.50 كجم</td><td>6.0</td><td>8.0</td></tr>
<tr><td>طرود كبيرة</td><td>معدل واحد</td><td>6.5</td><td>8.5</td></tr>
<tr><td>طرد متوسط</td><td>≤ 0.25 كجم</td><td>7.0</td><td>8.5</td></tr>
<tr><td>طرد متوسط</td><td>&gt;0.25 – ≤0.50 كجم</td><td>7.0</td><td>9.0</td></tr>
<tr><td>طرد متوسط</td><td>&gt;0.50 – ≤1.0 كجم</td><td>8.0</td><td>10.0</td></tr>
<tr><td>طرد متوسط</td><td>&gt;1.0 – ≤1.5 كجم</td><td>8.5</td><td>10.5</td></tr>
<tr><td>طرد متوسط</td><td>&gt;1.5 – ≤2.0 كجم</td><td>9.0</td><td>11.0</td></tr>
<tr><td>طرد متوسط</td><td>&gt;2.0 – ≤3.0 كجم</td><td>10.0</td><td>12.0</td></tr>
<tr><td>طرد كبير الحجم</td><td>≤1.0 كجم</td><td>10.0</td><td>12.0</td></tr>
<tr><td>طرد كبير الحجم</td><td>&gt;1.0 – ≤2.0 كجم</td><td>11.0</td><td>13.0</td></tr>
<tr><td>طرد كبير الحجم</td><td>&gt;2.0 – ≤3.0 كجم</td><td>12.0</td><td>14.0</td></tr>
<tr><td>طرد كبير جداً</td><td>≤1 كجم</td><td>11.0</td><td>13.0</td></tr>
<tr><td>ضخم</td><td>≤20 كجم</td><td>33.0</td><td>35.0</td></tr>
</tbody>
</table></div>
<p><em>ملاحظة: يُضاف 1 درهم لكل كيلوجرام إضافي حتى 10-12 كجم حسب الفئة، وتزداد الشرائح تدريجياً للطرود الأكبر حجماً ووزناً — راجع الحاسبة أعلاه للحصول على القيمة الدقيقة لمنتجك.</em></p>

<h2 id="h_fees_1_3">3. رسوم التخزين الشهرية</h2>
<p>رسوم شهرية تطبق على كل عنصر يتم تخزينه في مركز استيفاء نون. تُحتسب الرسوم بناءً على حجم العنصر بالأقدام المكعبة × عدد أيام التخزين.</p>
<div class="hc-table"><table>
<thead><tr><th>الفئة</th><th>الإمارات (التكلفة لكل قدم مكعب شهرياً، درهم)</th></tr></thead>
<tbody><tr><td>جميع الفئات</td><td>1.5</td></tr></tbody>
</table></div>
<p>* قدم مكعب = الطول (سم) × العرض (سم) × الارتفاع (سم) / 28317</p>
<p>يتم إصدار بيان مفصّل لرسوم تخزين FBN شهرياً، عادة خلال الأسبوع الثاني من الشهر التالي. تُجمّع جميع الرسوم المتعلقة بالتخزين في هذا البيان.</p>

<hr>

<h1 id="h_fees_2">II. الرسوم العرضية</h1>
<p>الرسوم العرضية هي رسوم تُطبَّق أو تُعكَس في مجموعة متنوعة من السيناريوهات غير العادية التي قد تحدث أثناء بيع منتجاتك على موقع نون. تُخصم في نهاية الشهر أو في بدايته أيهما أقرب. يمكنك الاطلاع على هذه الرسوم من صفحة المدفوعات الخاصة بك في موقع البائعين، من علامة تبويب "كشوفات الحساب".</p>

<h2 id="h_fees_2_1">1. تخزين طويل الأمد</h2>
<p>رسوم قابلة للتطبيق على كل عنصر مخزن في مركز شحن نون لأكثر من 365 يوماً.</p>
<div class="hc-table"><table>
<thead><tr><th>الفئات</th><th>الإمارات (التكلفة لكل قدم مكعب، درهم)</th></tr></thead>
<tbody><tr><td>جميع الفئات</td><td>25</td></tr></tbody>
</table></div>

<h2 id="h_fees_2_2">2. رسوم تخزين السلع غير القابلة للبيع</h2>
<p>تُطبَّق هذه الرسوم على المخزون غير القابل للبيع والمخزَّن في مراكز الاستيفاء لدى نون لمدة تزيد عن 30 يوماً <strong>(اعتباراً من 1 أبريل 2024)</strong>.</p>
<div class="hc-table"><table>
<thead><tr><th>الفئات</th><th>الإمارات (التكلفة لكل قدم مكعب، درهم)</th></tr></thead>
<tbody><tr><td>مخزون غير قابل للبيع</td><td>12</td></tr></tbody>
</table></div>

<h2 id="h_fees_2_3">3. رسوم الضمان (WNTY)</h2>
<p>رسوم على مطالبات الضمان المقدمة من العملاء والتي لا يستطيع البائع الوفاء بها وفقاً للوائح المحلية وسياسات نون.</p>

<h2 id="h_fees_2_4">4. رسوم غرامة الاحتفاظ (RETP)</h2>
<p>تُحمَّل رسوم غرامة الاحتفاظ (RETP) على البائع لتعويض عملائه في حالة مواجهة أو شكاوى بسبب عدم الالتزام باللوائح المحلية وسياساتها، مثل عناصر بها أجزاء/ملحقات مفقودة، أو مزيفة، أو تالفة، أو معيبة، أو مستعملة، أو منتهية الصلاحية، أو غير مطابقة للمنتج المدرج على الموقع أو بها بعض أخطاء التسعير.</p>

<h2 id="h_fees_2_5">5. رسوم إدارة الإرجاع</h2>
<p>تُفرض هذه الرسوم على البائع لمعالجة العناصر التي يعيدها العملاء لأسباب محددة يكون البائع مسؤولاً عنها. تشمل هذه الأسباب اختلاف العنصر عن الوصف، أو التلف، أو التزييف، أو الاستعمال سابقاً، أو الأجزاء المفقودة، أو العناصر الإضافية في الطلب، أو حالات الإرجاع الاستثنائية. ستكون الرسوم الأولى 15 درهماً إماراتياً أو 20% من رسوم الإحالة للعنصر المرتجع، اعتباراً من 1 أكتوبر 2024.</p>

<h2 id="h_fees_2_6">6. رسوم متنوعة (فوترات)</h2>
<p>مبلغ يتم تحصيله من البائع مقابل أي حوادث غير نظامية قد تتسبب فيها، والتي تولّد شكاوى من عملائها، أو ضد اللوائح المحلية وسياسات نون، مثل العقوبات الموزونة.</p>

<hr>

<h1 id="h_fees_3">III. رسوم إزالة مخزون FBN</h1>
<p>رسوم تُطبَّق على كل وحدة يتم شحنها إليك بناءً على طلبك. تعتمد رسوم إزالة المخزون إما على الوزن الحجمي للمنتج، أو الوزن الفعلي له، أيهما أعلى. <a href="https://helpcenter.noon.partners/ar/category/fulfilled-by-noon-fbn/how-to-calculate-the-fbn-outbound-fee-and-the-inventory-removal-fee-in-uae">تعرّف على كيفية حساب رسوم إزالة مخزون FBN</a>.</p>

<h2 id="h_fees_3_1">أ. طلب التوصيل</h2>
<div class="hc-table"><table>
<thead><tr><th>إزالة المخزون</th><th>وحدة القياس</th><th>الإمارات (درهم)</th></tr></thead>
<tbody>
<tr><td>الحد الأدنى لرسوم طلب إزالة المخزون</td><td>—</td><td>15</td></tr>
<tr><td>رسوم التسليم (لكل قطعة)</td><td>لكل قطعة</td><td>0.3</td></tr>
<tr><td>رسوم الاستلام/التوصيل في نفس المدينة</td><td>لكل كجم *</td><td>1.0</td></tr>
<tr><td>رسوم التوصيل بين المدن</td><td>لكل كجم *</td><td>1.0</td></tr>
</tbody>
</table></div>
<p><em>* كجم أكبر من الوزن بالكيلوجرام أو الوزن الحجمي، حيث يُحسب الوزن الحجمي (الطول سم × العرض سم × الارتفاع سم / 5000)</em></p>

<h2 id="h_fees_3_2">ب. طلب التحصيل</h2>
<div class="hc-table"><table>
<thead><tr><th>إزالة المخزون</th><th>وحدة القياس</th><th>الإمارات (درهم)</th></tr></thead>
<tbody>
<tr><td>الحد الأدنى لرسوم طلب المرتجعات</td><td>—</td><td>15</td></tr>
<tr><td>رسوم المناولة (لكل وحدة)</td><td>لكل قطعة</td><td>0.3</td></tr>
<tr><td>رسوم المعالجة</td><td>لكل قدم مكعب</td><td>0.8</td></tr>
</tbody>
</table></div>

<hr>

<h1 id="h_fees_4">IV. خدمات القيمة المضافة</h1>
<p>يجب أن تلتزم جميع البضائع المرسلة إلى FBN بإرشادات نون الخاصة بالتغليف وضمان سلامة منتجاتك. لتجنب رفض المنتجات التي لا تلبي هذه المعايير، نقدم الآن خدمات ذات قيمة مضافة لراحتك.</p>
<div class="hc-table"><table>
<thead><tr><th>خدمات القيمة المضافة الواردة</th><th>نوع العنصر</th><th>الإمارات — التكلفة لكل وحدة (درهم)</th></tr></thead>
<tbody>
<tr><td>بوليبباغ عادي / تغليف عادي</td><td>جميع المقاسات والطرود القياسية</td><td>0.8</td></tr>
<tr><td>بوليبباغ عادي / تغليف عادي</td><td>كبير جداً</td><td>0.8</td></tr>
<tr><td>بوليبباغ عادي / تغليف عادي</td><td>كبير جداً ضخم</td><td>1.0</td></tr>
<tr><td>غلاف الفقاعات</td><td>جميع المقاسات والطرود القياسية</td><td>2.0</td></tr>
<tr><td>غلاف الفقاعات</td><td>كبير جداً</td><td>2.0</td></tr>
<tr><td>كرتون مع الحشو</td><td>جميع المقاسات والطرود القياسية</td><td>2.0</td></tr>
<tr><td>كرتون مع الحشو</td><td>كبير جداً</td><td>2.5</td></tr>
</tbody>
</table></div>

<hr>

<h2 id="h_fees_faq">الأسئلة الشائعة</h2>
<p><strong>س: أين يمكنني العثور على تفاصيل الرسوم التالية؟</strong></p>
<ul>
<li>رسوم التخزين طويلة الأجل لكل منتج / رمز التخزين التعريفي للمنتج SKU</li>
<li>رسوم تخزين المنتجات غير القابلة للبيع</li>
<li>رسوم إزالة المخزون</li>
<li>الرسوم الإدارية</li>
</ul>
<p><strong>ج:</strong> يمكنك العثور على جميع تفاصيل هذه الرسوم من خلال <strong>موقع البائعين</strong> في <strong>مشغل الحساب الأسبوعية</strong> عن طريق النقر على <strong>القائمة الرئيسية &gt; المدفوعات والرسوم &gt; البيانات</strong>. أو عن طريق تنزيل تقرير المشكلات من خلال النقر على <strong>القائمة الرئيسية &gt; المدفوعات والرسوم &gt; المستخرجات &gt; إنشاء تقرير &gt;</strong> اختر <strong>تقرير تفاصيل المشكلات Statement Detail Report &gt;</strong> حدد تاريخ <strong>البدء</strong> وتاريخ <strong>الانتهاء</strong>، ثم <strong>إنشاء</strong>.</p>

<hr>

<p style="text-align:center"><strong>هل لديك أية أسئلة؟</strong></p>
<p style="text-align:center">اتصل بنا على <a href="mailto:seller@noon.com">seller@noon.com</a></p>
HTML;
    }
}
