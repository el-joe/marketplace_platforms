# خطة تنفيذ ملاحظات العميل (اجتماع 2026-09-22)

هذا المستند يلخص ملاحظات العميل، تحليل الكود الحالي، والخطة التفصيلية للتنفيذ. مبني على فحص فعلي للكود في `backend/app` (لا افتراضات).

---

## 0. ملخص الفجوات المكتشفة في الكود الحالي

| # | الموضوع | الحالة الحالية | الفجوة |
|---|---|---|---|
| 1 | عمولة الماركتر | مبلغ ثابت للماركتر + نسبة يأخذها الأدمن من ذلك المبلغ (`MarketerCampaignService::resolveDefaultCommission`) | لا يوجد اختيار "ثابت / نسبة / الاثنين معًا" فعلي قابل للتهيئة من الأدمن |
| 2 | تبويب "كل الأقسام / أقسام محددة" | موجود ضمنيًا فقط عبر `category_id` nullable في `marketer_category_commissions` | لا توجد واجهة فعلية، ولا خيار "استثناء" |
| 3 | فصل أقسام السوق المفتوح عن أقسام المنتجات | موجودة أصلًا: `Category` (منتجات) و `ClassifiedCategory` (سوق مفتوح) منفصلتان | حساب العمولة لا يدعم مسار السوق المفتوح (classified) حاليًا |
| 4 | سعر إعلان السوق المفتوح للمشهور + سماح بالتعديل | غير موجود | يبنى من الصفر |
| 5 | عقود حصرية (مشهور × قسم) | غير موجودة إطلاقًا | تبنى من الصفر بالكامل |
| 6 | قسائم لمتاجر/مشاهير محددين + منتجات + FBM/FBN | `Coupon` يدعم `vendor_id` واحد فقط + `category_id` واحد + `CouponProduct` (منتجات) + `shipping_type_restriction` (FBN/FBP/FBM موجود بالفعل) | لا يوجد ربط بمتاجر/مشاهير متعددين، ولا مسابقة قسائم مدفوعة |
| 7 | نموذج FBM (شحن خاص بالبائع + شركة شحن خاصة به) | `fulfillment_model` نص حر على `VendorListing`، لا يوجد enum مخصص | لا يوجد تخصيص شركة شحن لبائع واحد فقط |
| 8 | رسوم تخزين حسب الوزن الفعلي/الحجمي | `GenerateFbnStorageFeesJob` يحسب بالوحدة (unit count) وليس بالحجم الفعلي، رغم اسم `storage_rate_per_m3_price` | خطأ منطقي + لا يوجد ربط بعدد أيام مجانية متغيّر حسب الوزن |
| 9 | تثبيت أول سعر للمنتج (تاريخ الأسعار) | غير موجود إطلاقًا | يبنى من الصفر |
| 10 | تقويم حجوزات الشاليهات/الفنادق باليوم | `TravelPackage` بتاريخ ثابت (departure/return) + تسعير حسب عدد المسافرين فقط | لا يوجد تقويم توفر يومي ولا تسعير باليوم/بالمبيت |

---

## 1. نظام العمولات الموحّد (Fixed / Percentage / Both) + الأقسام

### 1.1 الهدف
- عمولة الأدمن (على المشاهير للمنتجات، وعلى إعلانات السوق المفتوح) قابلة للتحديد لكل قسم كـ:
  - مبلغ ثابت، أو
  - نسبة مئوية، أو
  - الاثنين معًا (مثال: 5 ريال + 2%)
- فصل تام بين أقسام المنتجات (`Category`) وأقسام السوق المفتوح (`ClassifiedCategory`) — موجود أصلًا في القاعدة، لكن يجب فصله في كل واجهات العمولة أيضًا.
- خيار اختيار الأقسام: "كل الأقسام" / "أقسام محددة" / **"كل الأقسام باستثناء..." (جديد)**.
- لإعلانات السوق المفتوح: سعر منفصل عن سعر المنتجات + خيار "السماح للماركتر بتعديل سعره الخاص" لكل قسم.

