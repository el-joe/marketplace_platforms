<?php

namespace Tests\Feature\Contracts;

use App\Enums\VendorListingStatus;
use App\Exceptions\ContractRequiredException;
use App\Models\VendorContract;
use App\Models\VendorListing;
use App\Services\ListingContractGate;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ListingService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CountryContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Where the individual-contract rule is enforced: vendor API (the former hole), web wizard and every path that
 * can make a listing live. The global Seller Agreement never replaces an individual contract.
 */
class IndividualContractEnforcementTest extends TestCase
{
    use CountryContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->vendor->update(['global_status' => 'active', 'onboarding_completed_at' => now(), 'vendor_type' => 'product_vendor']);
    }

    /**
     * @return array<string, mixed>
     */
    private function listingPayload(?string $variantId = null): array
    {
        return [
            'product_variant_id' => $variantId ?? $this->scenario->variants[0]->id,
            'country_id' => $this->scenario->country->id,
            'price' => 150,
            'condition' => 'new',
            'fulfillment_model' => 'fbm',
        ];
    }

    private function requireContract(?object $category = null): object
    {
        $template = $this->contractTemplate('product', 'Food terms');
        ($category ?? $this->scenario->category)->forceFill(['contract_template_id' => $template->id, 'requires_contract' => true])->save();

        return $template;
    }

    // ── Vendor API: the unguarded Flutter path ───────────────────────────────

    public function test_vendor_api_listing_creation_is_blocked_until_the_individual_contract_is_signed(): void
    {
        $template = $this->requireContract();
        $owner = $this->ownerFor($this->scenario->vendor);
        $before = VendorListing::count();

        $this->actingAs($owner, 'vendor')
            ->postJson('/api/vendor/v1/listings', $this->listingPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'contract_required')
            ->assertJsonPath('data.contract.template_id', $template->id)
            ->assertJsonPath('data.contract.category_scope', 'product')
            ->assertJsonPath('data.contract.category_id', $this->scenario->category->id)
            ->assertJsonPath('data.contract.country.id', $this->scenario->country->id);

        $this->assertSame($before, VendorListing::count(), 'nothing is created while the contract is unsigned');

        $this->actingAs($owner, 'vendor')
            ->postJson('/api/vendor/v1/contracts/'.$template->id.'/accept', ['signature_name' => 'Owner', 'language' => 'en', 'agreed' => true])
            ->assertOk()
            ->assertJsonPath('data.remaining_pending', 0);

        $this->actingAs($owner, 'vendor')
            ->postJson('/api/vendor/v1/listings', $this->listingPayload())
            ->assertCreated();

        $this->assertSame($before + 1, VendorListing::count());
        $this->assertSame(1, VendorContract::where('vendor_id', $this->scenario->vendor->id)->count(), 'one signature covers later listings');
    }

    public function test_vendor_api_exempt_product_and_switched_off_category_need_no_individual_contract(): void
    {
        $template = $this->requireContract();
        $owner = $this->ownerFor($this->scenario->vendor);
        $exempt = $this->productIn($this->scenario->category, 'exempt');
        $exemptVariant = $exempt->variants()->create(['sku' => 'EX-'.uniqid(), 'variant_name' => 'x', 'is_default' => true, 'is_active' => true, 'position' => 0]);

        $this->actingAs($owner, 'vendor')
            ->postJson('/api/vendor/v1/listings', $this->listingPayload($exemptVariant->id))
            ->assertCreated();

        $this->scenario->category->forceFill(['requires_contract' => false])->save();

        $this->actingAs($owner, 'vendor')
            ->postJson('/api/vendor/v1/listings', $this->listingPayload())
            ->assertCreated();

        $this->assertSame(0, VendorContract::count());
        $this->assertNotNull($template);
    }

    public function test_vendor_api_reports_the_typed_error_when_no_contract_is_configured_for_the_sellers_country(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');
        $omani = $this->vendorIn($oman);
        $owner = $this->ownerFor($omani);
        $food = $this->contractTemplate('product', 'Food terms', $this->scenario->country);
        $this->scenario->category->forceFill(['requires_contract' => true])->save();
        $this->assignCategoryTemplate($this->scenario->category, $this->scenario->country, $food);

        $this->actingAs($owner, 'vendor')
            ->postJson('/api/vendor/v1/listings', $this->listingPayload() + [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'contract_not_configured')
            ->assertJsonPath('data.country.code', 'OM');

        $this->actingAs($owner, 'vendor')
            ->getJson('/api/vendor/v1/contracts/check-category?scope=product&category_id='.$this->scenario->category->id)
            ->assertStatus(422)
            ->assertJsonPath('code', 'contract_not_configured');

        $this->actingAs($owner, 'vendor')
            ->getJson('/api/vendor/v1/contracts/pending')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_vendor_api_agreement_endpoint_returns_the_agreement_of_the_sellers_country(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');
        $omani = $this->vendorIn($oman);
        $uaeAgreement = $this->contractTemplate('platform', 'Seller Agreement', $this->scenario->country);
        $omanAgreement = $this->contractTemplate('platform', 'Seller Agreement', $oman);

        $this->actingAs($this->ownerFor($omani), 'vendor')
            ->getJson('/api/vendor/v1/legal/agreement')
            ->assertOk()
            ->assertJsonPath('data.country.code', 'OM')
            ->assertJsonPath('data.agreement_configured', true)
            ->assertJsonPath('data.pending.template_id', $omanAgreement->id);

        $this->actingAs($this->ownerFor($this->scenario->vendor), 'vendor')
            ->getJson('/api/vendor/v1/legal/agreement')
            ->assertJsonPath('data.pending.template_id', $uaeAgreement->id);
    }

    public function test_check_category_accepts_a_product_and_reports_the_product_level_contract(): void
    {
        $template = $this->contractTemplate('product', 'Licence terms');
        $licensed = $this->productIn($this->scenario->category, 'required');
        $licensed->forceFill(['contract_template_id' => $template->id])->save();
        $owner = $this->ownerFor($this->scenario->vendor);

        $this->actingAs($owner, 'vendor')
            ->getJson('/api/vendor/v1/contracts/check-category?scope=product&product_id='.$licensed->id)
            ->assertOk()
            ->assertJsonPath('data.requires_contract', true)
            ->assertJsonPath('data.product_id', $licensed->id)
            ->assertJsonPath('data.template_id', $template->id);

        $this->actingAs($owner, 'vendor')
            ->getJson('/api/vendor/v1/contracts/check-category?scope=product&category_id='.$this->scenario->category->id)
            ->assertOk()
            ->assertJsonPath('data.requires_contract', false);
    }

    // ── Activation / approval paths ──────────────────────────────────────────

    public function test_a_listing_cannot_be_reactivated_or_approved_while_its_contract_is_unsigned(): void
    {
        $listing = $this->scenario->vendorListingFbp;
        $listing->update(['status' => VendorListingStatus::Paused->value]);
        $template = $this->requireContract();

        try {
            app(ListingService::class)->updateStatus($listing->fresh(), 'active');
            $this->fail('Reactivation must be blocked');
        } catch (ContractRequiredException $e) {
            $this->assertSame($template->id, $e->contract['template_id']);
        }
        $this->assertSame('paused', $listing->fresh()->status->value);

        $this->expectException(ContractRequiredException::class);
        ListingContractGate::assertCanGoLive($listing->fresh());
    }

    public function test_activation_works_once_the_contract_is_signed_and_existing_active_listings_are_untouched(): void
    {
        $listing = $this->scenario->vendorListingFbp;
        $this->requireContract();

        // The listing already live is never switched off by enabling the switch.
        $this->assertSame('active', $listing->fresh()->status->value);

        $listing->update(['status' => VendorListingStatus::Paused->value]);
        app(CategoryContractService::class)->sign($this->scenario->vendor, null, 'product', $this->scenario->category->id, 'en', 'Owner', '127.0.0.1', 'phpunit');

        $updated = app(ListingService::class)->updateStatus($listing->fresh(), 'active');
        $this->assertSame('active', $updated->status->value);
    }

    // ── Web wizard ───────────────────────────────────────────────────────────

    public function test_web_wizard_demands_a_product_level_contract_and_signs_it_inline(): void
    {
        Permission::firstOrCreate(['name' => 'listings.create', 'guard_name' => 'vendor']);
        $owner = $this->ownerFor($this->scenario->vendor);
        $owner->givePermissionTo('listings.create');

        $template = $this->contractTemplate('product', 'Licence terms');
        $this->scenario->category->forceFill(['requires_contract' => false])->save();
        $licensed = $this->productIn($this->scenario->category, 'required');
        $licensed->forceFill(['contract_template_id' => $template->id])->save();

        $this->actingAs($owner, 'vendor')
            ->postJson(route('partner.listings.store'), ['product_id' => $licensed->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'contract_required')
            ->assertJsonPath('contract.template_id', $template->id)
            ->assertJsonPath('contract.product_id', $licensed->id)
            ->assertJsonPath('contract.accept_url', route('partner.contracts.accept', $template));

        $this->actingAs($owner, 'vendor')
            ->postJson(route('partner.contracts.accept', $template), [
                'signature_name' => 'Owner', 'language' => 'en', 'agreed' => 1,
                'category_scope' => 'product', 'product_id' => $licensed->id,
            ])
            ->assertOk();

        $contract = VendorContract::where('vendor_id', $this->scenario->vendor->id)->sole();
        $this->assertSame($licensed->id, $contract->product_id);
        $this->assertSame($this->scenario->country->id, $contract->country_id);

        // A second product of the same category that does not require one is not affected.
        $plain = $this->productIn($this->scenario->category);
        $this->actingAs($owner, 'vendor')
            ->getJson(route('partner.listings.contract-check', ['product_id' => $plain->id]))
            ->assertOk()
            ->assertJsonPath('requires_contract', false);

        $this->actingAs($owner, 'vendor')
            ->getJson(route('partner.listings.contract-check', ['product_id' => $licensed->id]))
            ->assertOk()
            ->assertJsonPath('requires_contract', true)
            ->assertJsonPath('signed', true);
    }

    public function test_web_wizard_hint_reports_an_unconfigured_country_instead_of_another_countrys_contract(): void
    {
        Permission::firstOrCreate(['name' => 'listings.create', 'guard_name' => 'vendor']);
        $oman = $this->extraCountry('OM', 'Oman');
        $omani = $this->vendorIn($oman);
        $owner = $this->ownerFor($omani);
        $owner->givePermissionTo('listings.create');
        $food = $this->contractTemplate('product', 'Food terms', $this->scenario->country);
        $this->scenario->category->forceFill(['requires_contract' => true])->save();
        $this->assignCategoryTemplate($this->scenario->category, $this->scenario->country, $food);

        $this->actingAs($owner, 'vendor')
            ->getJson(route('partner.listings.contract-check', ['product_id' => $this->scenario->product->id]))
            ->assertOk()
            ->assertJsonPath('requires_contract', true)
            ->assertJsonPath('not_configured', true);

        $this->actingAs($owner, 'vendor')
            ->postJson(route('partner.listings.store'), ['product_id' => $this->scenario->product->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'contract_not_configured');
    }

    public function test_pending_page_signs_every_subject_that_shares_the_template_in_one_go(): void
    {
        $template = $this->requireContract();
        $child = $this->childCategory($this->scenario->category, 'Spices');
        $childProduct = $this->productIn($child);
        $variant = $childProduct->variants()->create(['sku' => 'CH-'.uniqid(), 'variant_name' => 'x', 'is_default' => true, 'is_active' => true, 'position' => 0]);
        VendorListing::create([
            'id' => (string) Str::uuid(), 'vendor_id' => $this->scenario->vendor->id, 'product_variant_id' => $variant->id, 'country_id' => $this->scenario->country->id,
            'price' => 10, 'currency' => 'AED', 'condition' => 'new', 'fulfillment_model' => 'fbm', 'status' => 'active',
        ]);
        $owner = $this->ownerFor($this->scenario->vendor);

        $this->actingAs($owner, 'vendor')
            ->get(route('partner.contracts.pending'))
            ->assertOk()
            ->assertSee('Food terms');

        $this->actingAs($owner, 'vendor')
            ->post(route('partner.contracts.accept', $template), ['signature_name' => 'Owner', 'language' => 'en', 'agreed' => '1'])
            ->assertRedirect();

        $this->assertCount(0, app(CategoryContractService::class)->pendingForVendor($this->scenario->vendor));
        $this->assertSame(1, VendorContract::where('vendor_id', $this->scenario->vendor->id)->count(), 'parent and sub-category share one signature');
    }
}
