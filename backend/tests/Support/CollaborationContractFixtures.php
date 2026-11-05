<?php

namespace Tests\Support;

use App\Models\ClassifiedContractTemplate;
use App\Models\ContractDisplayDestination;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerSellerContract;
use App\Models\Vendor;
use Illuminate\Support\Str;

/**
 * Builders for seller-marketer collaboration contract tests.
 */
trait CollaborationContractFixtures
{
    /**
     * A published seller-marketer template shown on the given Display On destinations (keys of contract_display_destinations).
     *
     * @param  list<string>  $destinations
     * @param  array<string, mixed>  $attributes
     */
    protected function collaborationTemplate(string $name, array $destinations = ['influencer_profile'], ?Country $country = null, array $attributes = []): ClassifiedContractTemplate
    {
        $template = ClassifiedContractTemplate::factory()->marketerCollaboration()->create($attributes + [
            'name' => $name,
            'country_id' => $country?->id,
            'content_en' => '<p>Collaboration '.$name.' seller {{vendor.store_name}} marketer {{marketer.name}}</p>',
            'content_ar' => '<p>تعاون '.$name.' {{vendor.store_name}} {{marketer.name}}</p>',
        ]);

        $template->displayDestinations()->sync(ContractDisplayDestination::whereIn('key', $destinations)->pluck('id')->all());

        return $template;
    }

    protected function signedCollaboration(Vendor $vendor, Marketer $marketer, ClassifiedContractTemplate $template, array $attributes = []): MarketerSellerContract
    {
        return MarketerSellerContract::factory()->create($attributes + [
            'vendor_id' => $vendor->id,
            'marketer_id' => $marketer->id,
            'contract_template_id' => $template->id,
            'template_version' => $template->version,
            'country_id' => $vendor->country_id,
        ]);
    }

    protected function otherCountry(string $iso2 = 'KW', string $name = 'Kuwait'): Country
    {
        return Country::create([
            'id' => (string) Str::uuid(), 'iso_code_2' => $iso2, 'iso_code_3' => $iso2.'X',
            'name_ar' => $name.' ar', 'name_en' => $name, 'currency_code' => 'USD',
            'is_active' => true, 'is_launched' => true, 'site_code' => strtolower($iso2).'x',
        ]);
    }
}