### 1.2 تغييرات قاعدة البيانات (migrations جديدة فقط — لا حذف)
```
2026_09_22_203939_add_commission_mode_to_marketer_category_commissions_table.php   -- منفّذ (الجدول القديم، بقي كما هو)
  - commission_mode enum('fixed','percentage','both') default 'percentage'
  - commission_flat_amount unsigned bigint nullable (مبلغ ثابت لكل وحدة × الكمية)
  - commission_rate يُستخدم كنسبة عند mode=percentage|both

2026_09_22_203939_create_open_market_category_commissions_table.php   -- منفّذ (الجدول القديم، بقي كما هو)

2026_09_23_000001_create_marketer_commission_rules_table.php   -- الجدول الموحّد الجديد (يحل محل الجدولين أعلاه في القراءة)
  - marketer_id (nullable = افتراضي المنصة), scope enum(products|open_market|travel)
  - category_type + category_id (morph: Category | ClassifiedCategory | TravelCategory) — category_id فارغ = افتراضي للنوع كله
  - commission_mode, commission_rate, commission_flat_amount, updated_by_admin_id
  - backfill من الجدولين القديمين دون حذفهما
2026_09_23_000003_add_rule_key_to_marketer_commission_rules.php   -- منع التكرار
  - rule_key = sha1(marketer|scope|category_type|category_id) وعليه unique، لأن unique المركّب لا يمنع تكرار الصفوف التي فيها NULL
  - يُملأ تلقائيًا من الموديل عند الحفظ، وتُحذف التكرارات القديمة (يُبقى الأحدث)
  - ملاحظة: الحفظ حاليًا يكتب في الجدول الجديد وفي القديم معًا (dual-write) حتى اكتمال الانتقال

2026_xx_xx_create_open_market_listing_prices_table.php
  - classified_category_id, base_price, allow_marketer_override (bool), min_price/max_price (nullable حدود إن سمح بالتعديل)

2026_xx_xx_create_marketer_campaign_category_rules_table.php  -- لدعم include/exclude متعدد
  - marketer_campaign_id (أو marketer_id عام), classified_category_id/category_id, mode enum('include','exclude')
```

### 1.3 تعديلات الكود
- `app/Models/MarketerCategoryCommission.php`: إضافة `commission_mode`, `commission_flat_amount`, دالة `resolveAmount($baseAmount)` تُرجع القيمة حسب الوضع.
- **جديد** `app/Models/OpenMarketCategoryCommission.php` + Migration + دمجها في المسار المذكور.
- **جديد** `app/Models/OpenMarketListingPrice.php`.
- `app/Services/MarketerCampaignService.php::resolveDefaultCommission()`:
  - إصلاح الفجوة: حاليًا لا يحل الفئة لحملات `classified` (`campaign_category === 'classified'`). يجب إضافة مسار: عند وجود `classified_listing_id`، جلب `classified_listing->classified_category_id` واستخدام `OpenMarketCategoryCommission` بدل `MarketerCategoryCommission`.
  - تعميم منطق الحساب عبر دالة مشتركة `computeCommissionAmount(mode, flat, rate, base)` تُستخدم للمسارين (منتجات/سوق مفتوح).
- إضافة دعم `category_selection_mode` (all/include/exclude) في نموذج إعداد العمولات — على الأرجح جدول إعداد على مستوى `MarketerProfile` أو حملة، وتحديثه في الـ Controllers الإدارية (`Admin/MarketerController.php`).
- الواجهات (`resources/views/admin/marketers/show.blade.php`, `resources/views/admin/marketer_campaigns/create.blade.php`): إضافة select ثلاثي (الكل/تضمين/استثناء) مع multi-select للأقسام، **مكرر مرتين** — مرة لأقسام المنتجات ومرة لأقسام السوق المفتوح (مكونان منفصلان في الواجهة).
- إضافة حقل `allow_marketer_override` في نموذج سعر إعلان السوق المفتوح + حقل إدخال للماركتر في لوحته الخاصة (Partner) يظهر فقط إذا كان `allow_marketer_override = true`، مع التحقق من الحدود الدنيا/العليا إن وُجدت.

---

## 2. العقود الحصرية (Exclusive Contracts) في السوق المفتوح

### 2.1 الهدف
عند إضافة عقد حصري في ملف المشهور لإعلان عميل معه ضمن قسم معيّن، تظهر في صفحة العميل أيقونة "عقد حصري" لمدة محددة.

### 2.2 قاعدة البيانات (جديد بالكامل)
```
create_exclusive_contracts_table
  - id, marketer_id (FK marketers), classified_category_id (FK classified_categories, nullable = كل الأقسام)
  - classified_listing_id (FK, nullable — قد يكون العقد لإعلان عميل محدد وليس قسم كامل)
  - starts_at, ends_at
  - status enum('pending','active','expired','revoked')
  - contract_file_path (nullable), notes
  - created_by (admin_id)
  - timestamps
```

