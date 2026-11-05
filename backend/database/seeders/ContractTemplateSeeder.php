<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use Illuminate\Database\Seeder;

/**
 * Standard vendor agreements for classified and product categories, plus one sample agreement per classified
 * section type (real estate, vehicles, travel and services, general).
 *
 * firstOrCreate keyed on name, scope and version: re-running never overwrites an edited template. A section
 * template is linked to a classified category only when such a category already exists (matched by slug), the
 * category has no contract link yet and its contract switch was not explicitly turned off by an admin.
 * Nothing is linked for categories that do not exist, and existing links are never replaced.
 * Sample customer purchase contracts (real estate, vehicles) are created as unpublished, unlinked drafts: nothing
 * changes for customers until an admin links sections to one and publishes it.
 * Travel packages are a separate module with their own booking contracts; the travel/services template here
 * only serves classified categories of those slugs.
 */
class ContractTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = Admin::query()->value('id');

        if (! $adminId) {
            return;
        }

        foreach ($this->templates() as $template) {
            ClassifiedContractTemplate::firstOrCreate(
                ['name' => $template['name'], 'category_scope' => $template['scope'], 'version' => 1],
                [
                    'content_en' => $template['content_en'],
                    'content_ar' => $template['content_ar'],
                    'is_active' => true,
                    'is_published' => true,
                    'variables_schema' => ClassifiedContractTemplate::variableKeys(),
                    'created_by_admin_id' => $adminId,
                ],
            );
        }

        foreach ($this->sectionTemplates() as $section) {
            $template = ClassifiedContractTemplate::firstOrCreate(
                ['name' => $section['name'], 'category_scope' => 'classified', 'version' => 1],
                [
                    'content_en' => $this->sectionBody($section['title_en'], $section['clauses_en'], 'en'),
                    'content_ar' => $this->sectionBody($section['title_ar'], $section['clauses_ar'], 'ar'),
                    'is_active' => true,
                    'is_published' => true,
                    'variables_schema' => ClassifiedContractTemplate::variableKeys(),
                    'created_by_admin_id' => $adminId,
                ],
            );

            $this->linkToExistingCategories($template, $section['slugs']);
        }

        foreach ($this->customerTemplates() as $template) {
            ClassifiedContractTemplate::firstOrCreate(
                ['name' => $template['name'], 'category_scope' => ClassifiedContractTemplate::SCOPE_CUSTOMER, 'version' => 1],
                [
                    'content_en' => $template['content_en'],
                    'content_ar' => $template['content_ar'],
                    'is_active' => true,
                    'is_published' => false,
                    'variables_schema' => ClassifiedContractTemplate::variableKeys(),
                    'created_by_admin_id' => $adminId,
                ],
            );
        }
    }

    /**
     * Sample customer purchase contracts. Plain text (converted to HTML by the renderer), bilingual.
     *
     * @return list<array{name: string, content_en: string, content_ar: string}>
     */
    private function customerTemplates(): array
    {
        return [
            [
                'name' => 'Real Estate Purchase Contract (Customer)',
                'content_en' => "REAL ESTATE PURCHASE / LEASE INTENT\n\nCustomer: {{customer.name}} ({{customer.email}}).\nAdvertisement: {{listing.number}} - {{listing.title}} ({{listing.purpose}}), advertised price {{listing.price}}, {{listing.country}}. Advertiser: {{seller.name}}. Section: {{category.name_en}}.\n\n1. I have reviewed the advertisement and I am interested in concluding the transaction with the advertiser.\n2. I understand that {{platform.name_en}} is not a party to any sale or lease between me and the advertiser.\n3. I will inspect the property and verify ownership and legal documents before paying any amount.\n4. The final price, payment schedule and handover are agreed directly with the advertiser in a separate written agreement.\n5. The information I provide is accurate.\n\nVersion {{contract.version}} - {{contract.date}}\nAccepted by: {{customer.signer_name}}",
                'content_ar' => "نية شراء / استئجار عقار\n\nالعميل: {{customer.name}} ({{customer.email}}).\nالإعلان: {{listing.number}} - {{listing.title}} ({{listing.purpose}})، السعر المعلن {{listing.price}}، {{listing.country}}. المعلن: {{seller.name}}. القسم: {{category.name_ar}}.\n\n1. اطّلعت على الإعلان وأرغب في إتمام المعاملة مع المعلن.\n2. أدرك أن {{platform.name_ar}} ليست طرفاً في أي بيع أو إيجار بيني وبين المعلن.\n3. سأعاين العقار وأتحقق من الملكية والمستندات القانونية قبل دفع أي مبلغ.\n4. يُتفق على السعر النهائي وجدول الدفع والتسليم مباشرة مع المعلن في اتفاق مكتوب منفصل.\n5. المعلومات التي أقدمها صحيحة.\n\nالنسخة {{contract.version}} - {{contract.date}}\nقبلها: {{customer.signer_name}}",
            ],
            [
                'name' => 'Vehicle Purchase Contract (Customer)',
                'content_en' => "VEHICLE PURCHASE INTENT\n\nCustomer: {{customer.name}} ({{customer.email}}).\nAdvertisement: {{listing.number}} - {{listing.title}} ({{listing.purpose}}), advertised price {{listing.price}}, {{listing.country}}. Advertiser: {{seller.name}}. Section: {{category.name_en}}.\n\n1. I have reviewed the advertisement and I am interested in concluding the transaction with the advertiser.\n2. I understand that {{platform.name_en}} is not a party to any sale between me and the advertiser.\n3. I will inspect the vehicle and verify its registration, ownership and any outstanding finance before paying any amount.\n4. The final price, payment and transfer of ownership are agreed directly with the advertiser in a separate written agreement.\n5. The information I provide is accurate.\n\nVersion {{contract.version}} - {{contract.date}}\nAccepted by: {{customer.signer_name}}",
                'content_ar' => "نية شراء مركبة\n\nالعميل: {{customer.name}} ({{customer.email}}).\nالإعلان: {{listing.number}} - {{listing.title}} ({{listing.purpose}})، السعر المعلن {{listing.price}}، {{listing.country}}. المعلن: {{seller.name}}. القسم: {{category.name_ar}}.\n\n1. اطّلعت على الإعلان وأرغب في إتمام المعاملة مع المعلن.\n2. أدرك أن {{platform.name_ar}} ليست طرفاً في أي عملية بيع بيني وبين المعلن.\n3. سأعاين المركبة وأتحقق من تسجيلها وملكيتها وأي التزامات تمويلية قائمة قبل دفع أي مبلغ.\n4. يُتفق على السعر النهائي والدفع ونقل الملكية مباشرة مع المعلن في اتفاق مكتوب منفصل.\n5. المعلومات التي أقدمها صحيحة.\n\nالنسخة {{contract.version}} - {{contract.date}}\nقبلها: {{customer.signer_name}}",
            ],
        ];
    }

    /**
     * @param  list<string>  $slugs
     */
    private function linkToExistingCategories(ClassifiedContractTemplate $template, array $slugs): void
    {
        if ($slugs === [] || ! $template->is_published || ! $template->is_active) {
            return;
        }

        ClassifiedCategory::whereIn('slug', $slugs)
            ->whereNull('contract_template_id')
            ->where(fn ($query) => $query->whereNull('requires_contract')->orWhere('requires_contract', true))
            ->get()
            ->each(fn (ClassifiedCategory $category) => $category->forceFill([
                'contract_template_id' => $template->id,
                'requires_contract' => true,
            ])->save());
    }

    /**
     * @param  list<string>  $clauses
     */
    private function sectionBody(string $title, array $clauses, string $language): string
    {
        $party = $language === 'ar'
            ? 'تُبرم هذه الاتفاقية بين {{platform.name_ar}} و{{vendor.store_name}} ({{vendor.business_name}})، رقم السجل {{vendor.registration_number}}، العنوان {{vendor.address}}، {{vendor.country}}. القسم: {{category.name_ar}}.'
            : 'This agreement is made between {{platform.name_en}} and {{vendor.store_name}} ({{vendor.business_name}}), registration {{vendor.registration_number}}, located at {{vendor.address}}, {{vendor.country}}. Section: {{category.name_en}}.';
        $footer = $language === 'ar'
            ? "تسري هذه النسخة {{contract.version}} اعتباراً من {{contract.date}}.\n\nوقّعها: {{vendor.signer_name}}"
            : "This agreement version {{contract.version}} takes effect on {{contract.date}}.\n\nSigned by: {{vendor.signer_name}}";

        $numbered = collect($clauses)->map(fn (string $clause, int $index) => ($index + 1).'. '.$clause)->implode("\n");

        return $title."\n\n".$party."\n\n".$numbered."\n".(count($clauses) + 1).'. '.$footer;
    }

    /**
     * Sample agreements per section type. `slugs` are the classified category slugs they are linked to when those exist.
     *
     * @return list<array{name: string, slugs: list<string>, title_en: string, title_ar: string, clauses_en: list<string>, clauses_ar: list<string>}>
     */
    private function sectionTemplates(): array
    {
        return [
            [
                'name' => 'Real Estate Advertiser Agreement',
                'slugs' => ['real-estate', 'realestate', 'properties', 'property'],
                'title_en' => 'REAL ESTATE ADVERTISER AGREEMENT',
                'title_ar' => 'اتفاقية المعلن العقاري',
                'clauses_en' => [
                    'The vendor confirms that it owns, or is authorised in writing to advertise, every property it lists.',
                    'Ownership or authorisation documents must be provided on request and kept valid while the listing is live.',
                    'Prices, areas and descriptions must be accurate; the platform may remove listings that mislead buyers.',
                    'The platform is not a party to any sale or lease between the vendor and a customer.',
                ],
                'clauses_ar' => [
                    'يقرّ المعلن بأنه يملك كل عقار يعلن عنه أو أنه مفوّض كتابياً بالإعلان عنه.',
                    'يجب تقديم مستندات الملكية أو التفويض عند الطلب وإبقاؤها سارية طوال مدة الإعلان.',
                    'يجب أن تكون الأسعار والمساحات والأوصاف دقيقة، وللمنصة إزالة الإعلانات المضلّلة.',
                    'المنصة ليست طرفاً في أي بيع أو إيجار بين المعلن والعميل.',
                ],
            ],
            [
                'name' => 'Vehicle Advertiser Agreement',
                'slugs' => ['vehicles', 'cars', 'car', 'motors', 'automotive'],
                'title_en' => 'VEHICLE ADVERTISER AGREEMENT',
                'title_ar' => 'اتفاقية معلن المركبات',
                'clauses_en' => [
                    'The vendor confirms that every vehicle listed is owned by it or that it is authorised to sell it.',
                    'The vendor discloses accidents, mileage, defects and any outstanding finance accurately.',
                    'Registration and ownership documents must be available to the buyer before a sale is concluded.',
                    'The platform is not a party to any sale between the vendor and a customer.',
                ],
                'clauses_ar' => [
                    'يقرّ المعلن بأن كل مركبة معروضة مملوكة له أو أنه مفوّض ببيعها.',
                    'يفصح المعلن بدقة عن الحوادث وعدد الكيلومترات والعيوب وأي التزامات تمويلية قائمة.',
                    'يجب إتاحة مستندات التسجيل والملكية للمشتري قبل إتمام البيع.',
                    'المنصة ليست طرفاً في أي عملية بيع بين المعلن والعميل.',
                ],
            ],
            [
                'name' => 'Travel and Services Provider Agreement',
                'slugs' => ['travel', 'services', 'tourism'],
                'title_en' => 'TRAVEL AND SERVICES PROVIDER AGREEMENT',
                'title_ar' => 'اتفاقية مزوّد السفر والخدمات',
                'clauses_en' => [
                    'The vendor holds every licence required to offer the advertised services in the relevant country.',
                    'Descriptions, prices, schedules and cancellation terms must be accurate and honoured.',
                    'The vendor is responsible for delivering the service and for handling complaints about it.',
                    'The platform may suspend any advertisement that breaches this agreement.',
                ],
                'clauses_ar' => [
                    'يحمل المعلن جميع التراخيص اللازمة لتقديم الخدمات المعلن عنها في الدولة المعنية.',
                    'يجب أن تكون الأوصاف والأسعار والجداول وشروط الإلغاء دقيقة وأن يُلتزم بها.',
                    'المعلن مسؤول عن تقديم الخدمة وعن معالجة الشكاوى المتعلقة بها.',
                    'يحق للمنصة إيقاف أي إعلان يخالف هذه الاتفاقية.',
                ],
            ],
            [
                'name' => 'General Classified Advertiser Agreement',
                'slugs' => ['general', 'other', 'others', 'misc'],
                'title_en' => 'GENERAL CLASSIFIED ADVERTISER AGREEMENT',
                'title_ar' => 'اتفاقية المعلن العامة للإعلانات المبوّبة',
                'clauses_en' => [
                    'The vendor publishes only accurate listings for items or services it is entitled to offer.',
                    'Listings must comply with platform policies and applicable law.',
                    'The platform may reject, pause or remove any listing that breaches this agreement.',
                ],
                'clauses_ar' => [
                    'ينشر المعلن إعلانات دقيقة فقط عن سلع أو خدمات يحق له عرضها.',
                    'يجب أن تتوافق الإعلانات مع سياسات المنصة والأنظمة المعمول بها.',
                    'يحق للمنصة رفض أي إعلان يخالف هذه الاتفاقية أو إيقافه أو إزالته.',
                ],
            ],
        ];
    }

    /**
     * @return list<array{name: string, scope: string, content_en: string, content_ar: string}>
     */
    private function templates(): array
    {
        return [
            [
                'name' => 'Classified Vendor Agreement',
                'scope' => 'classified',
                'content_en' => "CLASSIFIED VENDOR AGREEMENT\n\nThis agreement is made between {{platform.name_en}} and {{vendor.store_name}} ({{vendor.business_name}}), registration {{vendor.registration_number}}, tax ID {{vendor.tax_id}}, located at {{vendor.address}}, {{vendor.country}}.\n\n1. The vendor will publish only accurate listings in the category {{category.name_en}}.\n2. Listings must comply with platform policies and applicable law.\n3. The platform may reject, pause or remove any listing that breaches this agreement.\n4. This agreement version {{contract.version}} takes effect on {{contract.date}}.\n\nSigned by: {{vendor.signer_name}}",
                'content_ar' => "اتفاقية البائع للإعلانات المبوّبة\n\nتُبرم هذه الاتفاقية بين {{platform.name_ar}} و{{vendor.store_name}} ({{vendor.business_name}})، رقم السجل {{vendor.registration_number}}، الرقم الضريبي {{vendor.tax_id}}، والعنوان {{vendor.address}}، {{vendor.country}}.\n\n1. يلتزم البائع بنشر إعلانات دقيقة فقط في فئة {{category.name_ar}}.\n2. يجب أن تتوافق الإعلانات مع سياسات المنصة والأنظمة المعمول بها.\n3. يحق للمنصة رفض أي إعلان أو إيقافه أو إزالته في حال مخالفة هذه الاتفاقية.\n4. تسري هذه النسخة {{contract.version}} اعتباراً من {{contract.date}}.\n\nوقّعها: {{vendor.signer_name}}",
            ],
            [
                'name' => 'Product Vendor Agreement',
                'scope' => 'product',
                'content_en' => "PRODUCT VENDOR AGREEMENT\n\nThis agreement is made between {{platform.name_en}} and {{vendor.store_name}} ({{vendor.business_name}}), registration {{vendor.registration_number}}, tax ID {{vendor.tax_id}}, located at {{vendor.address}}, {{vendor.country}}.\n\n1. The vendor will sell only genuine products in the category {{category.name_en}}.\n2. The vendor is responsible for product quality, pricing accuracy and stock levels.\n3. The platform may suspend any product listing that breaches this agreement.\n4. This agreement version {{contract.version}} takes effect on {{contract.date}}.\n\nSigned by: {{vendor.signer_name}}",
                'content_ar' => "اتفاقية البائع للمنتجات\n\nتُبرم هذه الاتفاقية بين {{platform.name_ar}} و{{vendor.store_name}} ({{vendor.business_name}})، رقم السجل {{vendor.registration_number}}، الرقم الضريبي {{vendor.tax_id}}، والعنوان {{vendor.address}}، {{vendor.country}}.\n\n1. يلتزم البائع ببيع منتجات أصلية فقط في فئة {{category.name_ar}}.\n2. البائع مسؤول عن جودة المنتجات ودقة الأسعار ومستويات المخزون.\n3. يحق للمنصة إيقاف أي منتج يخالف هذه الاتفاقية.\n4. تسري هذه النسخة {{contract.version}} اعتباراً من {{contract.date}}.\n\nوقّعها: {{vendor.signer_name}}",
            ],
        ];
    }
}
