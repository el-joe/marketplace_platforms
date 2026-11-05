<?php

namespace Tests\Feature\Credentials;

use App\Enums\VendorGlobalStatus;
use App\Jobs\VendorApprovedJob;
use App\Models\Country;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Services\Vendor\VendorOwnerService;
use App\Services\VendorApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CredentialMailFixtures;
use Tests\TestCase;

/**
 * Seller approval must reuse the owner login created at registration (never a second
 * account with an unknown password) and email the owner the login URL plus a set-password link.
 */
class VendorApprovalCredentialTest extends TestCase
{
    use CredentialMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        $this->useDeliveringMailer();

        // Document verification is covered elsewhere; here approval is always allowed.
        $this->app->bind(VendorApprovalService::class, fn ($app) => new class($app->make(VendorOwnerService::class)) extends VendorApprovalService
        {
            public function getApprovalBlockers(Vendor $vendor): array
            {
                return [];
            }
        });
    }

    private function pendingVendor(string $email = 'owner@yahoo.com'): Vendor
    {
        return Vendor::factory()->create([
            'email' => $email, 'global_status' => 'pending', 'approved_at' => null, 'business_name' => 'Acme LLC',
        ]);
    }

    /** The account RegistrationController::complete() creates: role "owner", is_owner left at 0. */
    private function registrationOwner(Vendor $vendor): VendorAdmin
    {
        return VendorAdmin::create([
            'vendor_id' => $vendor->id, 'name' => 'Owner', 'email' => $vendor->email,
            'password' => 'chosen-at-registration', 'role' => 'owner', 'is_active' => 1,
        ]);
    }

    public function test_approval_reuses_the_registration_owner_and_emails_login_and_set_password_links(): void
    {
        $vendor = $this->pendingVendor();
        $owner = $this->registrationOwner($vendor);
        $this->assertFalse((bool) $owner->is_owner);
        $actor = $this->adminWith(['vendors.view', 'vendors.edit']);

        $response = $this->actingAs($actor, 'admin')->postJson(route('admin.vendor-applications.approve', $vendor->id));

        $response->assertOk()->assertJson(['email_sent' => true])->assertJsonMissingPath('warning');
        $this->assertSame(1, VendorAdmin::where('vendor_id', $vendor->id)->count(), 'no duplicate owner account');
        $this->assertTrue($owner->fresh()->is_owner);
        $this->assertSame('vendor_owner', $owner->fresh()->role);
        $this->assertTrue(Hash::check('chosen-at-registration', $owner->fresh()->password), 'owner keeps the password they chose');
        $this->assertSame(VendorGlobalStatus::Active, $vendor->fresh()->global_status);

        $mail = $this->sentCredentialMail('vendor_approved');
        $this->assertTrue($mail->hasTo('owner@yahoo.com'));
        $this->assertSame(route('partner.login'), $mail->loginUrl);
        $this->assertStringStartsWith(route('partner.auth.reset', ['token' => $mail->resetCode]), $mail->setPasswordUrl);
        $this->assertStringContainsString(e($mail->loginUrl), $mail->render());
        Queue::assertPushed(VendorApprovedJob::class);
    }

    public function test_approval_creates_one_owner_when_the_vendor_has_no_login_and_never_duplicates_it(): void
    {
        $vendor = $this->pendingVendor();
        $actor = $this->adminWith(['vendors.view', 'vendors.edit']);

        $this->actingAs($actor, 'admin')->postJson(route('admin.vendor-applications.approve', $vendor->id))->assertOk();
        $this->actingAs($actor, 'admin')->postJson(route('admin.vendor-applications.approve', $vendor->id))->assertOk();

        $this->assertSame(1, VendorAdmin::where('vendor_id', $vendor->id)->count());
        $this->assertTrue(VendorAdmin::where('vendor_id', $vendor->id)->firstOrFail()->is_owner);
    }

    public function test_approval_is_rolled_back_when_the_email_belongs_to_another_sellers_login(): void
    {
        $other = Vendor::factory()->create();
        VendorAdmin::create(['vendor_id' => $other->id, 'name' => 'X', 'email' => 'owner@yahoo.com', 'password' => 'x', 'role' => 'owner', 'is_active' => 1]);
        $vendor = $this->pendingVendor('owner@yahoo.com');
        $actor = $this->adminWith(['vendors.view', 'vendors.edit']);

        $this->actingAs($actor, 'admin')->postJson(route('admin.vendor-applications.approve', $vendor->id))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'owner@yahoo.com'));

        $this->assertSame(VendorGlobalStatus::Pending, $vendor->fresh()->global_status);
        Mail::assertNothingSent();
    }

    public function test_vendor_stays_approved_and_admin_sees_a_warning_when_the_approval_email_fails(): void
    {
        Log::spy();
        $this->makeMailerFail();
        $vendor = $this->pendingVendor();
        $this->registrationOwner($vendor);
        $actor = $this->adminWith(['vendors.view', 'vendors.edit']);

        $response = $this->actingAs($actor, 'admin')->postJson(route('admin.vendor-applications.approve', $vendor->id));

        $response->assertOk()->assertJson(['email_sent' => false]);
        $this->assertStringContainsString('owner@yahoo.com', $response->json('warning'));
        $this->assertSame(VendorGlobalStatus::Active, $vendor->fresh()->global_status);
        $this->assertCredentialFailureLogged('vendor_approved', 'owner@yahoo.com');
    }

    public function test_the_vendors_screen_approval_path_uses_the_same_owner_and_email_logic(): void
    {
        $vendor = $this->pendingVendor();
        $owner = $this->registrationOwner($vendor);
        $actor = $this->adminWith(['vendors.view', 'vendors.edit', 'vendors.approve']);

        $this->actingAs($actor, 'admin')->postJson(route('admin.vendors.approve', $vendor->id))
            ->assertOk()->assertJson(['email_sent' => true]);

        $this->assertTrue($owner->fresh()->is_owner);
        $this->assertSame(1, VendorAdmin::where('vendor_id', $vendor->id)->count());
        $this->sentCredentialMail('vendor_approved');
    }

    public function test_the_approved_job_no_longer_sends_a_plain_text_mail(): void
    {
        $vendor = Vendor::factory()->create(['country_id' => Country::factory()->create()->id]);

        (new VendorApprovedJob($vendor->id))->handle();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_admin_can_resend_credentials_to_an_active_store_owner(): void
    {
        $vendor = Vendor::factory()->create(['email' => 'live@proton.me']);
        $owner = VendorAdmin::create(['vendor_id' => $vendor->id, 'name' => 'O', 'email' => 'live@proton.me', 'password' => 'pw-123456', 'role' => 'vendor_owner', 'is_owner' => true, 'is_active' => 1]);
        $actor = $this->adminWith(['vendors.view', 'vendors.edit']);

        $this->actingAs($actor, 'admin')->postJson(route('admin.vendors.resend-credentials', $vendor->id))
            ->assertOk()->assertJson(['email_sent' => true]);

        $this->assertTrue($this->sentCredentialMail('vendor_resend')->hasTo('live@proton.me'));
        $this->assertTrue(Hash::check('pw-123456', $owner->fresh()->password));
    }

    public function test_resend_credentials_needs_edit_permission_and_an_approved_vendor(): void
    {
        $active = Vendor::factory()->create();
        $pending = $this->pendingVendor();

        $viewer = $this->adminWith(['vendors.view']);
        $this->actingAs($viewer, 'admin')->postJson(route('admin.vendors.resend-credentials', $active->id))->assertForbidden();

        $editor = $this->adminWith(['vendors.view', 'vendors.edit']);
        $this->actingAs($editor, 'admin')->postJson(route('admin.vendors.resend-credentials', $pending->id))->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_resend_credentials_failure_is_visible_and_logged(): void
    {
        Log::spy();
        $this->makeMailerFail();
        $vendor = Vendor::factory()->create(['email' => 'live@proton.me']);
        $actor = $this->adminWith(['vendors.view', 'vendors.edit']);

        $this->actingAs($actor, 'admin')->postJson(route('admin.vendors.resend-credentials', $vendor->id))
            ->assertStatus(502)->assertJson(['email_sent' => false]);

        $this->assertCredentialFailureLogged('vendor_credentials_resent', 'live@proton.me');
    }
}