### 2.3 الكود
- `app/Models/ExclusiveContract.php` + علاقات مع `Marketer` و `ClassifiedCategory` و `ClassifiedListing`.
- Scope/Accessor `isCurrentlyActive()` يتحقق من `status='active' && now() between starts_at/ends_at`.
- **Command مجدول** (`app/Console/Commands/ExpireExclusiveContracts.php`) يشغَّل يوميًا (Scheduler) لتحديث الحالة إلى `expired` تلقائيًا.
- Controller إداري جديد: `Admin/ExclusiveContractController.php` (CRUD) + ربطه من صفحة ملف المشهور.
- **منع التعارض**: عند إنشاء عقد حصري لقسم/إعلان، يجب التحقق من عدم وجود عقد حصري نشط آخر يتقاطع لنفس `classified_listing_id` (نفس الإعلان لا يمكن أن يكون حصريًا لأكثر من مشهور بنفس الوقت) — validation في request.
- API عرض العميل: في استجابة تفاصيل إعلان السوق المفتوح، إضافة حقل `exclusive_contract` (marketer, expires_at) إن وُجد عقد نشط — تعديل `ListingController`/`ListingDetailController` المناسب.
- Frontend: إضافة أيقونة/badge "عقد حصري" مع عدّاد تنازلي بسيط في صفحة تفاصيل الإعلان.

---

## 3. القسائم (Coupons) — استهداف متعدد + مسابقة اشتراك مدفوعة

### 3.1 استهداف متعدد للمتاجر/المشاهير + منتجات + FBM/FBN
- `shipping_type_restriction` (FBN/FBP/FBM) **موجود بالفعل** في `Coupon` — يُستخدم كما هو.
- المنتجات: `CouponProduct` (pivot) **موجود بالفعل** — يُستخدم كما هو، لكن يجب توضيحه في الواجهة أنه "لكل بائع/مشهور محدد" (فلترة قائمة المنتجات حسب البائع/المشهور المختار).
- **جديد**: الكوبون حاليًا مرتبط بـ `vendor_id` واحد فقط → يجب تحويله لعلاقة متعددة:
```
create_coupon_vendors_table   (coupon_id, vendor_id)
create_coupon_marketers_table (coupon_id, marketer_id)
```
  - إبقاء `vendor_id` الحالي كما هو لعدم كسر التوافق (legacy)، وإضافة الجداول الجديدة، مع تعديل منطق التحقق في وقت الشراء (`Coupon::isApplicableTo($order)` أو ما يعادلها) ليتحقق من: إن وُجدت صفوف في `coupon_vendors`/`coupon_marketers` فالكوبون يقتصر عليها، وإلا (فارغة) يُطبق على الجميع كسلوك افتراضي متوافق.
  - `app/Models/Coupon.php`: إضافة `belongsToMany(Vendor::class)` و `belongsToMany(Marketer::class)`.

### 3.2 مسابقة/دعوة الاشتراك في القسيمة مقابل رسوم
هذه ميزة جديدة بالكامل: الأدمن ينشئ "دعوة قسيمة" (Coupon Campaign Invitation)، يحدد حد أقصى لعدد المتاجر/المشاهير المشاركين، وحد أدنى لرسوم الاشتراك، والبائع/المشهور يمكنه رفع الرسوم إن أراد.

```
create_coupon_participation_invitations_table
  - id, coupon_id (FK — القسيمة الأصل التي سينشأ منها الكوبون بعد اكتمال المشاركين، أو draft)
  - max_participants
  - min_fee_amount, currency
  - registration_deadline
  - status enum('open','closed','fulfilled','cancelled')

create_coupon_participation_requests_table
  - id, invitation_id (FK), participant_type enum('vendor','marketer'), participant_id
  - offered_fee_amount (>= min_fee_amount, يقبل البائع رفعه)
  - status enum('pending','approved','rejected','paid')
  - paid_at
```

