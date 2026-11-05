<?php

namespace Tests\Feature\Contracts;

use App\Enums\ContractKind;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ContractDisplayDestination;
use App\Models\Marketer;
use App\Models\MarketerContract;
use App\Models\MarketerContractAcceptance;
use App\Models\MarketerContractVersion;
use App\Models\MarketerProfile;
use App\Models\MarketerSellerContract;
use App\Models\Vendor;
use App\Models\VendorContract;
use App\Services\Marketer\MarketerCollaborationContractService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\CollaborationContractFixtures;
use Tests\Support\CountryContractFixtures;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Requirement 6: contract kind, Display On destinations, which contracts a marketer profile shows, filters,
 * and who may see them.
 */
class MarketerCollaborationContractTest extends TestCase
{
    use CollaborationContractFixtures;
    use CountryContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Cache::flush();

        foreach (['classifieds.view', 'marketers.view', 'marketers.manage', 'vendors.view', 'vendors.assigned_only'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $this->s = MarketplaceScenario::make()->build();
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['classifieds.view', 'marketers.view']);
    }

    private function service(): MarketerCollaborationContractService
    {
        return app(MarketerCollaborationContractService::class);
    }

    private function destinationId(string $key): string
    {
        return ContractDisplayDestination::where('key', $key)->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Influencer collaboration',
            'category_scope' => 'marketer',
            'content_mode' => 'editor',
            'content_en' => '<p>Terms</p>',
            'content_ar' => '<p>الشروط</p>',
        ];
    }

    // ── Kind and destinations ────────────────────────────────────────────────

    public function test_contract_kind_follows_the_scope_and_destinations_are_database_driven(): void
    {
        $platform = $this->contractTemplate('platform', 'Seller Agreement');
        $category = $this->contractTemplate('classified', 'Cars');
        $collab = $this->collaborationTemplate('Collab');

        $this->assertSame(ContractKind::SellerPlatform, $platform->fresh()->contract_kind);
        $this->assertSame(ContractKind::SellerCategory, $category->fresh()->contract_kind);
        $this->assertSame(ContractKind::SellerMarketer, $collab->fresh()->contract_kind);

        $keys = ContractDisplayDestination::pluck('target_type', 'key');
        $this->assertSame('general', $keys['general']);
        $this->assertSame('vendor_profile', $keys['seller_profile']);
        $this->assertSame(['influencer', 'broker', 'affiliate'], ContractDisplayDestination::where('target_type', 'marketer_job')->orderBy('sort_order')->pluck('marketer_job_key')->all());
    }

    public function test_admin_selects_destinations_and_they_persist_with_an_audit_entry(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.contracts.templates.store'), $this->form([
            'destination_ids' => [$this->destinationId('influencer_profile'), $this->destinationId('broker_profile')],
            'show_on_public_profile' => 1,
        ]));

        $response->assertSessionHasNoErrors()->assertRedirect();
        $template = ClassifiedContractTemplate::where('name', 'Influencer collaboration')->sole();

        $this->assertSame(ContractKind::SellerMarketer, $template->contract_kind);
        $this->assertSame('marketer', $template->category_scope);
        $this->assertTrue($template->show_on_public_profile);
        $this->assertEqualsCanonicalizing(['influencer_profile', 'broker_profile'], $template->displayDestinations()->pluck('key')->all());
        $this->assertTrue(Activity::where('log_name', 'contracts')->where('event', 'contract_template.display_changed')->exists());

        $this->actingAs($this->admin, 'admin')->put(route('admin.contracts.templates.update', $template), $this->form([
            'destination_ids' => [$this->destinationId('general')],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['general'], $template->fresh()->displayDestinations()->pluck('key')->all());
    }

    public function test_a_collaboration_template_needs_a_destination_or_a_marketer(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.contracts.templates.store'), $this->form())
            ->assertSessionHasErrors('destination_ids');
        $this->actingAs($this->admin, 'admin')->post(route('admin.contracts.templates.store'), $this->form(['destination_ids' => [(string) Str::uuid()]]))
            ->assertSessionHasErrors('destination_ids.0');

        $this->assertSame(0, ClassifiedContractTemplate::where('category_scope', 'marketer')->count());
    }

    public function test_the_templates_index_filters_by_kind_and_destination_and_warns_about_uncovered_types(): void
    {
        $this->collaborationTemplate('Influencer deal', ['influencer_profile']);
        $this->collaborationTemplate('Broker deal', ['broker_profile']);
        $this->contractTemplate('classified', 'Cars');

        $names = fn ($response) => $response->viewData('templates')->pluck('name')->all();

        $byKind = $this->actingAs($this->admin, 'admin')->get(route('admin.contracts.templates.index', ['kind' => 'seller_marketer']))->assertOk();
        $this->assertEqualsCanonicalizing(['Influencer deal', 'Broker deal'], $names($byKind));

        $byDestination = $this->actingAs($this->admin, 'admin')->get(route('admin.contracts.templates.index', ['destination' => 'broker_profile']))->assertOk();
        $this->assertSame(['Broker deal'], $names($byDestination));

        $this->actingAs($this->admin, 'admin')->get(route('admin.contracts.templates.index'))
            ->assertSee('uncovered-marketer-types', false)
            ->assertSee('ملف المسوق بالعمولة');
    }

    public function test_publishing_a_new_version_retires_only_the_older_version_of_the_same_country(): void
    {
        $kuwait = $this->otherCountry();
        $v1 = $this->collaborationTemplate('Deal', ['influencer_profile']);
        $kuwaitV1 = $this->collaborationTemplate('Deal', ['influencer_profile'], $kuwait);
        $v2 = $this->collaborationTemplate('Deal', ['influencer_profile'], null, ['version' => 2, 'is_published' => false, 'published_at' => null, 'effective_at' => null]);

        $this->actingAs($this->admin, 'admin')->post(route('admin.contracts.templates.publish', $v2))->assertSessionHasNoErrors();

        $this->assertTrue($v2->fresh()->is_published);
        $this->assertFalse($v1->fresh()->is_published);
        $this->assertTrue($kuwaitV1->fresh()->is_published, 'another country is never touched');
    }

    // ── Which contracts a profile shows ──────────────────────────────────────

    public function test_profiles_show_only_the_contracts_of_their_marketer_type_and_may_have_several(): void
    {
        $influencer = F::influencer($this->s);
        $broker = V::broker($this->s);
        $influencerOne = $this->collaborationTemplate('Influencer one', ['influencer_profile']);
        $influencerTwo = $this->collaborationTemplate('Influencer two', ['influencer_profile', 'general']);
        $brokerOnly = $this->collaborationTemplate('Broker only', ['broker_profile']);
        $this->collaborationTemplate('Seller page only', ['seller_profile']);

        $this->assertEqualsCanonicalizing([$influencerOne->id, $influencerTwo->id], $this->service()->applicableTo($influencer)->pluck('id')->all());
        $this->assertSame([$brokerOnly->id], $this->service()->applicableTo($broker)->pluck('id')->all());
    }

    public function test_a_template_linked_to_specific_marketers_applies_to_them_only(): void
    {
        $one = F::influencer($this->s);
        $two = F::influencer($this->s);
        $template = $this->collaborationTemplate('Exclusive terms', ['influencer_profile']);
        $template->marketers()->sync([$one->id]);

        $this->assertSame([$template->id], $this->service()->applicableTo($one)->pluck('id')->all());
        $this->assertSame([], $this->service()->applicableTo($two)->pluck('id')->all());
    }

    public function test_drafts_retired_and_scheduled_contracts_do_not_apply(): void
    {
        $influencer = F::influencer($this->s);
        $this->collaborationTemplate('Draft', ['influencer_profile'], null, ['is_published' => false]);
        $this->collaborationTemplate('Retired', ['influencer_profile'], null, ['is_active' => false]);
        $this->collaborationTemplate('Scheduled', ['influencer_profile'], null, ['effective_at' => now()->addWeek()]);
        $live = $this->collaborationTemplate('Live', ['influencer_profile']);

        $this->assertSame([$live->id], $this->service()->applicableTo($influencer)->pluck('id')->all());
    }

    public function test_country_specific_contracts_override_legacy_ones_and_never_cross_countries(): void
    {
        $kuwait = $this->otherCountry();
        $legacy = $this->collaborationTemplate('Deal', ['influencer_profile']);
        $own = $this->collaborationTemplate('Deal', ['influencer_profile'], $this->s->country, ['version' => 3]);
        $kuwaitOnly = $this->collaborationTemplate('Kuwait only', ['influencer_profile'], $kuwait);

        $countryId = $this->s->country->id;
        $this->assertSame([$own->id], $this->service()->resolve(['influencer'], null, $countryId)->pluck('id')->all(), 'own country wins over legacy of the same name');
        $this->assertSame([$legacy->id], $this->service()->resolve(['influencer'], null, $kuwait->id)->where('name', 'Deal')->pluck('id')->all(), 'a country without its own uses the legacy contract');
        $this->assertNotContains($kuwaitOnly->id, $this->service()->resolve(['influencer'], null, $countryId)->pluck('id')->all());
        $this->assertSame([], $this->service()->resolve(['influencer'], null, (string) Str::uuid())->where('country_id', '!=', null)->all());
    }

    public function test_a_category_scoped_contract_only_applies_to_listings_of_that_category(): void
    {
        $cars = ClassifiedCategory::create(['name_en' => 'Cars', 'name_ar' => 'سيارات', 'slug' => 'cars-'.Str::lower(Str::random(5)), 'is_active' => true, 'sort_order' => 1]);
        $sedans = ClassifiedCategory::create(['name_en' => 'Sedans', 'name_ar' => 'سيدان', 'slug' => 'sedans-'.Str::lower(Str::random(5)), 'is_active' => true, 'sort_order' => 2]);
        $sedans->forceFill(['parent_id' => $cars->id])->save();
        $homes = ClassifiedCategory::create(['name_en' => 'Homes', 'name_ar' => 'عقار', 'slug' => 'homes-'.Str::lower(Str::random(5)), 'is_active' => true, 'sort_order' => 3]);
        $carsDeal = $this->collaborationTemplate('Cars deal', ['influencer_profile'], null, ['classified_category_id' => $cars->id]);
        $anyDeal = $this->collaborationTemplate('Any deal', ['influencer_profile']);

        $vendor = $this->s->vendor;
        $influencer = F::influencer($this->s);
        $ids = fn ($categoryId) => $this->service()->requirementsFor($vendor, [$influencer->id], [], $categoryId, true)->keys()->all();

        $this->assertEqualsCanonicalizing([$carsDeal->id, $anyDeal->id], $ids($sedans->id), 'a sub-category inherits the parent category contract');
        $this->assertSame([$anyDeal->id], $ids($homes->id));
        $this->assertSame([$anyDeal->id], $this->service()->requirementsFor($vendor, [$influencer->id], [], null, false)->keys()->all(), 'a non-classified campaign never gets a category contract');
    }

    // ── Admin profile ────────────────────────────────────────────────────────

    public function test_the_admin_profile_lists_applicable_signed_onboarding_and_exclusive_contracts_with_filters(): void
    {
        $influencer = F::influencer($this->s);
        $template = $this->collaborationTemplate('Influencer deal', ['influencer_profile']);
        $this->collaborationTemplate('Broker deal', ['broker_profile']);
        $signed = $this->signedCollaboration($this->s->vendor, $influencer, $template);
        $revoked = $this->signedCollaboration($this->s->vendor, $influencer, $template, ['status' => 'revoked', 'signed_at' => now()->subYear()]);

        $contract = MarketerContract::create(['marketer_id' => $influencer->id, 'current_version' => 1, 'is_required' => true]);
        MarketerContractVersion::create(['marketer_contract_id' => $contract->id, 'version_number' => 1, 'content_type' => 'text', 'text_content' => 'Terms', 'title_en' => 'Onboarding', 'is_active' => true]);

        $page = $this->actingAs($this->admin, 'admin')->get(route('admin.marketers.show', $influencer))->assertOk();
        $page->assertSee('Influencer deal')->assertDontSee('Broker deal');
        $this->assertSame(2, $page->viewData('signedContracts')->total());
        $this->assertSame(1, $page->viewData('applicableContracts')->total());
        $this->assertNotNull($page->viewData('onboardingContract'));

        $active = $this->actingAs($this->admin, 'admin')->get(route('admin.marketers.show', ['marketer' => $influencer, 'contract_status' => 'active']));
        $this->assertSame([$signed->id], $active->viewData('signedContracts')->pluck('id')->all());

        $ended = $this->actingAs($this->admin, 'admin')->get(route('admin.marketers.show', ['marketer' => $influencer, 'contract_status' => 'ended']));
        $this->assertSame([$revoked->id], $ended->viewData('signedContracts')->pluck('id')->all());
        $this->assertNull($ended->viewData('onboardingContract'));

        $onboardingOnly = $this->actingAs($this->admin, 'admin')->get(route('admin.marketers.show', ['marketer' => $influencer, 'contract_type' => 'onboarding']));
        $this->assertSame(0, $onboardingOnly->viewData('signedContracts')->total());
        $this->assertNotNull($onboardingOnly->viewData('onboardingContract'));

        $dated = $this->actingAs($this->admin, 'admin')->get(route('admin.marketers.show', ['marketer' => $influencer, 'contract_from' => now()->subMonth()->toDateString()]));
        $this->assertSame([$signed->id], $dated->viewData('signedContracts')->pluck('id')->all());

        $other = $this->otherCountry();
        $byCountry = $this->actingAs($this->admin, 'admin')->get(route('admin.marketers.show', ['marketer' => $influencer, 'contract_country' => $other->id]));
        $this->assertSame(0, $byCountry->viewData('signedContracts')->total());
    }

    public function test_the_admin_profile_filters_signed_contracts_by_classified_category(): void
    {
        $influencer = F::influencer($this->s);
        $cars = ClassifiedCategory::create(['name_en' => 'Cars', 'name_ar' => 'سيارات', 'slug' => 'cars-'.Str::lower(Str::random(5)), 'is_active' => true, 'sort_order' => 1]);
        $carsDeal = $this->collaborationTemplate('Cars deal', ['influencer_profile'], null, ['classified_category_id' => $cars->id]);
        $anyDeal = $this->collaborationTemplate('Any deal', ['influencer_profile']);
        $carsSigned = $this->signedCollaboration($this->s->vendor, $influencer, $carsDeal);
        $this->signedCollaboration($this->s->vendor, $influencer, $anyDeal);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.marketers.show', ['marketer' => $influencer, 'contract_category' => $cars->id]));
        $this->assertSame([$carsSigned->id], $response->viewData('signedContracts')->pluck('id')->all());
        $this->assertSame([$carsDeal->id], $response->viewData('applicableContracts')->pluck('id')->all());
    }

    public function test_the_marketers_list_filters_by_contract(): void
    {
        $with = F::influencer($this->s);
        $without = F::influencer($this->s);
        $template = $this->collaborationTemplate('Deal', ['influencer_profile']);
        $this->signedCollaboration($this->s->vendor, $with, $template);

        $ids = fn (array $query) => $this->actingAs($this->admin, 'admin')->get(route('admin.marketers.index', $query))->assertOk()->viewData('marketers')->pluck('id')->all();

        $yes = $ids(['has_contract' => 'yes']);
        $this->assertContains($with->id, $yes);
        $this->assertNotContains($without->id, $yes);

        $no = $ids(['has_contract' => 'no']);
        $this->assertContains($without->id, $no);
        $this->assertNotContains($with->id, $no);

        $this->assertSame([$with->id], $ids(['contract_type' => 'collaboration']));
        $this->assertSame([$with->id], $ids(['contract_status' => 'active', 'contract_type' => 'collaboration', 'contract_country' => $this->s->country->id]));
        $this->assertSame([], $ids(['contract_status' => 'ended', 'contract_type' => 'collaboration']));
        $this->assertSame([], $ids(['contract_type' => 'exclusive']));
        $this->assertContains($without->id, $ids([]));
    }

    public function test_the_admin_profile_does_not_query_per_contract_row(): void
    {
        $influencer = F::influencer($this->s);
        $template = $this->collaborationTemplate('Deal', ['influencer_profile']);
        $this->signedCollaboration($this->s->vendor, $influencer, $template);

        $count = function () use ($influencer): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin, 'admin')->get(route('admin.marketers.show', $influencer))->assertOk();

            return count(DB::getQueryLog());
        };

        $count();
        $few = $count();
        foreach (range(1, 6) as $i) {
            $vendor = Vendor::factory()->create(['country_id' => $this->s->country->id]);
            $this->signedCollaboration($vendor, $influencer, $template);
        }

        $count();
        $this->assertSame($few, $count(), 'query count is independent of the number of signed contracts');
    }

    public function test_an_admin_without_marketer_permission_cannot_see_the_contracts(): void
    {
        $influencer = F::influencer($this->s);
        $signed = $this->signedCollaboration($this->s->vendor, $influencer, $this->collaborationTemplate('Deal'));
        $nobody = Admin::factory()->create();

        $this->actingAs($nobody, 'admin')->get(route('admin.marketers.show', $influencer))->assertForbidden();
        $this->actingAs($nobody, 'admin')->get(route('admin.marketers.collaboration-contracts.show', [$influencer, $signed]))->assertForbidden();

        $this->actingAs($this->admin, 'admin')->get(route('admin.marketers.collaboration-contracts.show', [$influencer, $signed]))->assertOk()->assertSee($signed->rendered_content, false);

        $otherMarketer = F::influencer($this->s);
        $this->actingAs($this->admin, 'admin')->get(route('admin.marketers.collaboration-contracts.show', [$otherMarketer, $signed]))->assertNotFound();
    }

    // ── Marketer panel, seller panel, public API ─────────────────────────────

    public function test_the_marketer_sees_only_his_own_contracts_and_the_onboarding_page_renders(): void
    {
        $mine = F::influencer($this->s);
        $theirs = F::influencer($this->s);
        $template = $this->collaborationTemplate('Deal', ['influencer_profile']);
        $minerow = $this->signedCollaboration($this->s->vendor, $mine, $template);
        $theirrow = $this->signedCollaboration($this->s->vendor, $theirs, $template);
        $login = F::marketerLogin($mine);

        $contract = MarketerContract::create(['marketer_id' => $mine->id, 'current_version' => 1, 'is_required' => true]);
        MarketerContractVersion::create(['marketer_contract_id' => $contract->id, 'version_number' => 1, 'content_type' => 'text', 'text_content' => 'Onboarding terms', 'title_en' => 'Onboarding', 'is_active' => true]);

        $this->actingAs($login, 'marketer')->get(route('marketer.contract.show'))->assertOk()->assertSee('Onboarding terms');

        $index = $this->actingAs($login, 'marketer')->get(route('marketer.contracts.index'))->assertOk();
        $this->assertSame([$minerow->id], $index->viewData('signed')->pluck('id')->all());
        $this->assertSame([$template->id], $index->viewData('applicable')->pluck('id')->all());

        $this->actingAs($login, 'marketer')->get(route('marketer.contracts.collaboration', $minerow->id))->assertOk()->assertDontSee($minerow->signed_ip);
        $this->actingAs($login, 'marketer')->get(route('marketer.contracts.collaboration', $theirrow->id))->assertNotFound();
    }

    public function test_the_seller_sees_only_the_collaboration_contracts_it_signed(): void
    {
        $marketer = F::influencer($this->s);
        $template = $this->collaborationTemplate('Deal', ['influencer_profile', 'general']);
        $mine = $this->signedCollaboration($this->s->vendor, $marketer, $template);

        $foreignVendor = Vendor::factory()->create(['country_id' => $this->s->country->id]);
        $foreign = $this->signedCollaboration($foreignVendor, $marketer, $template);

        $owner = $this->ownerFor($this->s->vendor);

        $page = $this->actingAs($owner, 'vendor')->get(route('partner.legal.collaborations'))->assertOk();
        $this->assertSame([$mine->id], $page->viewData('signed')->pluck('id')->all());
        $this->assertCount(1, $page->viewData('general'));

        $this->actingAs($owner, 'vendor')->get(route('partner.legal.collaborations.show', $mine->id))->assertOk();
        $this->actingAs($owner, 'vendor')->get(route('partner.legal.collaborations.show', $foreign->id))->assertNotFound();
    }

    public function test_the_public_profile_exposes_only_public_contract_types_and_no_private_data(): void
    {
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $marketer = F::influencer($this->s);
        MarketerProfile::where('marketer_id', $marketer->id)->update(['profile_slug' => 'public-one']);

        $public = $this->collaborationTemplate('Public deal', ['influencer_profile'], null, ['show_on_public_profile' => true]);
        $this->collaborationTemplate('Internal deal', ['influencer_profile'], null, ['show_on_public_profile' => false]);
        $this->signedCollaboration($this->s->vendor, $marketer, $public, ['signer_name' => 'Secret Signer', 'signed_ip' => '203.0.113.9']);

        $response = $this->getJson("/api/public/v1/{$this->s->country->site_code}/marketers/public-one")->assertOk();
        $types = $response->json('data.contract_types');

        $this->assertCount(1, $types);
        $this->assertSame('Public deal', $types[0]['name']);
        $this->assertSame('seller_marketer', $types[0]['kind']);

        $body = $response->getContent();
        foreach (['Internal deal', 'Secret Signer', '203.0.113.9', 'rendered_content', $this->s->vendor->store_name, $public->id] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_seller_platform_marketer_onboarding_and_collaboration_records_stay_distinct(): void
    {
        $marketer = F::influencer($this->s);
        $this->signedCollaboration($this->s->vendor, $marketer, $this->collaborationTemplate('Deal'));

        $this->assertSame(1, MarketerSellerContract::count());
        $this->assertSame(0, VendorContract::count());
        $this->assertSame(0, MarketerContractAcceptance::count());
        $this->assertSame(1, $marketer->sellerContracts()->count());
    }
}
