<?php

namespace Tests\Feature\Vendor;

use App\Jobs\NotifyVendorsOfAgreementUpdateJob;
use App\Mail\SellerAgreementSignedMail;
use App\Mail\SellerAgreementUpdatedMail;
use App\Models\Admin;
use App\Models\ClassifiedContractTemplate;
use App\Models\PortalContent;
use App\Models\Vendor;
use App\Models\VendorContract;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ContractFileService;
use App\Services\Vendor\SellerAgreementService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Support\ContractDocumentFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * The whole life of the platform Seller Agreement: signed at registration, shown in the dashboard,
 * re-accepted by click-wrap or by notice period, and always available as the exact file that was signed.
 */
class SellerAgreementLifecycleTest extends TestCase
{
    use ContractDocumentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->fakePrivateDisk();
        Mail::fake();

        foreach (['classifieds.view', 'vendors.view', 'vendors.assigned_only'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
    }

    private function agreements(): SellerAgreementService
    {
        return app(SellerAgreementService::class);
    }

    private function activeScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->vendor->update(['global_status' => 'active', 'onboarding_completed_at' => now(), 'vendor_type' => 'product_vendor']);

        return $scenario;
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationSession(MarketplaceScenario $scenario, string $email = 'new-seller@example.test'): array
    {
        return ['reg_data' => [
            'step1' => ['name' => 'Amina Seller', 'email' => $email, 'phone' => '+96890000000', 'gender' => 'female', 'country_id' => $scenario->country->id, 'password' => 'password123'],
            'step2' => ['store_name' => 'Amina Store', 'store_slug' => 'amina-store', 'business_type' => 'llc', 'business_name' => 'Amina LLC'],
            'step3' => ['contact_email' => $email, 'contact_phone' => '+96890000000', 'city_id' => $scenario->city->id, 'street_address' => 'Al Khuwair'],
            'documents' => [],
        ]];
    }

    private function completeRegistration(MarketplaceScenario $scenario, array $payload = [])
    {
        return $this->withSession($this->registrationSession($scenario))
            ->postJson(route('portal.register.complete'), $payload + ['terms_agreed' => '1', 'privacy_agreed' => '1']);
    }

    // ── Portal: signing at registration ──────────────────────────────────────

    public function test_registration_cannot_be_submitted_without_signing_the_published_agreement(): void
    {
        $scenario = $this->activeScenario();
        $template = $this->platformFileTemplate();

        $this->completeRegistration($scenario)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['signature_name', 'agreement_language', 'agreement_template_id']);

        $this->completeRegistration($scenario, ['signature_name' => 'A', 'agreement_language' => 'ar', 'agreement_template_id' => $template->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('signature_name');

        $this->assertDatabaseMissing('vendors', ['email' => 'new-seller@example.test']);
    }

    public function test_registration_signs_the_agreement_freezes_the_file_and_emails_a_copy(): void
    {
        $scenario = $this->activeScenario();
        $template = $this->platformFileTemplate();

        $this->completeRegistration($scenario, [
            'signature_name' => 'Amina Al Farsi', 'agreement_language' => 'ar', 'agreement_template_id' => $template->id,
        ])->assertOk()->assertJsonPath('success', true);

        $vendor = Vendor::where('email', 'new-seller@example.test')->firstOrFail();
        $contract = VendorContract::where('vendor_id', $vendor->id)->sole();

        $this->assertSame('platform', $contract->category_scope);
        $this->assertSame('registration', $contract->acceptance_method);
        $this->assertSame('file', $contract->content_mode);
        $this->assertSame('active', $contract->status);
        $this->assertSame('Amina Al Farsi', $contract->signer_name);
        $this->assertNull($contract->vendor_category_enrollment_id);
        $this->assertSame($template->files['ar']['sha256'], $contract->rendered_content_hash);
        $this->assertNotSame($template->files['ar']['path'], $contract->signed_file_path);
        $this->assertStringStartsWith('vendor-contracts/'.$vendor->id, $contract->signed_file_path);
        Storage::disk('private')->assertExists($contract->signed_file_path);
        $this->assertNotNull($contract->signed_ip);

        Mail::assertQueued(SellerAgreementSignedMail::class, fn (SellerAgreementSignedMail $mail) => $mail->hasTo('new-seller@example.test')
            && count($mail->attachments()) === 1);
        $this->assertNotNull($contract->fresh()->emailed_at);
        $this->assertNull($this->agreements()->pendingFor($vendor), 'a vendor who signed at registration is not gated');
    }

    public function test_registration_rejects_an_agreement_that_changed_while_the_form_was_open(): void
    {
        $scenario = $this->activeScenario();
        $this->platformFileTemplate();

        $this->completeRegistration($scenario, [
            'signature_name' => 'Amina Al Farsi', 'agreement_language' => 'ar', 'agreement_template_id' => '00000000-0000-0000-0000-000000000000',
        ])->assertStatus(422)->assertJsonValidationErrors('agreement_template_id');
    }

    public function test_registration_works_unchanged_while_no_agreement_is_published(): void
    {
        $scenario = $this->activeScenario();
        $this->platformFileTemplate(published: false);

        $this->completeRegistration($scenario)->assertOk();

        $this->assertSame(0, VendorContract::count());
    }

    public function test_registration_page_shows_the_agreement_viewer_and_public_terms_link(): void
    {
        $this->activeScenario();
        $template = $this->platformFileTemplate();

        $this->get(route('portal.register'))
            ->assertOk()
            ->assertSee('form.signature_name', false)
            ->assertSee(route('portal.legal.seller-agreement.file', [$template, 'ar']), false)
            ->assertSee(route('portal.legal.seller-agreement'), false);
    }

    public function test_terms_and_privacy_links_open_the_public_terms_page_even_when_the_cms_stores_a_hash(): void
    {
        $this->activeScenario()->country->update(['site_code' => 'om']);

        foreach (['terms_link', 'privacy_link'] as $field) {
            PortalContent::updateOrCreate(
                ['country_code' => '*', 'page_key' => 'register', 'block_key' => 'step_5', 'field_key' => $field],
                ['type' => 'link', 'value_en' => 'Link', 'value_ar' => 'رابط', 'value_url' => '#', 'sort_order' => 1, 'is_active' => true],
            );
        }

        $this->get(route('portal.register'))
            ->assertOk()
            ->assertSee('href="'.route('portal.legal.seller-agreement').'"', false)
            ->assertSee('href="'.route('portal.legal.seller-agreement').'#privacy"', false);

        $this->get(route('portal.legal.seller-agreement'))->assertOk()->assertSee(__('portal.legal.none'));
    }

    // ── Portal: public terms page ────────────────────────────────────────────

    public function test_public_terms_page_serves_only_published_seller_agreement_documents(): void
    {
        $published = $this->platformFileTemplate();
        $draft = $this->platformFileTemplate(version: 2, published: false);

        $this->get(route('portal.legal.seller-agreement'))
            ->assertOk()
            ->assertSee('Seller Agreement')
            ->assertSee(route('portal.legal.seller-agreement.file', [$published, 'ar']), false);

        $this->get(route('portal.legal.seller-agreement.file', [$published, 'ar']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('portal.legal.seller-agreement.file', [$draft, 'ar']))->assertNotFound();

        $productTemplate = ClassifiedContractTemplate::create([
            'name' => 'Product terms', 'category_scope' => 'product', 'content_mode' => 'file', 'version' => 1,
            'content_en' => '', 'content_ar' => '', 'is_active' => true, 'is_published' => true,
            'files' => $published->files, 'created_by_admin_id' => Admin::factory()->create()->id,
        ]);
        $this->get(route('portal.legal.seller-agreement.file', [$productTemplate, 'ar']))->assertNotFound();
    }

    // ── Partner: sections reserved for another vendor type ───────────────────

    public function test_product_vendor_opening_classifieds_gets_a_localized_page_inside_the_portal_layout(): void
    {
        $scenario = $this->activeScenario();
        $owner = $this->ownerOf($scenario->vendor);

        $this->actingAs($owner, 'vendor')->withSession(['locale' => 'ar'])->get(route('partner.classifieds.index'))
            ->assertForbidden()
            ->assertViewIs('partner.section-unavailable')
            ->assertSee(__('partner.section_unavailable.heading', [], 'ar'))
            ->assertSee(route('partner.dashboard'), false)
            ->assertDontSee('This section is not available for your account type.');

        $this->actingAs($owner, 'vendor')->getJson(route('partner.classifieds.index'))
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->actingAs($owner, 'vendor')->get(route('partner.dashboard'))
            ->assertOk()
            ->assertDontSee(route('partner.classifieds.index'), false);
    }

    // ── Partner: click-wrap gate and Legal Documents ─────────────────────────

    public function test_vendor_is_gated_until_the_agreement_is_accepted_then_returns_where_they_were(): void
    {
        $scenario = $this->activeScenario();
        $template = $this->platformFileTemplate();
        $owner = $this->ownerOf($scenario->vendor);

        $this->actingAs($owner, 'vendor')->get(route('partner.dashboard'))
            ->assertRedirect(route('partner.legal.agreement'));

        $this->actingAs($owner, 'vendor')->getJson(route('partner.dashboard'))
            ->assertStatus(423)
            ->assertJsonPath('code', 'agreement_required');

        $this->actingAs($owner, 'vendor')->get(route('partner.legal.agreement'))
            ->assertOk()
            ->assertSee('Seller Agreement')
            ->assertSee(route('partner.legal.template-file', [$template, 'ar']), false);

        $this->actingAs($owner, 'vendor')->get(route('partner.legal.index'))->assertOk();

        $this->actingAs($owner, 'vendor')
            ->post(route('partner.legal.agreement.accept'), ['signature_name' => '', 'language' => 'ar'])
            ->assertSessionHasErrors(['signature_name', 'agreed']);

        $this->actingAs($owner, 'vendor')
            ->post(route('partner.legal.agreement.accept'), ['signature_name' => 'Vendor Owner', 'language' => 'ar', 'agreed' => '1'])
            ->assertRedirect(route('partner.dashboard'));

        $contract = VendorContract::where('vendor_id', $scenario->vendor->id)->sole();
        $this->assertSame('click_wrap', $contract->acceptance_method);
        $this->assertSame($owner->id, $contract->signed_by_vendor_admin_id);
        Mail::assertQueued(SellerAgreementSignedMail::class, fn (SellerAgreementSignedMail $mail) => $mail->hasTo($scenario->vendor->email));

        $location = $this->actingAs($owner, 'vendor')->get(route('partner.dashboard'))->headers->get('Location');
        $this->assertNotSame(route('partner.legal.agreement'), $location);
    }

    public function test_nothing_gates_vendors_while_no_agreement_is_published(): void
    {
        $scenario = $this->activeScenario();
        $this->platformFileTemplate(published: false);
        $owner = $this->ownerOf($scenario->vendor);

        $location = $this->actingAs($owner, 'vendor')->get(route('partner.dashboard'))->headers->get('Location');

        $this->assertNotSame(route('partner.legal.agreement'), $location);
        $this->actingAs($owner, 'vendor')->get(route('partner.legal.agreement'))->assertRedirect(route('partner.legal.index'));
    }

    public function test_legal_documents_page_shows_the_signed_copy_and_only_its_owner_can_download_it(): void
    {
        $scenario = $this->activeScenario();
        $template = $this->platformFileTemplate();
        $owner = $this->ownerOf($scenario->vendor);
        $contract = $this->agreements()->sign($scenario->vendor, $owner, $template, 'ar', 'Vendor Owner', '127.0.0.1', 'phpunit', 'registration');

        $this->actingAs($owner, 'vendor')->get(route('partner.legal.index'))
            ->assertOk()
            ->assertSee('v1')
            ->assertSee('Vendor Owner')
            ->assertSee(route('partner.legal.signed-file', $contract), false);

        $this->actingAs($owner, 'vendor')->get(route('partner.legal.signed-file', $contract))
            ->assertOk()
            ->assertDownload('agreement-ar.pdf');

        $other = $this->ownerOf(Vendor::factory()->create(['global_status' => 'active', 'onboarding_completed_at' => now()]));
        $this->actingAs($other, 'vendor')->get(route('partner.legal.signed-file', $contract))->assertNotFound();
    }

    public function test_vendors_can_open_published_template_documents_but_never_drafts(): void
    {
        $scenario = $this->activeScenario();
        $owner = $this->ownerOf($scenario->vendor);
        $published = $this->platformFileTemplate();
        $draft = $this->platformFileTemplate(version: 2, published: false);
        $this->agreements()->sign($scenario->vendor, $owner, $published, 'ar', 'Vendor Owner', null, null);

        $this->actingAs($owner, 'vendor')->get(route('partner.legal.template-file', [$published, 'ar']))->assertOk();
        $this->actingAs($owner, 'vendor')->get(route('partner.legal.template-file', [$draft, 'ar']))->assertNotFound();
    }

    // ── Versions: click-wrap and notice period ───────────────────────────────

    public function test_a_click_wrap_version_retires_the_old_one_and_asks_signed_vendors_to_accept_again(): void
    {
        $scenario = $this->activeScenario();
        $owner = $this->ownerOf($scenario->vendor);
        $v1 = $this->platformFileTemplate();
        $this->agreements()->sign($scenario->vendor, $owner, $v1, 'ar', 'Vendor Owner', null, null);
        $this->assertNull($this->agreements()->pendingFor($scenario->vendor));

        $v2 = $this->platformFileTemplate(version: 2, published: false);
        $this->agreements()->publish($v2);

        $this->assertFalse($v1->fresh()->is_published);
        $this->assertTrue($v2->fresh()->is_published);
        $this->assertSame($v2->id, $this->agreements()->pendingFor($scenario->vendor)?->id);

        $this->actingAs($owner, 'vendor')->get(route('partner.dashboard'))->assertRedirect(route('partner.legal.agreement'));

        $this->actingAs($owner, 'vendor')
            ->post(route('partner.legal.agreement.accept'), ['signature_name' => 'Vendor Owner', 'language' => 'ar', 'agreed' => '1'])
            ->assertRedirect();

        $this->assertSame(['superseded', 'active'], VendorContract::where('vendor_id', $scenario->vendor->id)->orderBy('signed_at')->orderBy('template_version')->pluck('status')->all());
        $this->assertNull($this->agreements()->pendingFor($scenario->vendor));
    }

    public function test_a_notice_version_does_not_block_vendors_and_becomes_an_implicit_acceptance_when_it_takes_effect(): void
    {
        $scenario = $this->activeScenario();
        $owner = $this->ownerOf($scenario->vendor);
        $v1 = $this->platformFileTemplate();
        $this->agreements()->sign($scenario->vendor, $owner, $v1, 'ar', 'Vendor Owner', '127.0.0.1', 'phpunit', 'registration');

        $v2 = $this->platformFileTemplate(version: 2, published: false, overrides: ['acceptance_mode' => 'notice', 'notice_days' => 14, 'change_summary' => 'Fees updated']);
        $this->agreements()->publish($v2);
        $v2->refresh();

        $this->assertTrue($v2->isScheduled());
        $this->assertTrue($v1->fresh()->is_published, 'the old version stays in force during the notice period');
        $this->assertSame($v1->id, $this->agreements()->inForce()->id);
        $this->assertSame($v2->id, $this->agreements()->upcoming()->id);
        $this->assertNull($this->agreements()->pendingFor($scenario->vendor));

        $this->actingAs($owner, 'vendor')->get(route('partner.legal.index'))
            ->assertOk()
            ->assertSee('Fees updated')
            ->assertSee($v2->effective_at->format('d/m/Y'));

        $this->travel(15)->days();
        $this->assertSame($v2->id, $this->agreements()->inForce()->id);

        Artisan::call('contracts:apply-effective');

        $implicit = VendorContract::where('vendor_id', $scenario->vendor->id)->where('status', 'active')->sole();
        $this->assertSame($v2->id, $implicit->contract_template_id);
        $this->assertSame('implicit', $implicit->acceptance_method);
        $this->assertSame('Vendor Owner', $implicit->signer_name);
        $this->assertNotNull($implicit->signed_file_path);
        $this->assertFalse($v1->fresh()->is_published);
        $this->assertSame('superseded', VendorContract::where('template_version', 1)->sole()->status);

        Artisan::call('contracts:apply-effective');
        $this->assertSame(2, VendorContract::where('vendor_id', $scenario->vendor->id)->count(), 'running again must not duplicate records');
    }

    public function test_a_vendor_who_never_logged_in_before_the_notice_ended_is_still_covered_lazily(): void
    {
        $scenario = $this->activeScenario();
        $v1 = $this->platformFileTemplate();
        $this->agreements()->sign($scenario->vendor, null, $v1, 'ar', 'Vendor Owner', null, null, 'registration');
        $v2 = $this->platformFileTemplate(version: 2, published: false, overrides: ['acceptance_mode' => 'notice']);
        $this->agreements()->publish($v2);

        $this->travel(15)->days();

        $this->assertNull($this->agreements()->pendingFor($scenario->vendor));
        $this->assertSame('implicit', $this->agreements()->activeSignature($scenario->vendor)->acceptance_method);
        $this->assertSame($v2->id, $this->agreements()->activeSignature($scenario->vendor)->contract_template_id);
    }

    public function test_a_vendor_without_any_signature_must_sign_even_when_the_new_version_is_notice_mode(): void
    {
        $scenario = $this->activeScenario();
        $v1 = $this->platformFileTemplate();
        $v2 = $this->platformFileTemplate(version: 2, published: false, overrides: ['acceptance_mode' => 'notice']);
        $this->agreements()->publish($v2);

        $this->assertSame($v2->id, $this->agreements()->pendingFor($scenario->vendor)?->id);
        $this->assertTrue($v1->fresh()->is_published);
    }

    public function test_the_first_published_agreement_is_always_click_wrap_even_if_marked_notice(): void
    {
        $first = $this->platformFileTemplate(published: false, overrides: ['acceptance_mode' => 'notice']);
        $this->agreements()->publish($first);

        $first->refresh();
        $this->assertSame('click_wrap', $first->acceptance_mode);
        $this->assertFalse($first->isScheduled());
    }

    // ── Admin publish and notifications ──────────────────────────────────────

    public function test_publishing_from_admin_dispatches_notifications_and_the_job_emails_vendors_who_have_not_signed(): void
    {
        Bus::fake([NotifyVendorsOfAgreementUpdateJob::class]);
        $scenario = $this->activeScenario();
        $this->ownerOf($scenario->vendor);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['classifieds.view', 'vendors.view', 'vendors.assigned_only']);
        $draft = $this->platformFileTemplate(published: false, overrides: ['change_summary' => 'First version']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.contracts.templates.publish', $draft))
            ->assertSessionHas('success');

        Bus::assertDispatched(NotifyVendorsOfAgreementUpdateJob::class, fn ($job) => $job->templateId === $draft->id);
    }

    public function test_the_update_job_emails_and_notifies_vendors_who_have_not_signed_the_version(): void
    {
        $scenario = $this->activeScenario();
        $this->ownerOf($scenario->vendor);
        $draft = $this->platformFileTemplate(published: false, overrides: ['change_summary' => 'First version']);
        $this->agreements()->publish($draft);

        (new NotifyVendorsOfAgreementUpdateJob($draft->id))->handle($this->agreements());

        Mail::assertQueued(SellerAgreementUpdatedMail::class, fn (SellerAgreementUpdatedMail $mail) => $mail->hasTo($scenario->vendor->contact_email ?: $scenario->vendor->email));
        $this->assertSame(1, $scenario->vendor->vendorAdmins()->first()->notifications()->count());
    }

    public function test_admin_sees_every_vendor_on_the_platform_signature_page_and_can_download_signed_copies(): void
    {
        $scenario = $this->activeScenario();
        $template = $this->platformFileTemplate();
        $contract = $this->agreements()->sign($scenario->vendor, null, $template, 'ar', 'Vendor Owner', '127.0.0.1', 'phpunit', 'registration');
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['classifieds.view', 'vendors.view', 'vendors.assigned_only']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.contracts.templates.signatures', $template))
            ->assertOk()
            ->assertSee($scenario->vendor->store_name)
            ->assertSee(route('admin.contracts.signatures.download', $contract), false)
            ->assertViewHas('summary', fn (array $summary) => $summary['signed'] === 1);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.contracts.signatures.download', $contract))
            ->assertOk()
            ->assertDownload('agreement-ar.pdf');

        $this->actingAs($admin, 'admin')->get(route('admin.contracts.signatures.index', ['scope' => 'platform']))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.vendors.contracts.index', $scenario->vendor))->assertOk();
    }

    // ── Editor-mode agreement keeps working end to end ───────────────────────

    public function test_an_editor_written_agreement_is_frozen_with_vendor_values_and_emailed_inline(): void
    {
        $scenario = $this->activeScenario();
        $template = ClassifiedContractTemplate::create([
            'name' => 'Seller Agreement', 'category_scope' => 'platform', 'content_mode' => 'editor', 'version' => 1,
            'content_en' => '<p>Agreement with {{vendor.store_name}} signed by {{vendor.signer_name}}</p>',
            'content_ar' => '<p>اتفاقية مع {{vendor.store_name}} وقعها {{vendor.signer_name}}</p>',
            'is_active' => true, 'is_published' => true, 'effective_at' => now()->subMinute(),
            'created_by_admin_id' => Admin::factory()->create()->id,
        ]);

        $contract = $this->agreements()->sign($scenario->vendor, null, $template, 'en', 'Owner Name', '127.0.0.1', 'phpunit');
        $this->agreements()->sendSignedCopy($contract);

        $this->assertSame('editor', $contract->content_mode);
        $this->assertNull($contract->signed_file_path);
        $this->assertStringContainsString($scenario->vendor->store_name.' signed by Owner Name', $contract->rendered_content);

        Mail::assertQueued(SellerAgreementSignedMail::class, function (SellerAgreementSignedMail $mail) use ($scenario) {
            $mail->assertSeeInHtml('signed by Owner Name', false);
            $mail->assertSeeInHtml($scenario->vendor->store_name, false);

            return $mail->attachments() === [];
        });
    }

    // ── Category contract in file mode ───────────────────────────────────────

    public function test_a_product_category_contract_uploaded_as_a_file_is_signed_and_frozen_like_text_contracts(): void
    {
        $scenario = $this->activeScenario();
        $owner = $this->ownerOf($scenario->vendor);
        $template = ClassifiedContractTemplate::create([
            'name' => 'Product terms', 'category_scope' => 'product', 'content_mode' => 'file', 'version' => 1,
            'content_en' => '', 'content_ar' => '', 'is_active' => true, 'is_published' => true,
            'created_by_admin_id' => Admin::factory()->create()->id,
        ]);
        $template->update(['files' => ['ar' => app(ContractFileService::class)->store($this->pdf('product-terms.pdf'), $template->id, 'ar')]]);
        $scenario->category->update(['contract_template_id' => $template->id]);

        $this->actingAs($owner, 'vendor')->get(route('partner.contracts.pending'))
            ->assertOk()
            ->assertSee('Product terms')
            ->assertSee(route('partner.legal.template-file', [$template, 'ar']), false);

        $this->actingAs($owner, 'vendor')->get(route('partner.contracts.preview', $template))
            ->assertOk()
            ->assertSee(route('partner.legal.template-file', [$template, 'ar']), false);

        $this->actingAs($owner, 'vendor')
            ->postJson(route('partner.contracts.accept', $template), [
                'signature_name' => 'Owner', 'language' => 'en', 'agreed' => 1,
                'category_scope' => 'product', 'category_id' => $scenario->category->id,
            ])
            ->assertOk();

        $contract = VendorContract::where('category_scope', 'product')->sole();
        $this->assertSame('file', $contract->content_mode);
        $this->assertSame('ar', $contract->language_signed, 'the file language actually signed is recorded');
        $this->assertSame($template->fresh()->files['ar']['sha256'], $contract->rendered_content_hash);
        Storage::disk('private')->assertExists($contract->signed_file_path);

        $this->actingAs($owner, 'vendor')->get(route('partner.contracts.signed', $contract))
            ->assertOk()
            ->assertSee(route('partner.legal.signed-file', $contract), false);

        $payload = app(CategoryContractService::class)->present($template, $scenario->vendor, $scenario->category);
        $this->assertSame('file', $payload['content_mode']);
        $this->assertArrayHasKey('ar', $payload['files']);
        $this->assertStringContainsString('product-terms.pdf', $payload['content_en'], 'clients that only render text still get a link');
    }

    // ── Vendor API ───────────────────────────────────────────────────────────

    public function test_vendor_api_exposes_the_agreement_accepts_it_and_lists_signed_documents(): void
    {
        $scenario = $this->activeScenario();
        $owner = $this->ownerOf($scenario->vendor);
        $template = $this->platformFileTemplate();

        $this->actingAs($owner, 'vendor')
            ->getJson('/api/vendor/v1/legal/agreement')
            ->assertOk()
            ->assertJsonPath('data.acceptance_required', true)
            ->assertJsonPath('data.pending.template_id', $template->id)
            ->assertJsonPath('data.pending.content_mode', 'file')
            ->assertJsonPath('data.pending.files.ar.name', 'agreement-ar.pdf');

        $this->actingAs($owner, 'vendor')
            ->postJson('/api/vendor/v1/legal/agreement/accept', ['signature_name' => 'Owner'])
            ->assertStatus(422);

        $this->actingAs($owner, 'vendor')
            ->postJson('/api/vendor/v1/legal/agreement/accept', ['signature_name' => 'Owner', 'language' => 'ar', 'agreed' => true])
            ->assertOk()
            ->assertJsonPath('data.method', 'click_wrap');

        $this->actingAs($owner, 'vendor')
            ->getJson('/api/vendor/v1/legal/agreement')
            ->assertJsonPath('data.acceptance_required', false)
            ->assertJsonPath('data.signature.version', 1);

        $this->actingAs($owner, 'vendor')
            ->getJson('/api/vendor/v1/legal/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category_scope', 'platform');

        $contract = VendorContract::sole();
        $this->actingAs($owner, 'vendor')
            ->withHeader('Accept', 'application/json')
            ->get('/api/vendor/v1/legal/signed/'.$contract->id.'/file')
            ->assertOk();
    }
}