### 3.3 الكود
- `Admin/CouponParticipationInvitationController.php` (CRUD + قائمة الطلبات + قبول/رفض).
- `Vendor/CouponParticipationController.php` + `Marketer/CouponParticipationController.php` (لوحات البائع والمشهور — عرض الدعوات المتاحة، إرسال طلب مشاركة مع تحديد `offered_fee_amount`).
- إشعارات: استخدام نظام الإشعارات الحالي (يوجد بالفعل `Notifications/Vendor/...` مثال `CampaignInvitationAcceptedNotification.php`) — إنشاء نظير: `CouponParticipationInvitationNotification` تُرسل عبر القنوات المسجلة (بريد/واتساب/لوحة تحكم) عند فتح دعوة جديدة تناسب الفئة.
- عند اكتمال العدد الأقصى للمشاركين أو انتهاء الموعد، Job/Command يُفعّل الكوبون تلقائيًا ويربط المشاركين المقبولين بـ `coupon_vendors`/`coupon_marketers`.
- الدفع: ربط برسوم الاشتراك بنظام الدفع/المحفظة الحالي إن وُجد (يحتاج فحص إضافي لنظام الفوترة قبل التنفيذ — TODO تحقق).

---

## 4. نموذج FBM (شحن خاص بالبائع)

### 4.1 الهدف
- في FBM كل شيء (بما فيه الشحن المجاني) على البائع، وهو الوحيد الذي يختار شركة الشحن ويفعّل طريقة دفع العميل.
- إمكانية إضافة شركة شحن خاصة بهذا البائع فقط (لا تظهر للبائعين الآخرين) — مثال: كارفور توصيل داخلي بموظفيه.

### 4.2 قاعدة البيانات
```
add_owner_vendor_id_to_shipping_companies (migration جديدة)
  - owner_vendor_id nullable FK vendors  -- null = شركة شحن عامة تظهر للجميع، غير null = خاصة بهذا البائع فقط

create_vendor_payment_methods_table (إن لم يكن موجودًا مسبقًا — يحتاج تأكيد فحص إضافي)
  - vendor_id, payment_method_id/code, is_enabled  -- يُفعّل فقط لبائعي FBM
```
> ملاحظة: يجب أولًا التأكد عبر فحص إضافي من عدم وجود جدول `shipping_companies` بنية owner مشابهة، وعدم وجود نظام "طرق دفع لكل بائع" حاليًا، لتفادي ازدواجية. **هذا بند يحتاج جلسة استكشاف كود إضافية قبل الشروع (30 دقيقة) لأن الوصف الحالي لم يغطِ `ShippingCompany` model بعمق.**

### 4.3 الكود
- `app/Models/ShippingCompany.php`: scope `visibleTo($vendorId)` يُرجع (`owner_vendor_id is null OR owner_vendor_id = $vendorId`).
- تقييد اختيار طريقة الدفع للعميل بحيث يظهر فقط لبائعي FBM (شرط `fulfillment_model === 'fbm'` عند عرض إعدادات الدفع في لوحة البائع).
- توحيد `fulfillment_model` كـ enum فعلي: `app/Enums/FulfillmentModel.php` (Fbm, Fbn, Fbp) بدل النص الحر، مع migration لتحويل القيم الحالية.

---

## 5. رسوم التخزين الحقيقية حسب الوزن

### 5.1 المشكلة الحالية
`GenerateFbnStorageFeesJob` يحسب `total_fee = quantity_on_hand × rate_per_unit` — لا علاقة فعلية بالحجم رغم اسم `storage_rate_per_m3_price`. يجب إصلاحه ليحسب فعليًا:
- **الوزن الفعلي** = `declared_weight_grams` (موجود في `VendorListing`).
- **الوزن الحجمي** = `(length_cm × width_cm × height_cm) / معامل تحويل قياسي` (عادة 5000 أو 6000 — يُحدَّد مع العميل).
- أيام مجانية متغيرة: وزن < 1 كجم → 60 يوم مجاني، وزن ≥ 1 كجم → 30 يوم مجاني (قابلة للتهيئة من لوحة الأدمن، ليست مثبتة بالكود).

### 5.2 قاعدة البيانات
```
create_storage_fee_free_period_rules_table
  - id, min_weight_grams, max_weight_grams (nullable=مفتوح), free_days
  -- بيانات ابتدائية: (0-999g → 60 يوم), (1000g+ → 30 يوم)

add_billable_volume_columns_to_fbn_storage_fees (أو حساب inline وقت التشغيل بدون تخزين عمود جديد)
```

