<?php

namespace Tests\Feature\Credentials;

use App\Http\Middleware\EnforceSellerAgreement;
use App\Http\Middleware\VendorActive;
use App\Http\Middleware\VendorApiActive;
use App\Http\Middleware\VendorDualAuth;
use App\Http\Middleware\VendorPermissionMiddleware;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Services\Vendor\TeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\Support\CredentialMailFixtures;
use Tests\TestCase;

/**
 * Inviting a team member emails a time-limited set-password link, never a plaintext password,
 * and a failed send keeps the member but warns the inviter.
 */
class VendorTeamInviteEmailTest extends TestCase
{
    use CredentialMailFixtures;
    use RefreshDatabase;

    private VendorAdmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->useDeliveringMailer();
        Role::firstOrCreate(['name' => 'vendor_manager', 'guard_name' => 'vendor']);

        $vendor = Vendor::factory()->create();
        $this->owner = VendorAdmin::create([
            'vendor_id' => $vendor->id, 'name' => 'Owner', 'email' => 'owner@store.test', 'password' => 'secret-pass',
            'role' => 'vendor_owner', 'is_owner' => true, 'is_active' => true,
        ]);
    }

    private function invite(): TestResponse
    {
        return $this->withoutMiddleware([
            VendorActive::class,
            EnforceSellerAgreement::class,
            VendorPermissionMiddleware::class,
        ])->actingAs($this->owner, 'vendor')->postJson(route('partner.team.store'), [
            'name' => 'New Manager', 'email' => 'manager@gmail.com', 'role' => 'vendor_manager',
        ]);
    }

    public function test_invite_emails_a_set_password_link_and_no_password(): void
    {
        $this->invite()->assertCreated()->assertJson(['email_sent' => true])->assertJsonMissingPath('warning');

        $mail = $this->sentCredentialMail('vendor_team_invite');
        $this->assertTrue($mail->hasTo('manager@gmail.com'));
        $this->assertStringStartsWith(route('partner.auth.reset', ['token' => $mail->resetCode]), $mail->setPasswordUrl);
        $this->assertStringContainsString(e($mail->setPasswordUrl), $mail->render());
        $this->assertSame('vendor_manager', VendorAdmin::where('email', 'manager@gmail.com')->firstOrFail()->role);
    }

    public function test_member_is_kept_and_inviter_warned_when_the_invite_email_fails(): void
    {
        Log::spy();
        $this->makeMailerFail();

        $response = $this->invite();

        $response->assertCreated()->assertJson(['email_sent' => false]);
        $this->assertStringContainsString('manager@gmail.com', $response->json('warning'));
        $this->assertDatabaseHas('vendor_admins', ['email' => 'manager@gmail.com', 'vendor_id' => $this->owner->vendor_id]);
        $this->assertCredentialFailureLogged('vendor_team_invite', 'manager@gmail.com');
    }

    public function test_partner_app_api_invite_emails_a_link_and_reports_failures(): void
    {
        Log::spy();
        $this->makeMailerFail();

        $response = $this->withoutMiddleware([VendorDualAuth::class, VendorApiActive::class])
            ->actingAs($this->owner, 'vendor_api')
            ->postJson(route('partner.api.team.store'), ['name' => 'App Staff', 'email' => 'app.staff@gmail.com', 'role' => 'vendor_manager']);

        $response->assertCreated()->assertJson(['success' => true, 'email_sent' => false]);
        $this->assertStringContainsString('app.staff@gmail.com', $response->json('warning'));
        $this->assertDatabaseHas('vendor_admins', ['email' => 'app.staff@gmail.com']);
        $this->assertCredentialFailureLogged('vendor_team_invite', 'app.staff@gmail.com');
    }

    public function test_the_vendor_api_invite_service_uses_the_same_link_flow(): void
    {
        [$member, $delivery] = app(TeamService::class)->invite($this->owner->vendor, [
            'name' => 'Api Staff', 'email' => 'api.staff@gmail.com', 'role' => 'manager',
        ]);

        $this->assertTrue($delivery->delivered());
        $this->assertSame('vendor_manager', $member->role);
        $this->assertTrue($this->sentCredentialMail('vendor_team_invite')->hasTo('api.staff@gmail.com'));
    }
}
