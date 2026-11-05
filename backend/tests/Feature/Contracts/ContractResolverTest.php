<?php

namespace Tests\Feature\Contracts;

use App\Exceptions\ContractNotConfiguredException;
use App\Exceptions\ContractRequiredException;
use App\Models\Category;
use App\Models\VendorContract;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ContractResolver;
use App\Services\Vendor\SellerAgreementService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CountryContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Requirement 3 + 4 rules: one global seller contract per country, optional individual contracts per
 * product / category, country-scoped selection with no silent fallback to another country.
 */
class ContractResolverTest extends TestCase
{
    use CountryContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->vendor->update(['global_status' => 'active', 'onboarding_completed_at' => now(), 'vendor_type' => 'product_vendor']);
    }

    private function resolver(): ContractResolver
    {
        return app(ContractResolver::class);
    }

    private function contracts(): CategoryContractService
    {
        return app(CategoryContractService::class);
    }

    private function agreements(): SellerAgreementService
    {
        return app(SellerAgreementService::class);
    }

    private function sign($vendor, $category, string $name = 'Jane'): VendorContract
    {
        return $this->contracts()->sign($vendor, null, 'product', $category->id, 'en', $name, '127.0.0.1', 'phpunit');
    }

    // ── Requirement 3: global by default, individual only by explicit switch ──

    public function test_one_global_contract_covers_every_product_with_no_per_product_signing(): void
    {
        $home = $this->scenario->country;
        $agreement = $this->contractTemplate('platform', 'Seller Agreement', $home);
        $category = $this->scenario->category;
        $product = $this->scenario->product;

        $resolved = $this->resolver()->resolveForVendorProduct($this->scenario->vendor, $product);

        $this->assertFalse($resolved->isIndividual());
        $this->assertSame($agreement->id, $resolved->template->id, 'the global agreement of the seller country covers it');
        $this->assertCount(0, $this->contracts()->pendingForVendor($this->scenario->vendor));
        $this->assertNull($this->contracts()->unsignedForProduct($this->scenario->vendor, $product));

        // A template linked to the category but with the switch explicitly OFF is not enforced.
        $template = $this->contractTemplate('product', 'Perfume terms');
        $category->forceFill(['contract_template_id' => $template->id, 'requires_contract' => false])->save();

        $this->assertFalse($this->resolver()->resolveForVendorProduct($this->scenario->vendor, $product)->isIndividual());
        $this->assertCount(0, $this->contracts()->pendingForVendor($this->scenario->vendor));
    }

    public function test_category_switch_on_needs_a_separate_signature_that_the_global_agreement_does_not_replace(): void
    {
        $home = $this->scenario->country;
        $this->contractTemplate('platform', 'Seller Agreement', $home);
        $template = $this->contractTemplate('product', 'Food terms');
        $category = $this->scenario->category;
        $category->forceFill(['contract_template_id' => $template->id, 'requires_contract' => true])->save();

        // The seller signs the global agreement; the individual contract is still missing.
        $this->agreements()->sign($this->scenario->vendor, null, $this->agreements()->latestPublished($home->id), 'en', 'Jane', '127.0.0.1', 'phpunit');

        $pending = $this->contracts()->pendingForVendor($this->scenario->vendor);
        $this->assertCount(1, $pending);
        $this->assertSame($template->id, $pending->first()['template']->id);

        try {
            $this->contracts()->assertCoveredForProduct($this->scenario->vendor, $this->scenario->product);
            $this->fail('A product that requires an individual contract must not pass with the global agreement only.');
        } catch (ContractRequiredException $e) {
            $this->assertSame('contract_required', $e->errorCode());
            $this->assertSame($template->id, $e->contract['template_id']);
        }

        $contract = $this->sign($this->scenario->vendor, $category);
        $this->assertSame($home->id, $contract->country_id, 'the signature records the country it was resolved for');
        $this->assertCount(0, $this->contracts()->pendingForVendor($this->scenario->vendor));
        $this->contracts()->assertCoveredForProduct($this->scenario->vendor, $this->scenario->product);
    }

    public function test_double_submit_returns_the_existing_signature_and_never_duplicates(): void
    {
        $template = $this->contractTemplate('product', 'Food terms');
        $this->scenario->category->forceFill(['contract_template_id' => $template->id, 'requires_contract' => true])->save();

        $first = $this->sign($this->scenario->vendor, $this->scenario->category);
        $second = $this->sign($this->scenario->vendor, $this->scenario->category);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, VendorContract::where('vendor_id', $this->scenario->vendor->id)->count());

        $agreement = $this->contractTemplate('platform', 'Seller Agreement', $this->scenario->country);
        $one = $this->agreements()->sign($this->scenario->vendor, null, $agreement, 'en', 'Jane', '127.0.0.1', 'phpunit');
        $two = $this->agreements()->sign($this->scenario->vendor, null, $agreement, 'en', 'Jane', '127.0.0.1', 'phpunit');
        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, VendorContract::where('category_scope', 'platform')->count());
    }

    public function test_database_rejects_a_duplicate_active_signature(): void
    {
        $template = $this->contractTemplate('product', 'Food terms');
        $this->scenario->category->forceFill(['contract_template_id' => $template->id, 'requires_contract' => true])->save();
        $contract = $this->sign($this->scenario->vendor, $this->scenario->category);

        $this->expectException(QueryException::class);
        VendorContract::create($contract->only([
            'vendor_id', 'category_scope', 'product_category_id', 'contract_template_id', 'template_version', 'language_signed',
            'rendered_content', 'rendered_content_hash', 'variables', 'signer_name', 'signed_at', 'status', 'acceptance_method',
        ]));
    }

    public function test_product_override_required_and_exempt_beat_the_category_switch(): void
    {
        $template = $this->contractTemplate('product', 'Food terms');
        $category = $this->scenario->category;

        // Category OFF, product required: the product needs a contract (its category template is not used because the switch is off,
        // so the product has to carry its own).
        $category->forceFill(['requires_contract' => false])->save();
        $licensed = $this->productIn($category, 'required');
        $licensed->forceFill(['contract_template_id' => $template->id])->save();

        $resolved = $this->resolver()->resolveForVendorProduct($this->scenario->vendor, $licensed);
        $this->assertTrue($resolved->isIndividual());
        $this->assertSame($licensed->id, $resolved->subject->getKey());

        // Category ON, product exempt: covered by the global agreement.
        $category->forceFill(['requires_contract' => true, 'contract_template_id' => $template->id])->save();
        $exempt = $this->productIn($category, 'exempt');
        $this->assertFalse($this->resolver()->resolveForVendorProduct($this->scenario->vendor, $exempt)->isIndividual());

        // Inherit follows the category.
        $inherit = $this->productIn($category);
        $this->assertTrue($this->resolver()->resolveForVendorProduct($this->scenario->vendor, $inherit)->isIndividual());

        // Product-level signature is recorded against the product and covers only it.
        $contract = $this->contracts()->sign($this->scenario->vendor, null, 'product', $category->id, 'en', 'Jane', '127.0.0.1', 'phpunit', null, $licensed->id);
        $this->assertSame($licensed->id, $contract->product_id);
        $this->assertNull($contract->product_category_id);
        $this->assertNull($this->contracts()->unsignedForProduct($this->scenario->vendor, $licensed));
        $this->assertNotNull($this->contracts()->unsignedForProduct($this->scenario->vendor, $inherit));
    }

    public function test_sub_categories_inherit_the_parent_requirement_and_one_signature_covers_all_of_them(): void
    {
        $template = $this->contractTemplate('product', 'Food terms');
        $parent = $this->scenario->category;
        $parent->forceFill(['contract_template_id' => $template->id, 'requires_contract' => true])->save();
        $childA = $this->childCategory($parent, 'Spices');
        $childB = $this->childCategory($parent, 'Blends');
        $productA = $this->productIn($childA);
        $productB = $this->productIn($childB);

        $resolved = $this->resolver()->resolveForVendorProduct($this->scenario->vendor, $productA);
        $this->assertTrue($resolved->isIndividual());
        $this->assertSame($parent->id, $resolved->subject->getKey(), 'the signature belongs to the category that carries the contract');

        $this->contracts()->sign($this->scenario->vendor, null, 'product', $childA->id, 'en', 'Jane', '127.0.0.1', 'phpunit');

        $this->assertNull($this->contracts()->unsignedForProduct($this->scenario->vendor, $productA));
        $this->assertNull($this->contracts()->unsignedForProduct($this->scenario->vendor, $productB), 'sibling sub-category is covered by the same signature');
        $this->assertSame(1, VendorContract::where('product_category_id', $parent->id)->count());
    }

    // ── Requirement 4: country-aware selection, no silent fallback ───────────

    public function test_different_countries_receive_different_contracts(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');
        $uae = $this->scenario->country;
        $omaniSeller = $this->vendorIn($oman);
        $emirati = $this->scenario->vendor;

        $uaeAgreement = $this->contractTemplate('platform', 'Seller Agreement', $uae);
        $omanAgreement = $this->contractTemplate('platform', 'Seller Agreement', $oman);
        $this->assertSame($uaeAgreement->id, $this->agreements()->latestPublished($uae->id)->id);
        $this->assertSame($omanAgreement->id, $this->agreements()->pendingFor($omaniSeller)->id);
        $this->assertSame($uaeAgreement->id, $this->agreements()->pendingFor($emirati)->id);

        $uaeFood = $this->contractTemplate('product', 'Food terms', $uae);
        $omanFood = $this->contractTemplate('product', 'Food terms', $oman);
        $category = $this->scenario->category;
        $category->forceFill(['requires_contract' => true])->save();
        $this->assignCategoryTemplate($category, $uae, $uaeFood);
        $this->assignCategoryTemplate($category, $oman, $omanFood);

        $this->assertSame($uaeFood->id, $this->resolver()->resolveForVendorProduct($emirati, $this->scenario->product)->template->id);
        $this->assertSame($omanFood->id, $this->resolver()->resolveForVendorProduct($omaniSeller, $this->scenario->product)->template->id);
    }

    public function test_missing_country_contract_is_a_clear_error_and_never_falls_back_to_another_country(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');
        $omaniSeller = $this->vendorIn($oman);
        $uae = $this->scenario->country;

        // Platform agreement: only the UAE has one, so Oman is "not configured" (strict mode used by registration).
        $this->contractTemplate('platform', 'Seller Agreement', $uae);
        $this->assertNull($this->agreements()->latestPublished($oman->id), 'an Omani seller never gets the UAE agreement');
        try {
            $this->resolver()->platformAgreementFor($oman, strict: true);
            $this->fail('Expected ContractNotConfiguredException');
        } catch (ContractNotConfiguredException $e) {
            $this->assertSame('contract_not_configured', $e->errorCode());
            $this->assertStringContainsString('Oman', $e->getMessage());
        }

        // Individual contract: the category requires one, only the UAE configured it.
        $food = $this->contractTemplate('product', 'Food terms', $uae);
        $category = $this->scenario->category;
        $category->forceFill(['requires_contract' => true])->save();
        $this->assignCategoryTemplate($category, $uae, $food);

        $this->expectException(ContractNotConfiguredException::class);
        $this->resolver()->resolveForVendorProduct($omaniSeller, $this->scenario->product);
    }

    public function test_pending_list_skips_unconfigured_countries_but_listing_is_blocked_with_the_typed_error(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');
        $omaniSeller = $this->vendorIn($oman);
        $food = $this->contractTemplate('product', 'Food terms', $this->scenario->country);
        $category = $this->scenario->category;
        $category->forceFill(['requires_contract' => true])->save();
        $this->assignCategoryTemplate($category, $this->scenario->country, $food);

        $this->assertCount(0, $this->contracts()->pendingForVendor($omaniSeller));

        $this->expectException(ContractNotConfiguredException::class);
        $this->contracts()->assertCoveredForProduct($omaniSeller, $this->scenario->product);
    }

    public function test_legacy_country_less_template_keeps_working_for_every_country_until_a_specific_one_exists(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');
        $omaniSeller = $this->vendorIn($oman);
        $legacy = $this->contractTemplate('product', 'Old terms');
        $category = $this->scenario->category;
        $category->forceFill(['contract_template_id' => $legacy->id])->save(); // requires_contract stays NULL: legacy link

        $this->assertTrue($category->fresh()->requiresIndividualContract());
        $this->assertSame($legacy->id, $this->resolver()->resolveForVendorProduct($this->scenario->vendor, $this->scenario->product)->template->id);
        $this->assertSame($legacy->id, $this->resolver()->resolveForVendorProduct($omaniSeller, $this->scenario->product)->template->id);

        // Legacy platform agreement also serves every country, then a specific one takes over for its own country only.
        $legacyAgreement = $this->contractTemplate('platform', 'Seller Agreement');
        $this->assertSame($legacyAgreement->id, $this->agreements()->latestPublished($oman->id)->id);
        $omanAgreement = $this->contractTemplate('platform', 'Seller Agreement', $oman);
        $this->assertSame($omanAgreement->id, $this->agreements()->latestPublished($oman->id)->id);
        $this->assertSame($legacyAgreement->id, $this->agreements()->latestPublished($this->scenario->country->id)->id, 'other countries keep the legacy agreement');

        // A country-specific category contract wins over the legacy link for its country.
        $omanFood = $this->contractTemplate('product', 'Food terms', $oman);
        $this->assignCategoryTemplate($category, $oman, $omanFood);
        $this->assertSame($omanFood->id, $this->resolver()->resolveForVendorProduct($omaniSeller, $this->scenario->product)->template->id);
        $this->assertSame($legacy->id, $this->resolver()->resolveForVendorProduct($this->scenario->vendor, $this->scenario->product)->template->id);
    }

    public function test_a_contract_of_another_country_linked_by_mistake_is_never_used(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');
        $omanFood = $this->contractTemplate('product', 'Food terms', $oman);
        $category = $this->scenario->category;
        $category->forceFill(['contract_template_id' => $omanFood->id, 'requires_contract' => true])->save();

        $this->expectException(ContractNotConfiguredException::class);
        $this->resolver()->resolveForVendorProduct($this->scenario->vendor, $this->scenario->product);
    }

    public function test_country_default_contract_serves_a_required_category_without_its_own_assignment(): void
    {
        $uae = $this->scenario->country;
        $default = $this->contractTemplate('product', 'UAE product terms', $uae, default: true);
        $this->scenario->category->forceFill(['requires_contract' => true])->save();

        $resolved = $this->resolver()->resolveForVendorProduct($this->scenario->vendor, $this->scenario->product);
        $this->assertSame($default->id, $resolved->template->id);
        $this->assertSame($this->scenario->category->id, $resolved->subject->getKey());
    }

    public function test_only_one_published_default_per_country_and_scope_in_the_database(): void
    {
        $uae = $this->scenario->country;
        $this->contractTemplate('product', 'First default', $uae, default: true);

        $this->expectException(QueryException::class);
        $this->contractTemplate('product', 'Second default', $uae, default: true);
    }

    public function test_publishing_a_default_replaces_the_previous_default_of_that_country_only(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $old = $this->contractTemplate('product', 'Old default', $uae, default: true);
        $omanDefault = $this->contractTemplate('product', 'Oman default', $oman, default: true);
        $new = $this->contractTemplate('product', 'New default', $uae, published: false, default: true);

        $this->contracts()->publish($new);

        $this->assertFalse($old->fresh()->is_country_default);
        $this->assertTrue($new->fresh()->is_published);
        $this->assertTrue($new->fresh()->is_country_default);
        $this->assertTrue($omanDefault->fresh()->is_country_default, "another country's default is untouched");
        $this->assertTrue($omanDefault->fresh()->is_published);
    }

    // ── Versions, lineages, signatures ───────────────────────────────────────

    public function test_publishing_in_one_country_never_retires_another_countrys_agreement_and_keeps_signatures(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $omaniSeller = $this->vendorIn($oman);

        $uaeV1 = $this->contractTemplate('platform', 'Seller Agreement', $uae);
        $omanV1 = $this->contractTemplate('platform', 'Seller Agreement', $oman);
        $signature = $this->agreements()->sign($this->scenario->vendor, null, $uaeV1, 'en', 'Jane', '127.0.0.1', 'phpunit');
        $omanSignature = $this->agreements()->sign($omaniSeller, null, $omanV1, 'en', 'Omar', '127.0.0.1', 'phpunit');

        $uaeV2 = $this->contractTemplate('platform', 'Seller Agreement', $uae, 2, published: false);
        $this->agreements()->publish($uaeV2);

        $this->assertFalse($uaeV1->fresh()->is_published, 'the UAE version is replaced');
        $this->assertTrue($uaeV2->fresh()->is_published);
        $this->assertTrue($omanV1->fresh()->is_published, "Oman's agreement stays published");
        $this->assertTrue($omanV1->fresh()->is_active);
        $this->assertNull($this->agreements()->pendingFor($omaniSeller), 'the Omani seller is not asked to sign again');

        // The UAE seller must accept v2, and their v1 signature is preserved (not edited, not deleted).
        $this->assertSame($uaeV2->id, $this->agreements()->pendingFor($this->scenario->vendor)->id);
        $this->assertSame('active', $signature->fresh()->status);
        $this->assertSame(1, $signature->fresh()->template_version);
        $this->assertSame('active', $omanSignature->fresh()->status);

        $this->agreements()->sign($this->scenario->vendor, null, $uaeV2, 'en', 'Jane', '127.0.0.1', 'phpunit');
        $this->assertSame('superseded', $signature->fresh()->status, 'older signature is archived, never deleted');
        $this->assertSame(2, VendorContract::where('vendor_id', $this->scenario->vendor->id)->count());
    }

    public function test_versions_are_numbered_and_published_per_country_for_category_contracts(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $uaeV1 = $this->contractTemplate('product', 'Food terms', $uae);
        $omanV1 = $this->contractTemplate('product', 'Food terms', $oman);
        $uaeV2 = $this->contractTemplate('product', 'Food terms', $uae, 2, published: false);

        $this->contracts()->publish($uaeV2);

        $this->assertFalse($uaeV1->fresh()->is_published);
        $this->assertTrue($uaeV2->fresh()->is_published);
        $this->assertTrue($omanV1->fresh()->is_published, "publishing in the UAE never retires Oman's contract of the same name");
    }

    public function test_publishing_a_new_category_contract_version_moves_assignments_and_asks_signed_sellers_to_resign(): void
    {
        $uae = $this->scenario->country;
        $v1 = $this->contractTemplate('product', 'Food terms', $uae);
        $category = $this->scenario->category;
        $category->forceFill(['requires_contract' => true])->save();
        $this->assignCategoryTemplate($category, $uae, $v1);
        $signature = $this->sign($this->scenario->vendor, $category);

        $v2 = $this->contractTemplate('product', 'Food terms', $uae, 2, published: false);
        $vendors = $this->contracts()->publish($v2);

        $this->assertTrue($vendors->contains('id', $this->scenario->vendor->id));
        $this->assertSame($v2->id, $category->contractAssignments()->where('country_id', $uae->id)->value('contract_template_id'));
        $this->assertSame('active', $signature->fresh()->status, 'the old signature is preserved');
        $this->assertCount(1, $this->contracts()->pendingForVendor($this->scenario->vendor), 'the seller must sign the new version');
    }

    public function test_a_vendor_who_signed_the_legacy_agreement_must_accept_the_agreement_of_their_new_country_lineage(): void
    {
        $uae = $this->scenario->country;
        $legacy = $this->contractTemplate('platform', 'Seller Agreement');
        $this->agreements()->sign($this->scenario->vendor, null, $legacy, 'en', 'Jane', '127.0.0.1', 'phpunit');
        $this->assertNull($this->agreements()->pendingFor($this->scenario->vendor));

        $specific = $this->contractTemplate('platform', 'Seller Agreement', $uae, mode: 'notice');

        $pending = $this->agreements()->pendingFor($this->scenario->vendor);
        $this->assertSame($specific->id, $pending->id, 'a different agreement is never accepted implicitly');
        $this->assertSame('active', VendorContract::where('contract_template_id', $legacy->id)->value('status'), 'the legacy signature is untouched');
    }

    public function test_notice_period_is_applied_per_country_lineage(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $omaniSeller = $this->vendorIn($oman);

        $uaeV1 = $this->contractTemplate('platform', 'Seller Agreement', $uae);
        $omanV1 = $this->contractTemplate('platform', 'Seller Agreement', $oman);
        $this->agreements()->sign($this->scenario->vendor, null, $uaeV1, 'en', 'Jane', '127.0.0.1', 'phpunit');
        $this->agreements()->sign($omaniSeller, null, $omanV1, 'en', 'Omar', '127.0.0.1', 'phpunit');

        $uaeV2 = $this->contractTemplate('platform', 'Seller Agreement', $uae, 2, published: false, mode: 'notice');
        $this->agreements()->publish($uaeV2);
        $this->assertTrue($uaeV2->fresh()->isScheduled());
        $this->assertTrue($uaeV1->fresh()->is_published, 'the old version stays in force during the notice period');

        $this->travel(15)->days();
        $recorded = $this->agreements()->applyEffective();

        $this->assertSame(1, $recorded, 'only the UAE seller is affected');
        $this->assertSame(2, VendorContract::where('vendor_id', $this->scenario->vendor->id)->count());
        $this->assertSame(1, VendorContract::where('vendor_id', $omaniSeller->id)->count());
        $this->assertTrue($omanV1->fresh()->is_published);
        $this->assertFalse($uaeV1->fresh()->is_published);
    }

    public function test_countries_without_an_agreement_are_reported_for_the_admin_warning(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');
        $this->vendorIn($oman);
        $this->contractTemplate('platform', 'Seller Agreement', $this->scenario->country);

        $missing = $this->resolver()->countriesWithoutPlatformAgreement();

        $this->assertSame(['Oman'], $missing->pluck('name_en')->all());
    }

    // ── Migration backfill ───────────────────────────────────────────────────

    public function test_requires_contract_backfill_keeps_existing_behaviour(): void
    {
        $template = $this->contractTemplate('product', 'Old terms');
        $linked = Category::factory()->create(['contract_template_id' => $template->id]);
        $plain = Category::factory()->create();
        DB::table('categories')->whereIn('id', [$linked->id, $plain->id])->update(['requires_contract' => null]);

        $migration = require database_path('migrations/2026_11_05_100100_add_contract_switches_and_country_assignments.php');
        $migration->backfill();

        $this->assertTrue((bool) DB::table('categories')->where('id', $linked->id)->value('requires_contract'));
        $this->assertFalse((bool) DB::table('categories')->where('id', $plain->id)->value('requires_contract'));

        // Idempotent: an explicit admin choice made afterwards is never overwritten.
        DB::table('categories')->where('id', $linked->id)->update(['requires_contract' => false]);
        $migration->backfill();
        $this->assertFalse((bool) DB::table('categories')->where('id', $linked->id)->value('requires_contract'));
    }
}
