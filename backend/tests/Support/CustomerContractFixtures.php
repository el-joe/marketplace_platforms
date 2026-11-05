<?php

namespace Tests\Support;

use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerContractAssignment;
use App\Services\Vendor\CustomerContractAssignmentService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Builders for customer purchase contract tests: templates of the `customer` scope, their section assignments,
 * advertisements and extra customers. Expects $this->scenario (MarketplaceScenario) and $this->home (its country).
 */
trait CustomerContractFixtures
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function customerTemplate(string $name, ?Country $country = null, array $attributes = []): ClassifiedContractTemplate
    {
        return ClassifiedContractTemplate::factory()->customerPurchase()->create($attributes + [
            'name' => $name,
            'country_id' => $country?->id,
        ]);
    }

    /** Links a section to a customer template (country NULL = the all-countries contract). */
    protected function assignCustomerTemplate(ClassifiedCategory $category, ClassifiedContractTemplate $template, ?Country $country = null): CustomerContractAssignment
    {
        return CustomerContractAssignment::create([
            'classified_category_id' => $category->id,
            'country_id' => $country?->id ?? $template->country_id,
            'contract_template_id' => $template->id,
        ]);
    }

    protected function publishCustomerTemplate(ClassifiedContractTemplate $template): void
    {
        app(CustomerContractAssignmentService::class)->publish($template);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function classifiedAd(ClassifiedCategory $category, ?Country $country = null, array $overrides = []): ClassifiedListing
    {
        $listing = ClassifiedListing::create($overrides + [
            'listing_number' => 'CL-'.Str::upper(Str::random(8)),
            'seller_type' => $this->scenario->vendor::class,
            'seller_id' => $this->scenario->vendor->id,
            'classified_category_id' => $category->id,
            'country_id' => ($country ?? $this->home)->id,
            'listing_purpose' => 'sale',
            'title_en' => 'Villa '.Str::random(4),
            'title_ar' => 'فيلا',
            'description_en' => 'Nice',
            'description_ar' => 'جميلة',
            'price' => 250000,
            'currency' => 'AED',
            'price_negotiable' => false,
            'status' => 'active',
        ]);
        $listing->forceFill(['slug' => Str::slug($listing->title_en).'-'.Str::lower(Str::random(4))])->save();

        return $listing->fresh();
    }

    protected function anotherCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Other Customer',
            'email' => 'other-'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.fake()->numerify('########'),
            'password' => Hash::make('password'),
            'country_id' => $this->home->id,
            'status' => 'active',
        ]);
    }

    protected function contractUrl(ClassifiedListing $listing, string $suffix = '', ?Country $country = null): string
    {
        return '/api/customer/v1/'.($country ?? $this->home)->site_code.'/listings/classified/'.$listing->slug.'/'.ltrim($suffix, '/');
    }

    protected function accountUrl(string $path, ?Country $country = null): string
    {
        return '/api/customer/v1/'.($country ?? $this->home)->site_code.'/'.ltrim($path, '/');
    }
}
