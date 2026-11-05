<?php

namespace Tests\Feature\Contracts;

use App\Jobs\NotifySellerNewInquiryJob;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\Country;
use App\Models\CustomerContract;
use App\Services\Vendor\ContractFileService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\Support\ContractDocumentFixtures;
use Tests\Support\CountryContractFixtures;
use Tests\Support\CustomerContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Requirement 7: "My contracts" account section (list, detail, document download) strictly scoped to the signed-in
 * customer, and the document flow of file-mode customer templates.
 */
class CustomerContractAccountTest extends TestCase
{
    use ContractDocumentFixtures;
    use CountryContractFixtures;
    use CustomerContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Country $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Bus::fake([NotifySellerNewInquiryJob::class]);

        $this->scenario = MarketplaceScenario::make()->build();
        $this->home = $this->scenario->country;
        $this->home->update(['site_code' => 'ae-'.Str::lower(Str::random(5))]);
    }

    private function signedBy($customer, ClassifiedCategory $category, ClassifiedContractTemplate $template, array $overrides = []): CustomerContract
    {
        $listing = $this->classifiedAd($category);

        return CustomerContract::factory()->forListing($listing)->create($overrides + [
            'customer_id' => $customer->id,
            'contract_template_id' => $template->id,
            'template_version' => $template->version,
            'customer_name' => $customer->name,
        ]);
    }

    public function test_the_customer_lists_only_his_own_contracts_paginated_and_filterable(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $template = $this->customerTemplate('Property purchase');
        $other = $this->anotherCustomer();
        $mine = collect(range(1, 17))->map(fn ($i) => $this->signedBy($this->scenario->customer, $category, $template, ['signed_at' => now()->subMinutes($i)]));
        $this->signedBy($this->scenario->customer, $category, $template, ['status' => 'superseded']);
        $theirs = $this->signedBy($other, $category, $template);

        $page = $this->actingAs($this->scenario->customer, 'customer')
            ->getJson($this->accountUrl('account/contracts'))
            ->assertOk()
            ->assertJsonPath('data.meta.total', 18)
            ->assertJsonPath('data.meta.per_page', 15)
            ->assertJsonPath('data.meta.last_page', 2);
        $this->assertCount(15, $page->json('data.items'));
        $this->assertNotContains($theirs->id, array_column($page->json('data.items'), 'id'));
        $this->assertSame('Property purchase', $page->json('data.items.0.name'));

        $this->actingAs($this->scenario->customer, 'customer')
            ->getJson($this->accountUrl('account/contracts?status=superseded'))
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_the_detail_shows_the_frozen_text_and_another_customer_gets_404(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $template = $this->customerTemplate('Property purchase');
        $contract = $this->signedBy($this->scenario->customer, $category, $template, ['rendered_content' => '<p>FROZEN TEXT</p>']);

        $this->getJson($this->accountUrl('account/contracts'))->assertUnauthorized();
        $this->getJson($this->accountUrl('account/contracts/'.$contract->id))->assertUnauthorized();

        $this->actingAs($this->scenario->customer, 'customer')
            ->getJson($this->accountUrl('account/contracts/'.$contract->id))
            ->assertOk()
            ->assertJsonPath('data.id', $contract->id)
            ->assertJsonPath('data.content', '<p>FROZEN TEXT</p>')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.listing.number', $contract->listing_number);

        $this->actingAs($this->anotherCustomer(), 'customer')
            ->getJson($this->accountUrl('account/contracts/'.$contract->id))
            ->assertNotFound();
        $this->actingAs($this->anotherCustomer(), 'customer')
            ->getJson($this->accountUrl('account/contracts/'.$contract->id.'/file'))
            ->assertNotFound();
        $this->actingAs($this->scenario->customer, 'customer')
            ->getJson($this->accountUrl('account/contracts/not-a-uuid'))
            ->assertNotFound();
    }

    public function test_a_file_mode_contract_is_copied_at_signing_and_only_its_owner_can_download_it(): void
    {
        $this->fakePrivateDisk();
        $category = ClassifiedCategory::factory()->create();
        $template = ClassifiedContractTemplate::factory()->customerPurchase()->create(['name' => 'Vehicle purchase', 'content_mode' => 'file', 'content_en' => '', 'content_ar' => '']);
        $files = app(ContractFileService::class);
        $template->update(['files' => ['en' => $files->store($this->pdf('terms-en.pdf'), $template->id, 'en')]]);
        $this->assignCustomerTemplate($category, $template);
        $listing = $this->classifiedAd($category);

        $status = $this->actingAs($this->scenario->customer, 'customer')
            ->getJson($this->contractUrl($listing, 'customer-contract'))
            ->assertOk()
            ->assertJsonPath('data.contract.content_mode', 'file');
        $this->assertArrayHasKey('en', $status->json('data.contract.files'));

        // The template document is served for the advertisement it applies to only.
        $this->actingAs($this->scenario->customer, 'customer')
            ->get($this->contractUrl($listing, 'customer-contract/file/en'))
            ->assertOk();
        $this->actingAs($this->scenario->customer, 'customer')
            ->getJson($this->contractUrl($this->classifiedAd(ClassifiedCategory::factory()->create()), 'customer-contract/file/en'))
            ->assertNotFound();

        $this->actingAs($this->scenario->customer, 'customer')
            ->postJson($this->contractUrl($listing, 'customer-contract/sign'), [
                'signer_name' => 'File Signer', 'accept' => true, 'template_id' => $template->id, 'version' => 1, 'language' => 'en',
            ])
            ->assertCreated();

        $contract = CustomerContract::firstOrFail();
        $this->assertSame('file', $contract->content_mode);
        $this->assertTrue($contract->hasSignedFile());
        $this->assertStringStartsWith('customer-contracts/'.$this->scenario->customer->id, $contract->signed_file_path);
        $this->assertNotSame($template->files['en']['path'], $contract->signed_file_path, 'the customer keeps a private copy');

        $this->actingAs($this->scenario->customer, 'customer')
            ->get($this->accountUrl('account/contracts/'.$contract->id.'/file'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->anotherCustomer(), 'customer')
            ->get($this->accountUrl('account/contracts/'.$contract->id.'/file'))
            ->assertNotFound();
    }

    public function test_the_template_document_is_not_served_for_an_advertisement_without_that_contract(): void
    {
        $this->fakePrivateDisk();
        $listing = $this->classifiedAd(ClassifiedCategory::factory()->create());

        $this->getJson($this->contractUrl($listing, 'customer-contract/file/ar'))->assertUnauthorized();
        $this->actingAs($this->scenario->customer, 'customer')
            ->getJson($this->contractUrl($listing, 'customer-contract/file/ar'))
            ->assertNotFound();
    }
}
