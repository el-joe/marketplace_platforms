<?php

namespace Tests\Feature\Credentials;

use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\MarketerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CredentialMailFixtures;
use Tests\TestCase;

/**
 * Marketer onboarding: approval used to send nothing (a TODO); creation by an admin sent nothing either.
 */
class MarketerCredentialEmailTest extends TestCase
{
    use CredentialMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->useDeliveringMailer();
    }

    private function pendingMarketer(): MarketerAdmin
    {
        $marketer = Marketer::factory()->create(['email' => 'promoter@gmail.com', 'global_status' => 'pending', 'approved_at' => null]);

        return MarketerAdmin::create([
            'marketer_id' => $marketer->id, 'name' => 'Promoter', 'email' => 'promoter@gmail.com',
            'password' => 'chosen-at-signup', 'is_owner' => true, 'is_active' => true,
        ]);
    }

    public function test_approving_a_marketer_emails_login_and_a_set_password_link(): void
    {
        $owner = $this->pendingMarketer();
        $actor = $this->adminWith(['marketers.view', 'marketers.manage']);

        $this->actingAs($actor, 'admin')->post(route('admin.marketers.approve', $owner->marketer_id))
            ->assertRedirect()->assertSessionMissing('warning');

        $mail = $this->sentCredentialMail('marketer_approved');
        $this->assertTrue($mail->hasTo('promoter@gmail.com'));
        $this->assertSame(route('marketer.login'), $mail->loginUrl);
        $this->assertStringStartsWith(route('marketer.auth.reset', ['token' => $mail->resetCode]), $mail->setPasswordUrl);
        $this->assertTrue(Hash::check('chosen-at-signup', $owner->fresh()->password));
    }

    public function test_marketer_stays_approved_and_admin_is_warned_when_the_email_fails(): void
    {
        Log::spy();
        $this->makeMailerFail();
        $owner = $this->pendingMarketer();
        $actor = $this->adminWith(['marketers.view', 'marketers.manage']);

        $this->actingAs($actor, 'admin')->post(route('admin.marketers.approve', $owner->marketer_id))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('warning', fn ($warning) => str_contains($warning, 'promoter@gmail.com'));

        $this->assertSame('active', $owner->marketer->fresh()->global_status->value);
        $this->assertCredentialFailureLogged('marketer_approved', 'promoter@gmail.com');
    }

    public function test_admin_created_marketer_gets_a_welcome_link_and_failures_are_flagged(): void
    {
        $job = MarketerJob::firstOrCreate(['key' => 'influencer'], ['name_ar' => 'x', 'name_en' => 'x', 'is_active' => true, 'sort_order' => 1]);
        $actor = $this->adminWith(['marketers.view', 'marketers.manage']);
        $payload = ['name' => 'New Marketer', 'email' => 'fresh@outlook.com', 'marketer_jobs' => [$job->id], 'password' => 'initial-pass-1'];

        $this->actingAs($actor, 'admin')->post(route('admin.marketers.store'), $payload)->assertSessionMissing('warning');
        $mail = $this->sentCredentialMail('marketer_welcome');
        $this->assertTrue($mail->hasTo('fresh@outlook.com'));

        Log::spy();
        $this->makeMailerFail();
        $this->actingAs($actor, 'admin')->post(route('admin.marketers.store'), ['email' => 'second@outlook.com'] + $payload)
            ->assertSessionHas('warning', fn ($warning) => str_contains($warning, 'second@outlook.com'));
        $this->assertDatabaseHas('marketer_admins', ['email' => 'second@outlook.com']);
    }
}
