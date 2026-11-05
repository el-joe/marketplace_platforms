<?php

namespace Tests\Support;

use App\Models\Admin;
use App\Models\Category;
use App\Models\ClassifiedContractTemplate;
use App\Models\ContractCountryAssignment;
use App\Models\Country;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use Illuminate\Support\Str;

/**
 * Builders for country-aware contract tests: extra countries, sellers in them, templates per country
 * and per-country assignments. The MarketplaceScenario country (AE) is the "home" country.
 */
trait CountryContractFixtures
{
    protected function extraCountry(string $iso2, string $name): Country
    {
        return Country::create([
            'id' => (string) Str::uuid(), 'iso_code_2' => $iso2, 'iso_code_3' => $iso2.'X',
            'name_ar' => $name.' ar', 'name_en' => $name, 'currency_code' => 'USD',
            'is_active' => true, 'is_launched' => true, 'site_code' => strtolower($iso2).'x',
        ]);
    }

    protected function vendorIn(Country $country, string $name = 'Seller'): Vendor
    {
        return Vendor::factory()->create([
            'country_id' => $country->id, 'store_name' => $name.' '.Str::random(5), 'vendor_type' => 'product_vendor',
            'global_status' => 'active', 'onboarding_completed_at' => now(),
        ]);
    }

    protected function ownerFor(Vendor $vendor): VendorAdmin
    {
        return VendorAdmin::create([
            'vendor_id' => $vendor->id, 'name' => 'Owner', 'email' => 'owner-'.Str::random(6).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
    }

    protected function contractTemplate(string $scope, string $name, ?Country $country = null, int $version = 1, bool $published = true, bool $default = false, string $mode = 'click_wrap'): ClassifiedContractTemplate
    {
        return ClassifiedContractTemplate::create([
            'name' => $name, 'category_scope' => $scope, 'version' => $version,
            'content_en' => 'EN '.$name.' {{vendor.store_name}}', 'content_ar' => 'AR '.$name.' {{vendor.store_name}}',
            'is_active' => true, 'is_published' => $published,
            'published_at' => $published ? now() : null, 'effective_at' => $published ? now()->subMinute() : null,
            'acceptance_mode' => $mode, 'notice_days' => 14,
            'country_id' => $country?->id, 'is_country_default' => $default,
            'variables_schema' => ClassifiedContractTemplate::variableKeys(),
            'created_by_admin_id' => Admin::factory()->create()->id,
        ]);
    }

    protected function assignCategoryTemplate(Category $category, Country $country, ClassifiedContractTemplate $template): void
    {
        ContractCountryAssignment::create(['product_category_id' => $category->id, 'country_id' => $country->id, 'contract_template_id' => $template->id]);
    }

    protected function childCategory(Category $parent, string $name = 'Child'): Category
    {
        $child = Category::create([
            'id' => (string) Str::uuid(), 'name_en' => $name, 'name_ar' => $name, 'slug' => Str::slug($name).'-'.Str::random(6),
            'commission_rate' => 5, 'is_active' => true, 'is_visible' => true,
        ]);
        Category::whereKey($child->id)->update(['parent_id' => $parent->id]);

        return $child->fresh();
    }

    protected function productIn(Category $category, ?string $requirement = null): Product
    {
        return Product::create([
            'category_id' => $category->id, 'name_en' => 'Prod '.Str::random(4), 'name_ar' => 'منتج', 'slug' => 'prod-'.Str::lower(Str::random(8)),
            'status' => 'active', 'has_variants' => false, 'contract_requirement' => $requirement,
        ]);
    }
}
