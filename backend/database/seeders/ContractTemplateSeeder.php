<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\ClassifiedContractTemplate;
use Illuminate\Database\Seeder;

/**
 * Standard vendor agreements for classified and product categories.
 * firstOrCreate keyed on name, scope and version: re-running never overwrites an edited template.
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
