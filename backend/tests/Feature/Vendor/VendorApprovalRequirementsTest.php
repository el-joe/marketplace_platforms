<?php

namespace Tests\Feature\Vendor;

use App\Models\Country;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorDocument;
use App\Models\VendorDocumentCountryRequirement;
use App\Models\VendorDocumentType;
use App\Services\VendorApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Vendor approval enforces the documents the vendor's country marks mandatory (Admin → Vendor document
 * types) rather than a fixed list of three, and a blocked vendor is told why: an application still
 * under review or rejected reads differently from a suspended account.
 */
class VendorApprovalRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scenario = MarketplaceScenario::make()->build();
    }

    private function documentType(string $code): VendorDocumentType
    {
        return VendorDocumentType::firstOrCreate(['code' => $code], [
            'name_en' => Str::headline($code),
            'name_ar' => $code,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function requireForCountry(Country $country, string ...$codes): void
    {
        foreach ($codes as $code) {
            VendorDocumentCountryRequirement::create([
                'vendor_document_type_id' => $this->documentType($code)->id,
                'country_id' => $country->id,
                'requirement_level' => 'mandatory',
            ]);
        }
    }

    private function pendingVendor(Country $country): Vendor
    {
        return Vendor::factory()->create([
            'country_id' => $country->id,
            'business_name' => 'Pending LLC',
            'global_status' => 'pending',
            'approved_at' => null,
        ]);
    }

    private function verifyDocuments(Vendor $vendor, string ...$codes): void
    {
        foreach ($codes as $code) {
            VendorDocument::create([
                'vendor_id' => $vendor->id,
                'vendor_document_type_id' => $this->documentType($code)->id,
                'file_path' => "vendor-docs/{$code}.pdf",
                'status' => 'approved',
            ]);
        }
    }

    public function test_approval_requires_every_document_the_country_marks_mandatory(): void
    {
        $country = $this->scenario->country;
        $this->requireForCountry($country, 'business_license', 'tax_certificate', 'owner_id', 'vat_registration', 'bank_proof');
        $vendor = $this->pendingVendor($country);
        $this->verifyDocuments($vendor, 'business_license', 'tax_certificate', 'owner_id');

        $service = app(VendorApprovalService::class);

        $this->assertSame([
            'Required document not verified: vat_registration.',
            'Required document not verified: bank_proof.',
        ], $service->getApprovalBlockers($vendor));

        $this->verifyDocuments($vendor, 'vat_registration', 'bank_proof');

        $this->assertSame([], $service->getApprovalBlockers($vendor->fresh()));
    }

    public function test_a_country_without_configured_documents_falls_back_to_the_platform_default(): void
    {
        $vendor = $this->pendingVendor($this->scenario->country);

        $this->assertSame(
            VendorApprovalService::DEFAULT_REQUIRED_DOC_TYPES,
            array_keys(app(VendorApprovalService::class)->requiredDocumentTypes($vendor)),
        );
    }

    public function test_a_pending_applicant_is_told_the_application_is_under_review_not_suspended(): void
    {
        $vendor = $this->pendingVendor($this->scenario->country);
        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => $vendor->id,
            'name' => 'Applicant',
            'email' => 'applicant-'.Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'is_owner' => true,
            'is_active' => true,
        ]);

        $this->actingAs($vendorAdmin, 'vendor')
            ->getJson(route('partner.dashboard'))
            ->assertForbidden()
            ->assertJsonPath('message', __('partner.suspended.pending_api_message'));

        $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.suspended'))
            ->assertOk()
            ->assertSee(__('partner.suspended.pending_heading'))
            ->assertDontSee(__('partner.suspended.heading'));
    }
}
