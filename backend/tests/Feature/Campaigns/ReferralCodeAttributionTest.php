<?php

namespace Tests\Feature\Campaigns;

use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Services\LastClickAttributionService;
use App\Services\MarketerCampaignService;
use App\Services\ReferralCodeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\Support\ReferralCodeFixtures as R;
use Tests\TestCase;

/**
 * P14 (R12): referral code lifecycle and attribution safety on reassignment.
 * Customer flow = GET /api/r/{code} (click) + HTTP checkout (order), service matrix = which invitation earns it.
 */
class ReferralCodeAttributionTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        config(['app.frontend_url' => 'https://shop.test']);
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        F::setting('marketer_attribution_window_days', 60);
        F::setting('marketer_referral_grace_days', 7);
    }

    private function conversionsFor($invitation)
    {
        return MarketerCampaignConversion::where('invitation_id', $invitation->id)->get();
    }

    /** Returns [campaign, A (pending), B (the replacement invitation after A is rejected)]. */
    private function replacedPair(string $how = 'reject'): array
    {
        $a = F::affiliate($this->s);
        $b = F::affiliate($this->s);
        $campaign = R::pendingCampaign($this->s, $a);
        $invA = R::invitationOf($campaign, $a);

        return [$campaign, $invA, $a, $b];
    }

    public function test_accepted_code_credits_its_marketer_for_a_scanned_customer(): void
    {
        $inv = $this->s->marketerCampaignInvitation;
        R::scan($this, $inv->referral_code, 'sess-active')->assertOk()->assertJsonPath('attributed', true);

        $order = R::placeOrder($this, $this->s, 'sess-active');

        $conversions = MarketerCampaignConversion::where('order_id', $order->id)->get();
        $this->assertCount(1, $conversions);
        $this->assertSame($inv->id, $conversions->first()->invitation_id);
    }

    public function test_pending_invitation_remembers_the_click_and_credits_it_once_accepted(): void
    {
        [$campaign, $invA, $a] = $this->replacedPair();
        R::scan($this, $invA->referral_code, 'sess-pending')->assertOk();
        $order = R::placeOrder($this, $this->s, 'sess-pending');
        $this->assertCount(0, MarketerCampaignConversion::where('order_id', $order->id)->get(), 'not accepted yet => no commission');

        app(MarketerCampaignService::class)->acceptInvitation($invA->fresh());
        $order2 = R::placeOrder($this, $this->s, 'sess-pending');

        $this->assertSame($invA->id, MarketerCampaignConversion::where('order_id', $order2->id)->firstOrFail()->invitation_id);
    }

    public function test_replaced_in_grace_credits_only_the_original_marketer_for_earlier_clicks(): void
    {
        [$campaign, $invA, $a, $b] = $this->replacedPair();
        R::scan($this, $invA->referral_code, 'sess-early');

        $this->travel(2)->hours();
        R::reject($invA->fresh());
        $invB = $campaign->invitations()->where('replaced_invitation_id', $invA->id)->firstOrFail();
        app(MarketerCampaignService::class)->acceptInvitation($invB);

        $this->travel(3)->days(); // inside the 7 day grace window
        $order = R::placeOrder($this, $this->s, 'sess-early');

        $conversions = MarketerCampaignConversion::where('order_id', $order->id)->get();
        $this->assertCount(1, $conversions);
        $this->assertSame($invA->id, $conversions->first()->invitation_id, 'original marketer, not the replacement');
        $this->assertCount(0, $this->conversionsFor($invB));
    }

    public function test_a_scan_after_the_replacement_is_not_attributed_to_anybody(): void
    {
        [$campaign, $invA] = $this->replacedPair();
        R::reject($invA->fresh());
        $invB = $campaign->invitations()->where('replaced_invitation_id', $invA->id)->firstOrFail();
        app(MarketerCampaignService::class)->acceptInvitation($invB);

        R::scan($this, $invA->referral_code, 'sess-late')->assertOk()->assertJsonPath('attributed', false);
        $order = R::placeOrder($this, $this->s, 'sess-late');

        $this->assertSame(0, MarketerCampaignConversion::where('order_id', $order->id)->count());
    }

    public function test_replaced_after_grace_credits_nobody(): void
    {
        [$campaign, $invA] = $this->replacedPair();
        R::scan($this, $invA->referral_code, 'sess-expired');
        R::reject($invA->fresh());
        $invB = $campaign->invitations()->where('replaced_invitation_id', $invA->id)->firstOrFail();
        app(MarketerCampaignService::class)->acceptInvitation($invB);

        $this->travel(8)->days();
        $order = R::placeOrder($this, $this->s, 'sess-expired');

        $this->assertSame(0, MarketerCampaignConversion::where('order_id', $order->id)->count());
        $this->assertSame(ReferralCodeResolver::STATE_EXPIRED, app(ReferralCodeResolver::class)->stateFor($invA->fresh()->load('campaign')));
    }

    public function test_grace_window_follows_the_setting_and_zero_disables_it(): void
    {
        [$campaign, $invA] = $this->replacedPair();
        R::scan($this, $invA->referral_code, 'sess-grace');
        R::reject($invA->fresh());
        $resolver = app(ReferralCodeResolver::class);
        $invA = $invA->fresh()->load('campaign');

        F::setting('marketer_referral_grace_days', 2);
        $this->assertSame(ReferralCodeResolver::STATE_GRACE, $resolver->stateFor($invA, now()->addDays(2)->subMinute()));
        $this->assertSame(ReferralCodeResolver::STATE_EXPIRED, $resolver->stateFor($invA, now()->addDays(3)));

        F::setting('marketer_referral_grace_days', 0);
        $this->assertSame(ReferralCodeResolver::STATE_EXPIRED, $resolver->stateFor($invA));
    }

    public function test_timed_out_invitation_follows_the_same_grace_rule(): void
    {
        [$campaign, $invA] = $this->replacedPair();
        R::scan($this, $invA->referral_code, 'sess-timeout');
        $this->travel(1)->hours();
        R::timeout($invA->fresh());
        $this->assertSame('timed_out', $invA->fresh()->status);

        $this->travel(2)->days();
        $order = R::placeOrder($this, $this->s, 'sess-timeout');

        $this->assertSame($invA->id, MarketerCampaignConversion::where('order_id', $order->id)->firstOrFail()->invitation_id);
    }

    public function test_cancelled_or_done_campaign_stops_attributing(): void
    {
        $inv = $this->s->marketerCampaignInvitation;
        R::scan($this, $inv->referral_code, 'sess-cancel');
        $this->s->marketerCampaign->update(['status' => 'cancelled']);

        $order = R::placeOrder($this, $this->s, 'sess-cancel');
        $this->assertSame(0, MarketerCampaignConversion::where('order_id', $order->id)->count());

        $this->s->marketerCampaign->update(['status' => 'done']);
        $this->assertSame(ReferralCodeResolver::STATE_CAMPAIGN_CLOSED, app(ReferralCodeResolver::class)->stateFor($inv->fresh()->load('campaign')));
    }

    public function test_one_order_never_credits_two_marketers_for_the_same_campaign(): void
    {
        $inv = $this->s->marketerCampaignInvitation;           // accepted, with a marketer listing
        $prev = MarketerCampaignInvitation::create([
            'campaign_id' => $inv->campaign_id,
            'marketer_id' => F::influencer($this->s)->id,
            'status' => 'rejected',
            'responded_at' => now()->subDay(),
            'referral_code' => 'OLD'.Str::upper(Str::random(7)),
        ]);
        $inv->update(['replaced_invitation_id' => $prev->id]);

        // The customer clicked the OLD marketer's code two days ago (before the replacement) ...
        $this->travelTo(now()->subDays(2));
        Cache::put('referral_click:sess-two', ['referral_code' => $prev->referral_code, 'clicked_at' => now()->toISOString()], now()->addDays(60));
        $this->travelBack();

        // ... then bought the replacement's listing AND another plain product in the same order.
        $order = R::placeOrder($this, $this->s, 'sess-two', [$this->s->vendorListingFbn], [$this->s->marketerListing]);

        $conversions = MarketerCampaignConversion::where('order_id', $order->id)->get();
        $this->assertCount(1, $conversions, 'only the first-attributed marketer earns on this order');
        $this->assertSame($inv->id, $conversions->first()->invitation_id);
        $this->assertSame(0, $this->conversionsFor($prev)->count());
    }

    public function test_re_resolving_an_order_never_double_credits(): void
    {
        $inv = $this->s->marketerCampaignInvitation;
        R::scan($this, $inv->referral_code, 'sess-twice');
        $order = R::placeOrder($this, $this->s, 'sess-twice');

        app(LastClickAttributionService::class)->resolveAndRecordConversion($order, 'sess-twice');
        app(LastClickAttributionService::class)->resolveAndRecordConversion($order, 'sess-twice');

        $this->assertSame(1, MarketerCampaignConversion::where('order_id', $order->id)->count());
        $this->assertSame(1, (int) $inv->fresh()->total_conversions);
    }

    public function test_replacement_gets_a_fresh_code_link_and_qr(): void
    {
        [$campaign, $invA] = $this->replacedPair();
        R::reject($invA->fresh());
        $invB = $campaign->invitations()->where('replaced_invitation_id', $invA->id)->firstOrFail();

        $this->assertNotSame($invA->referral_code, $invB->referral_code);
        $this->assertStringEndsWith('/r/'.$invB->referral_code, $invB->referral_link);
        $this->assertNotSame($invA->referral_link, $invB->referral_link);
        if ($invA->fresh()->qr_code_path && $invB->qr_code_path) {
            $this->assertNotSame($invA->fresh()->qr_code_path, $invB->qr_code_path);
        }
        // The old record keeps its own code for the grace window and the admin history.
        $this->assertSame($invA->referral_code, $invA->fresh()->referral_code);
    }

    // ───────── replaced_at (instead of a "replaced" status) ─────────

    public function test_replacement_stamps_replaced_at_but_keeps_the_real_outcome_status(): void
    {
        foreach (['reject', 'timeout'] as $how) {
            [$campaign, $invA] = $this->replacedPair();
            $how === 'reject' ? R::reject($invA->fresh()) : R::timeout($invA->fresh());

            $old = $invA->fresh();
            $this->assertSame($how === 'reject' ? 'rejected' : 'timed_out', $old->status, 'reason is not erased by the replacement');
            $this->assertNotNull($old->replaced_at);
            $this->assertNotNull($old->responded_at);
            $this->assertTrue($old->replaced_at->greaterThanOrEqualTo($old->responded_at));
            $invB = $campaign->invitations()->where('replaced_invitation_id', $old->id)->firstOrFail();
            $this->assertNull($invB->replaced_at, 'the live replacement itself is not replaced');
        }
    }

    public function test_no_replacement_available_leaves_replaced_at_empty(): void
    {
        $a = F::influencer($this->s); // nobody else to take over
        $campaign = R::pendingCampaign($this->s, $a);
        $invA = R::invitationOf($campaign, $a);
        R::reject($invA->fresh());

        $this->assertSame('rejected', $invA->fresh()->status);
        $this->assertNull($invA->fresh()->replaced_at);
    }

    public function test_grace_attribution_is_anchored_on_the_response_not_on_replaced_at(): void
    {
        [$campaign, $invA] = $this->replacedPair();
        R::scan($this, $invA->referral_code, 'sess-early');
        $this->travel(2)->hours();
        R::reject($invA->fresh());
        // The replacement happened later than the rejection: replaced_at must not move the grace anchor.
        $invA->fresh()->update(['replaced_at' => now()->addDays(30)]);

        $resolver = app(ReferralCodeResolver::class);
        $this->assertTrue($resolver->closedAt($invA->fresh())->equalTo($invA->fresh()->responded_at));
        $this->assertSame(ReferralCodeResolver::STATE_GRACE, $resolver->stateFor($invA->fresh()));

        $this->travel(3)->days();
        $order = R::placeOrder($this, $this->s, 'sess-early');
        $this->assertCount(1, $this->conversionsFor($invA));
        $this->assertSame($invA->id, MarketerCampaignConversion::where('order_id', $order->id)->sole()->invitation_id);

        $this->travel(5)->days(); // 8 days after the rejection: expired, whatever replaced_at says
        $this->assertSame(ReferralCodeResolver::STATE_EXPIRED, $resolver->stateFor($invA->fresh()));
    }
}