### 5.3 الكود
- تعديل `GenerateFbnStorageFeesJob`: لكل `WarehouseInventory`
  1. حساب `chargeable_weight = max(actual_weight, volumetric_weight)`.
  2. جلب `free_days` من `StorageFeeFreePeriodRule` المطابقة للوزن.
  3. إذا `days_in_storage <= free_days` → بدون رسوم.
  4. غير ذلك: `total_fee = chargeable_volume_or_weight × rate × quantity` (يُحدَّد مع العميل: بالوزن أم بالحجم النهائي — التوصية: بالحجم الحجمي كما يوحي `storage_rate_per_m3_price`).
- إضافة `app/Models/StorageFeeFreePeriodRule.php` + واجهة أدمن لإدارتها (بدل تثبيتها بالكود).
- عند تجاوز التخزين سنة **ورسوم التخزين > قيمة المنتج ولم يدفع البائع** → المنتج "قابل للتصرف من الأدمن" + إشعار للأدمن (مرتبط ببند #6 أدناه، لأنه يحتاج "سعر المنتج الأصلي" للمقارنة).

---

## 6. تثبيت أول سعر للمنتج (Price History / Draft Snapshot)

### 6.1 الهدف
- أول سعر يضعه البائع لأي منتج (حتى لو كان مسودة) يُثبَّت ويبقى متاحًا للأدمن للرجوع إليه ولو بعد سنوات — حتى لو غيّر البائع السعر لاحقًا.
- نفس المبدأ لمنتجات حصل الأدمن على عينة منها (سيُعاد بيعها) — يُحفظ سعر البائع الأول كمرجع.
- ربط مع رسوم التخزين: إذا تجاوزت رسوم التخزين قيمة "أول سعر" ولم يُدفع → المنتج يصبح قابلًا للتصرف من الأدمن + إشعار.

### 6.2 قاعدة البيانات
```
create_product_price_history_table
  - id, vendor_listing_id (FK), price, recorded_at, source enum('initial','update'), recorded_by (nullable user)

add_first_price_snapshot_to_vendor_listings (migration)
  - first_price decimal(10,2) nullable
  - first_price_locked_at timestamp nullable
```

### 6.3 الكود
- `VendorListing` model: Observer/`booted()` hook — عند `created` (أول حفظ، بما فيه حالة Draft) يُسجَّل `first_price = price` و`first_price_locked_at = now()`، ويُنشأ سجل في `product_price_history` بـ `source='initial'`.
- عند كل تحديث لاحق للسعر (`updating` hook إذا تغيّر `price`)، يُنشأ سجل `source='update'` في `product_price_history` — **مع عدم المساس أبدًا بـ `first_price`** (يبقى ثابتًا للأبد، حتى للأدمن — القراءة فقط).
- صفحة أدمن جديدة (أو تبويب ضمن صفحة المنتج الحالية) لعرض `first_price` وسجل الأسعار الكامل.
- **Command** (`app/Console/Commands/FlagOverstoredUnpaidProducts.php`) دوري (يومي): يبحث عن منتجات `storage_duration > 365 يوم` و `unpaid_storage_fees_total > first_price` → يضع علم `disposable_by_admin = true` (عمود جديد على `vendor_listings`) + إشعار للأدمن.

---

## 7. حجوزات شركات السفر — تقويم يومي (شاليهات/فنادق)

### 7.1 الهدف
- تقويم توفر يومي لكل وحدة (شاليه/غرفة): محجوز/متاح لكل يوم.
- سعر لكل يوم، وسعر مع مبيت / بدون مبيت.
- بعض الحجوزات بفترات محددة (صباحية/مسائية) بساعات محددة لكل فترة.
- الاستفادة من فكرة تطبيق "مسرة" كمرجع تجربة مستخدم (سيتم مراجعة التطبيق يدويًا قبل تصميم واجهة العميل النهائية — بند تصميم UX منفصل، ليس بند باك-إند).

### 7.2 قاعدة البيانات (جديد بالكامل — لا مساس بـ TravelPackage الحالي المستخدم لحزم السفر بتاريخ ثابت)
```
create_bookable_units_table
  - id, travel_agency_id, name, type enum('chalet','hotel_room','other'), capacity, description
  -- الوحدة القابلة للحجز اليومي (منفصلة عن TravelPackage الحالي بتاريخ ثابت)

create_bookable_unit_availability_table
  - id, bookable_unit_id, date, is_available (bool), capacity_override (nullable)
  - price_day_only, price_with_overnight
  -- سطر واحد لكل (وحدة × يوم)، يُدار من لوحة الشركة عبر تقويم

create_bookable_unit_time_slots_table
  - id, bookable_unit_id, slot_type enum('morning','evening','custom'), starts_at time, ends_at time, price
  -- للحجوزات بالفترة بدل اليوم الكامل

create_bookable_unit_reservations_table
  - id, bookable_unit_id, customer_id, date_from, date_to (لحجز متعدد الأيام), time_slot_id (nullable لحجز بالفترة)
  - includes_overnight (bool), total_price, status enum('pending','confirmed','cancelled','completed')
```

### 7.3 الكود
- `app/Models/BookableUnit.php`, `BookableUnitAvailability.php`, `BookableUnitTimeSlot.php`, `BookableUnitReservation.php`.
- `Http/Controllers/TravelAgencyPortal/BookableUnitController.php` (لوحة الشركة: إدارة الوحدات + تقويم التوفر والأسعار).
- API عميل جديد: `Api/Customer/BookableUnitAvailabilityController.php` — يعيد تقويم الشهر مع حالة كل يوم (متاح/محجوز) وسعريه.
- منطق منع التعارض: عند تأكيد حجز، قفل الصفوف المعنية في `bookable_unit_availability` ضمن transaction لمنع الحجز المزدوج (race condition) — استخدام `lockForUpdate()`.
- هذا الجدول منفصل تمامًا عن `TravelPackage`/`TravelBooking` الحالي (حزم سفر بتاريخ ثابت) — الاثنان سيتعايشان كخيارين مختلفين لشركة السفر (حزمة سفر جاهزة أو وحدة تُحجز باليوم).

---

## 8. خطة التنفيذ المقترحة (تسلسل عملي)

**المرحلة 1 — الأساسات المشتركة (أسبوع 1-2)**
1. توحيد enum الـ Fulfillment Model (بند 4).
2. إصلاح مسار عمولة السوق المفتوح (classified) في `resolveDefaultCommission` (بند 1) — إصلاح فجوة حرجة موجودة الآن.
3. نظام عمولة fixed/percentage/both للمنتجات والسوق المفتوح (بند 1).

**المرحلة 2 (أسبوع 2-4)**
4. العقود الحصرية (بند 2).
5. تثبيت أول سعر + سجل الأسعار (بند 6).
6. إصلاح رسوم التخزين الحجمية (بند 5) — يعتمد جزئيًا على بند 6.

**المرحلة 3 (أسبوع 4-6)**
7. توسعة القسائم (استهداف متعدد + منتجات/FBM موجودة) (بند 3.1).
8. مسابقة اشتراك القسائم المدفوعة (بند 3.2) — الأعقد، يحتاج تكامل دفع.

**المرحلة 4 (أسبوع 6-9)**
9. FBM شركة شحن خاصة بالبائع (بند 4) — يحتاج فحص كود إضافي أولًا.
10. تقويم حجوزات الشاليهات/الفنادق (بند 7) — الأكبر حجمًا، يُقترح تجزئته لمرحلتين فرعيتين (تقويم أساسي، ثم فترات صباحية/مسائية).

**قواعد صارمة أثناء التنفيذ (وفق تعليمات المستخدم الدائمة):**
- لا حذف/تعديل مايجريشنات قديمة إطلاقًا — إضافات جديدة فقط.
- عدم تشغيل `schema:dump --prune` مطلقًا.
- كل تغيير DB عبر migration جديدة موقّعة بالتاريخ.

## نقاط تحتاج توضيح من العميل في الاجتماع
1. رسوم التخزين: هل تُحسب بالوزن الحجمي أم الفعلي أم الأكبر بينهما؟ وما معامل التحويل الحجمي المعتمد؟
2. مسابقة القسائم: آلية الدفع الفعلية (محفظة داخلية؟ بوابة دفع؟) وهل الرسوم تُرد إن لم يُقبل الطلب؟
3. العقود الحصرية: هل الحصرية على مستوى القسم كامل أم إعلان محدد أم الاثنان حسب الحالة؟
4. الشاليهات: هل نحتاج فعليًا لمراجعة تطبيق "مسرة" (لقطات شاشة) لتحديد تفاصيل UX قبل التصميم النهائي؟
5. FBM: تأكيد ما إذا كان يوجد نظام "طرق دفع لكل بائع" حاليًا (لم يُتحقق بعد في الفحص الأولي).
