<?php

namespace Tests\Feature;

use App\Enums\PromotionCategory;
use App\Jobs\CollectPendingInfluencerFeesJob;
use App\Jobs\SendCampaignWhatsAppNotificationJob;
use App\Models\Admin;
use App\Models\Marketer;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Models\PromotionCategoryTarget;
use App\Models\Setting;
use App\Notifications\Admin\CampaignMarketerReassignedNotification;
use App\Services\Marketer\PromotionQuotaService;
use App\Services\MarketerCampaignService;
use App\Services\WalletService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class MarketerPromotionFlowTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        Notification::fake();
    }

    private function setting(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'category' => 'marketer', 'is_encrypted' => 0, 'is_public' => 0]);
        Setting::flush();
    }

    private function newMarketer(MarketerJob $job): Marketer
    {
        $m = Marketer::create([
            'name' => 'M '.Str::random(4), 'email' => 'm-'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.fake()->numerify('########'), 'marketer_type' => 'affiliate',
            'global_status' => 'active', 'country_id' => $this->s->country->id, 'approved_at' => now(),
        ]);
        MarketerMarketerJob::create(['marketer_id' => $m->id, 'marketer_job_id' => $job->id]);

        return $m;
    }

    public function test_invitation_window_follows_the_admin_setting_instead_of_a_seven_day_floor(): void
    {
        $this->setting('marketer_invitation_timeout_hours', 12);

        $inv = app(MarketerCampaignService::class)->dispatchInvitation($this->s->marketerCampaign, $this->s->marketer->id);

        $this->assertSame(12, $inv->acceptance_window_hours);
        $this->assertEqualsWithDelta(12, now()->diffInHours($inv->expires_at, true), 0.1);
    }

    public function test_timeout_moves_the_promotion_to_the_marketer_with_fewest_recent_requests_and_logs_it_for_admin(): void
    {
        $job = MarketerJob::create(['key' => 'celeb', 'name_ar' => 'x', 'name_en' => 'x', 'is_active' => true, 'sort_order' => 1]);
        MarketerMarketerJob::create(['marketer_id' => $this->s->marketer->id, 'marketer_job_id' => $job->id]);
        $busy = $this->newMarketer($job);
        $idle = $this->newMarketer($job);
        $other = $this->s->marketerCampaign->replicate();
        $other->save();
        foreach (range(1, 3) as $i) {
            MarketerCampaignInvitation::create(['campaign_id' => $other->id, 'marketer_id' => $busy->id, 'status' => 'rejected', 'referral_code' => 'B'.$i.Str::random(6)]);
        }

        $admin = Admin::factory()->create();
        $pending = MarketerCampaignInvitation::create([
            'campaign_id' => $this->s->marketerCampaign->id, 'marketer_id' => $this->s->marketer->id,
            'status' => 'pending', 'referral_code' => 'P'.Str::random(8),
        ]);

        app(MarketerCampaignService::class)->handleInvitationTimeout($pending->id);

        $this->assertSame('timed_out', $pending->fresh()->status);
        $replacement = MarketerCampaignInvitation::where('replaced_invitation_id', $pending->id)->first();
        $this->assertNotNull($replacement);
        $this->assertSame($idle->id, $replacement->marketer_id);
        Notification::assertSentTo($admin, CampaignMarketerReassignedNotification::class);
    }

    public function test_whatsapp_link_requires_signature_and_lets_marketer_reject_without_login(): void
    {
        $this->withoutVite();
        $inv = MarketerCampaignInvitation::create([
            'campaign_id' => $this->s->marketerCampaign->id, 'marketer_id' => $this->s->marketer->id,
            'status' => 'pending', 'acceptance_window_hours' => 12, 'expires_at' => now()->addHours(12), 'referral_code' => 'W'.Str::random(8),
        ]);

        $this->get(route('marketer.invitations.whatsapp.show', $inv))->assertForbidden();

        $signed = URL::temporarySignedRoute('marketer.invitations.whatsapp.show', now()->addHour(), ['invitation' => $inv->id]);
        $this->get($signed)->assertOk()->assertSee('name="action"', false);

        $this->post($signed, ['action' => 'reject', 'reason' => 'busy'])->assertOk();
        $this->assertSame('rejected', $inv->fresh()->status);

        $this->post($signed, ['action' => 'accept'])->assertOk()->assertSee(__('marketer.invitations.whatsapp_unavailable'));
        $this->assertSame('rejected', $inv->fresh()->status);
    }

    public function test_whatsapp_flag_is_only_set_when_the_provider_accepts_the_message(): void
    {
        $this->s->marketer->update(['whatsapp_for_campaigns' => '+971500000000']);
        $inv = MarketerCampaignInvitation::create([
            'campaign_id' => $this->s->marketerCampaign->id, 'marketer_id' => $this->s->marketer->id,
            'status' => 'pending', 'acceptance_window_hours' => 12, 'expires_at' => now()->addHours(12), 'referral_code' => 'F'.Str::random(8),
        ]);

        $this->assertFalse(app(WhatsAppService::class)->isConfigured());
        app()->call([new SendCampaignWhatsAppNotificationJob($inv->id), 'handle']);
        $this->assertFalse($inv->fresh()->whatsapp_sent);

        $fake = new class extends WhatsAppService
        {
            public ?string $last = null;

            public function isConfigured(): bool
            {
                return true;
            }

            public function send(string $to, string $message): bool
            {
                $this->last = $message;

                return true;
            }
        };
        $this->app->instance(\App\Contracts\WhatsAppGateway::class, $fake);
        app()->call([new SendCampaignWhatsAppNotificationJob($inv->id), 'handle']);
        $this->assertTrue($inv->fresh()->whatsapp_sent);
        $this->assertStringContainsString('/invitations/'.$inv->id.'/whatsapp/accept', $fake->last);
    }

    public function test_monthly_quota_resolves_target_progress_and_warns_only_in_the_last_week(): void
    {
        $profile = $this->s->marketerProfile;
        $profile->update(['promotion_category' => PromotionCategory::VendorRequests->value]);
        PromotionCategoryTarget::create(['promotion_category' => 1, 'country_id' => null, 'monthly_minimum' => 5]);
        PromotionCategoryTarget::create(['promotion_category' => 1, 'country_id' => $this->s->country->id, 'monthly_minimum' => 3]);

        $service = app(PromotionQuotaService::class);
        $marketer = $this->s->marketer->fresh();
        $this->assertSame(3, $service->targetFor($marketer));

        $profile->update(['monthly_promotion_target' => 4]);
        $this->assertSame(4, $service->targetFor($marketer->fresh()));
        $profile->update(['monthly_promotion_target' => null]);

        // scenario already holds one accepted invitation responded to "now"
        $midMonth = now()->startOfMonth()->addDays(9);
        $this->travelTo($midMonth);
        $progress = $service->progressFor($marketer->fresh());
        $this->assertSame(3, $progress['target']);
        $this->assertTrue($progress['behind']);
        $this->assertFalse($progress['warning']);

        $this->travelTo(now()->endOfMonth()->subDays(2));
        $this->assertTrue($service->progressFor($marketer->fresh())['last_week']);
        $this->travelBack();
    }

    public function test_marketer_without_category_has_no_obligation(): void
    {
        $this->assertNull(app(PromotionQuotaService::class)->targetFor($this->s->marketer->fresh()));
        $this->assertSame(4, PromotionCategory::NawyProducts->value);
    }

    private function influencerInvitation(int $fee): MarketerCampaignInvitation
    {
        $job = MarketerJob::firstOrCreate(['key' => 'influencer'], ['name_ar' => 'x', 'name_en' => 'x', 'is_active' => true, 'sort_order' => 1]);
        MarketerMarketerJob::firstOrCreate(['marketer_id' => $this->s->marketer->id, 'marketer_job_id' => $job->id]);
        MarketerInfluencerFeeCountrySetting::create([
            'country_id' => $this->s->marketerCampaign->country_id, 'fee_per_influencer' => $fee,
            'currency' => 'AED', 'updated_by_admin_id' => null,
        ]);

        return MarketerCampaignInvitation::create([
            'campaign_id' => $this->s->marketerCampaign->id, 'marketer_id' => $this->s->marketer->id,
            'status' => 'pending', 'acceptance_window_hours' => 12, 'expires_at' => now()->addHours(12), 'referral_code' => 'I'.Str::random(8),
        ]);
    }

    public function test_seller_paid_fee_is_collected_now_when_balance_allows_and_retried_later_when_short(): void
    {
        $this->setting('marketer_influencer_fee_payer', 'vendor');
        $wallets = app(WalletService::class);
        $vendorWallet = $wallets->getOrCreateWallet('vendor', $this->s->vendor->id, 'AED');
        $inv = $this->influencerInvitation(900);

        // seller wallet is empty -> acceptance still succeeds, fee stays pending against the seller
        app(MarketerCampaignService::class)->acceptInvitation($inv);
        $inv->refresh();
        $this->assertSame('accepted', $inv->status);
        $this->assertSame('vendor', $inv->platform_fee_payer);
        $this->assertSame('pending', $inv->platform_fee_status);

        $wallets->credit($vendorWallet, 2000, 'test', null, 'topup');
        (new CollectPendingInfluencerFeesJob)->handle(app(MarketerCampaignService::class));

        $this->assertSame('paid', $inv->fresh()->platform_fee_status);
        $this->assertSame(2000 - 945, $vendorWallet->fresh()->balance, "fee 900 + 5% VAT 45");
    }

    public function test_marketer_paid_fee_keeps_current_behaviour(): void
    {
        $this->setting('marketer_influencer_fee_payer', 'marketer');
        $wallets = app(WalletService::class);
        $wallet = $wallets->getOrCreateWallet('marketer', $this->s->marketer->id, 'AED');
        $wallets->credit($wallet, 1000, 'test', null, 'topup');
        $inv = $this->influencerInvitation(900);

        app(MarketerCampaignService::class)->acceptInvitation($inv);

        $this->assertSame('paid', $inv->fresh()->platform_fee_status);
        $this->assertSame('marketer', $inv->fresh()->platform_fee_payer);
        $this->assertSame(1000 - 945, $wallet->fresh()->balance, "fee 900 + 5% VAT 45");
    }
}
