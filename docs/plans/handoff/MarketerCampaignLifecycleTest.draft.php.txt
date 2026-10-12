<?php

namespace Tests\Feature\Campaigns;

use App\Contracts\WhatsAppGateway;
use App\Enums\CancelActor;
use App\Enums\PromotionCategory;
use App\Enums\VendorGlobalStatus;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Jobs\CollectPendingInfluencerFeesJob;
use App\Jobs\GenerateVendorPayoutsJob;
use App\Jobs\ReleaseMarketerPendingCommissionJob;
use App\Jobs\SendCampaignWhatsAppNotificationJob;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCampaignSample;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\MarketerListing;
use App\Models\MarketerProfile;
use App\Models\MarketerPromotionFine;
use App\Models\MarketerPromotionReminder;
use App\Models\MarketerSaleSettlement;
use App\Models\Order;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Customer\CartService;
use App\Services\Inventory\InventoryService;
use App\Services\Marketer\InvitationWhatsAppMessage;
use App\Services\Marketer\MarketerSaleSettlementService;
use App\Services\MarketerCampaignService;
use App\Services\OrderStateMachine;
use App\Services\PayoutCalculationService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\Support\PeerPromotionFixtures as P;
use Tests\Support\PromotionQuotaFixtures as Q;
use Tests\Support\ReferralCodeFixtures as R;
use Tests\TestCase;

/**
 * P15: the cross-panel lifecycle. Every step goes through the real HTTP route / job / service of the panel that owns
 * it (Partner = guard vendor, Admin = guard admin, Marketer = guard marketer, customer API for the order) and the
 * result is asserted on the page where each party would look at it. WhatsApp goes through the fake gateway.
 */
class MarketerCampaignLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private WhatsAppGateway $gateway;

    private VendorAdmin $vendorAdmin;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // delayed timeout jobs must not fire; the jobs under test are run explicitly
        Notification::fake();
        $this->withoutVite();
        config(['app.frontend_url' => 'https://shop.test']);

        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->gateway = Q::fakeGateway();
        $this->app->instance(WhatsAppGateway::class, $this->gateway);

        F::setting('marketer_influencer_fee_payer', 'vendor');
        F::setting('marketer_attribution_window_days', 60);
        F::setting('marketer_referral_grace_days', 7);
        F::categoryQty($this->s, platform: 1, influencer: 2, affiliate: 0, minStock: 10);
        P::commission($this->s, 3000);
        MarketerInfluencerFeeCountrySetting::updateOrCreate(
            ['country_id' => $this->s->country->id],
            ['fee_per_influencer' => 1000, 'currency' => 'AED', 'updated_by_admin_id' => null]
        );

        VendorBankAccount::create([
            'vendor_id' => $this->s->vendor->id, 'account_holder_name' => 'Test Vendor', 'bank_name' => 'Test Bank',
            'iban' => 'AE070331234567890123456', 'account_number_encrypted' => 'enc', 'currency' => 'AED',
            'is_primary' => true, 'verification_status' => 'verified',
        ]);
        $this->s->vendor->update(['global_status' => VendorGlobalStatus::Active]);

        $this->vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create', 'marketer_campaigns.cancel', 'marketer_campaigns.edit', 'finance.view', 'finance.payouts.view'] as $name) {
            $this->vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }

        $perms = [
            'vendors.assigned_only', 'marketer_campaigns.view', 'marketer_campaigns.approve', 'marketer_campaigns.reject', 'marketer_campaigns.create',
            'payouts.view', 'payouts.approve', 'wallets.view', 'marketers.view', 'marketers.manage',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(array_slice($perms, 1));
    }

    // ───────── helpers ─────────

    /** An approved influencer with a 30% platform cut and a campaign WhatsApp number. */
    private function influencer(int $cut = 30)
    {
        $m = F::influencer($this->s);
        $m->update(['whatsapp_for_campaigns' => '+97150'.random_int(1000000, 9999999), 'country_id' => $this->s->country->id]);
        MarketerProfile::where('marketer_id', $m->id)->update(['platform_cut_percentage' => $cut, 'profile_slug' => 'p-'.Str::lower(Str::random(6))]);
        F::marketerLogin($m);

        return $m->fresh();
    }

    private function login($marketer)
    {
        return MarketerAdmin::where('marketer_id', $marketer->id)->firstOrFail();
    }

    private function stock(): int
    {
        return app(InventoryService::class)->availableStock($this->s->vendorListingFbn->fresh());
    }

    private function runWhatsApp(MarketerCampaignInvitation $inv): void
    {
        app()->call([new SendCampaignWhatsAppNotificationJob($inv->id), 'handle']);
    }

    /** Vendor creates the campaign over HTTP from the listing tab. */
    private function vendorCreates(array $marketers): MarketerCampaign
    {
        $response = $this->actingAs($this->vendorAdmin, 'vendor')->post(route('partner.marketer-campaigns.store'), [
            'vendor_listing_id' => $this->s->vendorListingFbn->id,
            'marketer_ids' => array_map(fn ($m) => $m->id, $marketers),
        ]);
        $response->assertSessionMissing('error')->assertSessionHasNoErrors()->assertRedirect();

        return MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->latest()->firstOrFail();
    }

    private function adminApproves(MarketerCampaign $campaign): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.marketer-campaigns.approve', $campaign->id))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');
        $this->assertSame('active', $campaign->fresh()->status);
    }

    private function acceptViaLink(MarketerCampaignInvitation $inv): void
    {
        $this->runWhatsApp($inv);
        $links = app(InvitationWhatsAppMessage::class)->links($inv);
        $this->get($links['accept'])->assertOk();
        $this->assertSame('pending', $inv->fresh()->status, 'a link preview alone must not accept');
        $this->post($links['accept'])->assertOk()->assertSee(__('marketer.invitations.whatsapp_accepted'));
        $this->assertSame('accepted', $inv->fresh()->status);
    }

    private function placeAndDeliver(string $session, string $gateway = 'wallet', ?array $listings = null, array $mlLines = [], int $returnDays = -1): Order
    {
        return $this->deliver(R::placeOrder($this, $this->s, $session, $listings ?? [$this->s->vendorListingFbn], $mlLines, $gateway));
    }

    private function deliver(Order $order, int $returnDays = -1): Order
    {
        $machine = app(OrderStateMachine::class);
        foreach ($order->subOrders()->get() as $so) {
            foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
                $machine->transition($so, $step, CancelActor::System);
            }
            $so->forceFill(['cod_remittance_confirmed' => true])->save();
        }
        $order->items()->update(['return_eligible_until' => now()->addDays($returnDays)->toDateString()]);

        return $order->fresh();
    }

    private function runStatement(): void
    {
        (new GenerateVendorPayoutsJob(now()->subDays(7), now()->addDay()))->handle(app(PayoutCalculationService::class));
    }

    private function wallet(string $type, string $id): ?Wallet
    {
        return Wallet::where('owner_type', $type)->where('owner_id', $id)->first();
    }

    // ───────── (1) happy path ─────────

    public function test_happy_path_vendor_to_admin_to_influencer_to_customer_to_statement_and_withdrawal(): void
    {
        $a = $this->influencer();
        $b = $this->influencer();

        // The seller can pay one of the two fees from his wallet right away (fee 1000 + 5% VAT = 1050), not both.
        $vendorWallet = app(WalletService::class)->getOrCreateWallet('vendor', $this->s->vendor->id, 'AED');
        app(WalletService::class)->credit($vendorWallet, 1050, 'test', null, 'topup');

        // ── Partner: listing tab offers the campaign, the create form quotes fee + VAT, samples are announced.
        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.listings.show', $this->s->vendorListingFbn->id))
            ->assertOk()
            ->assertSee(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id), false);

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))
            ->assertOk()
            ->assertSee('data-testid="quote-vat-row"', false)
            ->assertSee('data-testid="sample-plan"', false)
            ->assertSee($a->name)
            ->assertSee($b->name);

        $quote = $this->actingAs($this->vendorAdmin, 'vendor')
            ->getJson(route('partner.listings.marketer-fee', ['listing_id' => $this->s->vendorListingFbn->id, 'marketer_ids' => [$a->id, $b->id]]))
            ->assertOk()->json();
        $this->assertSame([2000, 100, 2100], [$quote['total_fee'], $quote['total_fee_vat'], $quote['total_fee_with_vat']]);

        $campaign = $this->vendorCreates([$a, $b]);
        $this->assertSame('pending_admin', $campaign->status);
        $this->assertSame(0, $campaign->invitations()->count(), 'invitations only follow the admin approval');

        $show = route('partner.marketer-campaigns.show', $campaign->id);
        $this->actingAs($this->vendorAdmin, 'vendor')->get($show)->assertOk()
            ->assertSee(__('partner.marketer_campaigns_my.samples_summary', ['count' => 1, 'units' => 1, 'dispatched' => 0]));

        // ── Admin: approves -> invitations + 2 influencer samples; the admin campaign page lists them.
        $this->adminApproves($campaign);
        $invA = R::invitationOf($campaign, $a);
        $invB = R::invitationOf($campaign, $b);
        $this->assertSame(3, $campaign->samples()->count());
        $this->actingAs($this->admin, 'admin')->get(route('admin.marketer-campaigns.show', $campaign->id))
            ->assertOk()
            ->assertSee(__('admin.marketer_campaigns.samples_summary', ['count' => 3, 'units' => 5, 'dispatched' => 0]))
            ->assertSee($a->name)->assertSee($b->name);
        Queue::assertPushed(SendCampaignWhatsAppNotificationJob::class, fn ($j) => $j->invitationId === $invA->id);
        Queue::assertPushed(SendCampaignWhatsAppNotificationJob::class, fn ($j) => $j->invitationId === $invB->id);

        // ── WhatsApp: delivered once per invitation with signed accept / reject links.
        $this->runWhatsApp($invA);
        $this->runWhatsApp($invA);
        $this->assertCount(1, $this->gateway->sent);
        $this->assertSame($a->whatsapp_for_campaigns, $this->gateway->sent[0]['to']);
        $this->assertStringContainsString('signature=', $this->gateway->sent[0]['message']);
        $this->assertTrue($invA->fresh()->whatsapp_sent);

        // ── Influencers accept through the signed link (A first, while the seller wallet can pay the fee).
        $this->acceptViaLink($invA);
        $this->acceptViaLink($invB);
        $invA->refresh();
        $invB->refresh();

        // ── Fee settlement, wallet variant: the first fee left the seller wallet at acceptance.
        $this->assertSame(['paid', 'wallet', 1000, 50], [$invA->platform_fee_status, $invA->platform_fee_paid_via, (int) $invA->platform_fee_amount, (int) $invA->platform_fee_vat_amount]);
        $this->assertSame(0, $vendorWallet->fresh()->balance);
        // Statement variant: the second one found an empty wallet and waits for the weekly statement.
        $this->assertSame('pending', $invB->platform_fee_status);
        (new CollectPendingInfluencerFeesJob)->handle(app(MarketerCampaignService::class));
        $this->assertSame('pending', $invB->fresh()->platform_fee_status, 'still no money: the job leaves it for the statement');

        $this->actingAs($this->vendorAdmin, 'vendor')->get($show)->assertOk()
            ->assertSee('data-testid="invitation-fee-vat"', false)
            ->assertSee(__('partner.marketer_campaigns.fee_status_pending'));

        // ── Admin dispatches A's sample: stock goes down once, commission snapshotted, repeat refused.
        $sampleA = MarketerCampaignSample::where('invitation_id', $invA->id)->sole();
        $before = $this->stock();
        $url = route('admin.marketer-campaigns.samples.update', [$campaign->id, $sampleA->id]);
        $this->actingAs($this->admin, 'admin')->patch($url, ['status' => 'dispatched'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin, 'admin')->patch($url, ['status' => 'dispatched'])->assertStatus(422);
        $sampleA->refresh();
        $this->assertSame($before - 2, $this->stock(), 'influencer sample of 2 units, taken once');
        $this->assertGreaterThan(0, (int) $sampleA->commission_amount);
        $this->actingAs($this->admin, 'admin')->get(route('admin.marketer-campaigns.show', $campaign->id))
            ->assertOk()->assertSee(__('admin.marketer_campaigns.samples_summary', ['count' => 3, 'units' => 5, 'dispatched' => 1]));
        $this->actingAs($this->login($a), 'marketer')->get(route('marketer.samples.index'))
            ->assertOk()->assertSee(route('marketer.samples.received', $sampleA->id), false);

        // ── Customer: scans A's code and orders (paid from the customer wallet) -> conversion for A only.
        // A listing price that lets the statement absorb the sample commission and the pending fee.
        $this->s->vendorListingFbn->update(['price' => 10000]);
        R::scan($this, $invA->referral_code, 'sess-happy')->assertOk()->assertJsonPath('attributed', true);
        $order = $this->placeAndDeliver('sess-happy');
        $conversion = MarketerCampaignConversion::where('order_id', $order->id)->sole();
        $this->assertSame($invA->id, $conversion->invitation_id);

        // ── Conversion approved: gross -> admin cut 30% -> VAT on the cut -> net.
        app()->call([new ApproveMarketerConversionsJob, 'handle']);
        $conversion->refresh();
        $gross = (int) $conversion->gross_commission_amount;
        $this->assertSame(3000, $gross);
        $this->assertSame(900, (int) $conversion->platform_cut_amount);
        $this->assertSame(45, (int) $conversion->platform_cut_vat_amount, '5% VAT on the 900 cut');
        $this->assertSame(3000 - 900 - 45, (int) $conversion->net_commission_amount);
        $net = (int) $conversion->net_commission_amount;

        // Marketer commissions page shows the breakdown; the campaign appears under active campaigns.
        $this->actingAs($this->login($a), 'marketer')->get(route('marketer.finance.commissions'))
            ->assertOk()->assertSee(number_format($net));
        $this->actingAs($this->login($a), 'marketer')->get(route('marketer.campaigns.active'))
            ->assertOk()->assertSee($invA->referral_code);

        // ── Weekly vendor statement: sale + sample line (price 0) + the statement-settled influencer fee of B.
        $this->runStatement();
        $payout = Payout::where('vendor_id', $this->s->vendor->id)->sole();
        $sub = $order->subOrders()->where('vendor_id', $this->s->vendor->id)->sole();
        $sampleLine = PayoutItem::where('campaign_sample_id', $sampleA->id)->sole();
        $feeLine = PayoutItem::where('invitation_id', $invB->id)->sole();
        $this->assertSame('promotion_sample', $sampleLine->item_type);
        $this->assertSame(0, (int) $sampleLine->gross);
        $this->assertSame((int) $sampleA->commission_amount, (int) $sampleLine->commission);
        $this->assertSame('influencer_fee', $feeLine->item_type);
        $this->assertSame(-1050, (int) $feeLine->net);
        $this->assertSame((int) $sub->vendor_payout - 1050 - (int) $sampleA->commission_amount - (int) PayoutItem::where('item_type', 'promotion_sample')->where('id', '!=', $sampleLine->id)->sum('commission'), (int) $payout->net_amount);
        $this->assertSame(['paid', 'statement'], [$invB->fresh()->platform_fee_status, $invB->fresh()->platform_fee_paid_via]);
        $this->runStatement();
        $this->assertSame(1, PayoutItem::where('invitation_id', $invB->id)->count(), 'never charged twice');
        $this->assertSame(1, PayoutItem::where('campaign_sample_id', $sampleA->id)->count());

        $statement = $this->actingAs($this->vendorAdmin, 'vendor')->get(route('partner.payouts.show', $payout->payout_number))
            ->assertOk()
            ->assertSee('id="influencer-fee-lines"', false)
            ->assertSee(__('admin.payouts.promotion_sample'))
            ->assertSee($sub->sub_order_number)
            ->assertSee('1,050.00');
        $this->actingAs($this->admin, 'admin')->get(route('admin.payouts.show', $payout->id))
            ->assertOk()
            ->assertSee(__('admin.payouts.influencer_fee'))
            ->assertSee(__('admin.payouts.promotion_sample'));
        $this->actingAs($this->admin, 'admin')->get(route('admin.marketer-campaigns.show', $campaign->id))->assertOk();

        // ── Marketer wallet and withdrawal use the NET commission.
        $conversion->update(['approved_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob)->handle();
        $wallet = $this->wallet('marketer', $a->id);
        $this->assertSame($net, $wallet->balance);
        $this->actingAs($this->login($a), 'marketer')->get(route('marketer.finance.wallet'))
            ->assertOk()->assertSee(number_format($net));

        $this->actingAs($this->login($a), 'marketer')
            ->post(route('marketer.finance.wallet.withdraw'), ['amount' => $net, 'bank_name' => 'Bank', 'bank_iban' => 'AE000000000000'])
            ->assertSessionHasNoErrors();
        $wallet->refresh();
        $this->assertSame(0, $wallet->balance);
        $this->assertSame($net, $wallet->pending_balance);
        $tx = WalletTransaction::where('wallet_id', $wallet->id)->where('source_type', 'marketer_conversion')->sole();
        $this->assertSame($net, $tx->amount);

        $this->actingAs($this->admin, 'admin')->get(route('admin.wallets.withdrawals'))
            ->assertOk()->assertSee('data-marketer-breakdown', false);
    }

    // ───────── (2) reject + timeout -> replacement ─────────

    public function test_reject_and_timeout_are_replaced_the_old_code_lives_through_grace_and_samples_follow(): void
    {
        [$a, $b] = [$this->influencer(), $this->influencer()];
        [$c, $d] = [$this->influencer(), $this->influencer()]; // spares the system can pick as replacements

        $campaign = $this->vendorCreates([$a, $b]);
        $this->adminApproves($campaign);
        $invA = R::invitationOf($campaign, $a);
        $invB = R::invitationOf($campaign, $b);
        $this->assertSame(3, $campaign->samples()->count(), '2 influencers + 1 platform sample');
        $sampleA = MarketerCampaignSample::where('invitation_id', $invA->id)->sole();
        $sampleB = MarketerCampaignSample::where('invitation_id', $invB->id)->sole();

        // A customer clicks A's code while A's invitation is still open.
        R::scan($this, $invA->referral_code, 'sess-early')->assertOk();

        // A rejects two hours later through the signed WhatsApp link.
        $this->travel(2)->hours();
        $this->runWhatsApp($invA);
        $links = app(InvitationWhatsAppMessage::class)->links($invA);
        $this->get($links['reject'])->assertOk();
        $this->assertSame('pending', $invA->fresh()->status, 'a link preview alone must not reject');
        $this->post($links['reject'], ['reason' => 'busy'])->assertOk();
        $this->assertSame('rejected', $invA->fresh()->status);
        $invA2 = MarketerCampaignInvitation::where('replaced_invitation_id', $invA->id)->sole();
        $this->assertContains($invA2->marketer_id, [$c->id, $d->id]);

        // B never answers: the timeout hands the slot to the other spare.
        R::timeout($invB->fresh());
        $this->assertSame('timed_out', $invB->fresh()->status);
        $invB2 = MarketerCampaignInvitation::where('replaced_invitation_id', $invB->id)->sole();
        $this->assertNotSame($invA2->marketer_id, $invB2->marketer_id);

        // Samples follow the replacements: same rows, same total, none cancelled.
        $this->assertSame(3, $campaign->samples()->count());
        $this->assertSame($invA2->id, $sampleA->fresh()->invitation_id);
        $this->assertSame($invB2->id, $sampleB->fresh()->invitation_id);
        $this->assertSame(0, $campaign->samples()->where('status', 'cancelled')->count());

        // Marketer panel: the rejected marketer sees the old code as being in its grace period.
        $this->actingAs($this->login($a), 'marketer')->get(route('marketer.invitations.index'))
            ->assertOk()->assertSee('data-code-inactive', false);
        // Admin panel: both transfers are charted in the code history with the grace state.
        $html = $this->actingAs($this->admin, 'admin')->get(route('admin.marketer-campaigns.show', $campaign->id))
            ->assertOk()->assertSee(__('admin.marketer_campaigns.code_history_title'))->getContent();
        $this->assertStringContainsString('data-code-row="'.$invA->referral_code.'" data-code-state="grace"', $html);
        $this->assertStringContainsString('data-code-row="'.$invB->referral_code.'" data-code-state="grace"', $html);
        // Partner panel: the seller sees who took over.
        $this->actingAs($this->vendorAdmin, 'vendor')->get(route('partner.marketer-campaigns.show', $campaign->id))
            ->assertOk()->assertSee(Marketer::find($invA2->marketer_id)->name);

        // The replacements accept.
        $this->acceptViaLink($invA2->fresh());
        $this->acceptViaLink($invB2->fresh());

        // Old code, earlier click, inside grace: the ORIGINAL marketer is credited, not the replacement.
        $this->travel(3)->days();
        $early = $this->placeAndDeliver('sess-early', 'cod');
        $this->assertSame($invA->id, MarketerCampaignConversion::where('order_id', $early->id)->sole()->invitation_id);
        $this->assertSame(0, MarketerCampaignConversion::where('invitation_id', $invA2->id)->count());

        // A scan of the old code after the replacement attributes nobody; the new code does.
        R::scan($this, $invA->referral_code, 'sess-late')->assertOk()->assertJsonPath('attributed', false);
        $late = R::placeOrder($this, $this->s, 'sess-late', [$this->s->vendorListingFbn], [], 'cod');
        $this->assertSame(0, MarketerCampaignConversion::where('order_id', $late->id)->count());

        R::scan($this, $invA2->referral_code, 'sess-new')->assertOk()->assertJsonPath('attributed', true);
        $fresh = R::placeOrder($this, $this->s, 'sess-new', [$this->s->vendorListingFbn], [], 'cod');
        $this->assertSame($invA2->id, MarketerCampaignConversion::where('order_id', $fresh->id)->sole()->invitation_id);

        // After the grace window the old code is expired on the marketer and admin pages.
        $this->travel(6)->days();
        $this->actingAs($this->login($a), 'marketer')->get(route('marketer.invitations.index'))
            ->assertOk()->assertSee(__('marketer.invitations.code_inactive_expired'));
        $this->assertStringContainsString('data-code-row="'.$invA->referral_code.'" data-code-state="expired"',
            $this->actingAs($this->admin, 'admin')->get(route('admin.marketer-campaigns.show', $campaign->id))->getContent());
    }

    // ───────── (3) cancel ─────────

    public function test_cancelling_an_active_campaign_cancels_open_samples_and_switches_the_listing_flag_off(): void
    {
        [$a, $b] = [$this->influencer(), $this->influencer()];
        $campaign = $this->vendorCreates([$a, $b]);
        $this->adminApproves($campaign);
        $invA = R::invitationOf($campaign, $a);
        $this->acceptViaLink($invA);
        $this->assertTrue((bool) $this->s->vendorListingFbn->fresh()->campaign_enabled);

        // One sample already left the warehouse: it stays as it is.
        $sampleA = MarketerCampaignSample::where('invitation_id', $invA->id)->sole();
        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.marketer-campaigns.samples.update', [$campaign->id, $sampleA->id]), ['status' => 'dispatched'])
            ->assertSessionHasNoErrors();
        $stock = $this->stock();

        // Partner cancels: a reason is mandatory.
        $url = route('partner.marketer-campaigns.cancel-active', $campaign->id);
        $this->actingAs($this->vendorAdmin, 'vendor')->post($url, ['reason' => 'short'])->assertSessionHasErrors('reason');
        $this->assertSame('active', $campaign->fresh()->status);
        $this->actingAs($this->vendorAdmin, 'vendor')->post($url, ['reason' => 'The product is being discontinued'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('cancelled', $campaign->fresh()->status);
        $this->assertFalse((bool) $this->s->vendorListingFbn->fresh()->campaign_enabled);
        $this->assertSame('dispatched', $sampleA->fresh()->status);
        $this->assertSame(2, $campaign->samples()->where('status', 'cancelled')->count(), 'B and the platform sample');
        $this->assertSame($stock, $this->stock(), 'cancelling gives nothing back for the dispatched sample and takes nothing more');

        // No more attribution on the cancelled campaign.
        R::scan($this, $invA->referral_code, 'sess-cancel');
        $order = R::placeOrder($this, $this->s, 'sess-cancel', [$this->s->vendorListingFbn], [], 'cod');
        $this->assertSame(0, MarketerCampaignConversion::where('order_id', $order->id)->count());

        // Each panel shows the cancelled state; the seller can open a fresh campaign afterwards.
        $this->actingAs($this->vendorAdmin, 'vendor')->get(route('partner.marketer-campaigns.show', $campaign->id))
            ->assertOk()->assertSee(__('partner.marketer_campaigns_my.samples_summary', ['count' => 1, 'units' => 2, 'dispatched' => 1]));
        $this->actingAs($this->admin, 'admin')->get(route('admin.marketer-campaigns.show', $campaign->id))
            ->assertOk()->assertSee(__('admin.marketer_campaigns.samples_summary', ['count' => 1, 'units' => 2, 'dispatched' => 1]));
        $this->actingAs($this->login($a), 'marketer')->get(route('marketer.campaigns.active'))
            ->assertOk()->assertDontSee($invA->referral_code);
        $this->actingAs($this->vendorAdmin, 'vendor')->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))->assertOk();
    }

    // ───────── (4) peer promotion ─────────

    public function test_peer_promotion_credits_the_promoter_and_settles_the_owner(): void
    {
        $owner = $this->influencer();
        $promoter = $this->influencer();
        $ownListing = P::ownListing($this->s, $owner, 20);
        $second = P::ownListing($this->s, $owner, 20);

        // Marketer panel (promoter): the owner's listing is offered, the request starts an active promotion at once.
        $this->actingAs($this->login($promoter), 'marketer')->get(route('marketer.campaigns.peer-products'))
            ->assertOk()->assertSee($owner->name)->assertSee($ownListing->id);
        $this->actingAs($this->login($promoter), 'marketer')
            ->post(route('marketer.campaigns.request'), ['listing_id' => $ownListing->id, 'listing_type' => 'marketer_listing'])
            ->assertRedirect(route('marketer.campaigns.active'))
            ->assertSessionHas('success', __('marketer.campaigns.peer.started'));
        $campaign = MarketerCampaign::where('marketer_listing_id', $ownListing->id)->sole();
        $invitation = $campaign->invitations()->sole();
        $this->assertSame('active', $campaign->status);

        // Month rule: a second product of the same owner in the same month is refused.
        $this->actingAs($this->login($promoter), 'marketer')
            ->post(route('marketer.campaigns.request'), ['listing_id' => $second->id, 'listing_type' => 'marketer_listing'])
            ->assertSessionHas('error', __('marketer.campaigns.peer.errors.monthly_limit'));
        $this->assertSame(1, MarketerCampaign::whereNotNull('marketer_listing_id')->count());
        $this->actingAs($this->login($promoter), 'marketer')->get(route('marketer.campaigns.active'))
            ->assertOk()->assertSee($invitation->referral_code);

        // The owner sees the promoter; an outsider does not.
        $this->actingAs($this->login($owner), 'marketer')->get(route('marketer.campaigns.promoted-by-others'))
            ->assertOk()->assertSee($promoter->name)->assertSee(__('marketer.campaigns.peer.promoter'));
        $outsider = $this->influencer();
        $this->actingAs($this->login($outsider), 'marketer')->get(route('marketer.campaigns.promoted-by-others'))
            ->assertOk()->assertDontSee($promoter->name);

        // Admin panel: the campaign page names both the owner and the promoter.
        $this->actingAs($this->admin, 'admin')->get(route('admin.marketer-campaigns.show', $campaign->id))
            ->assertOk()->assertSee($promoter->name)->assertSee($owner->name);

        // Customer buys 2 units through the promoter's listing (owner's stock, owner is the seller).
        $proxy = MarketerListing::where('invitation_id', $invitation->id)->sole();
        $order = $this->orderThroughProxy($proxy, 2);
        $sub = $order->subOrders()->sole();
        $this->assertSame($owner->id, $sub->marketer_id);
        $this->assertSame(6000, (int) $sub->marketer_commission);
        $conversion = MarketerCampaignConversion::where('order_id', $order->id)->sole();
        $this->assertSame($invitation->id, $conversion->invitation_id);

        $this->deliver($order);
        app()->call([new ApproveMarketerConversionsJob, 'handle']);
        app(MarketerSaleSettlementService::class)->settleAllEligible();

        // Owner settlement: gross - platform commission - VAT - promoter commission.
        $row = MarketerSaleSettlement::where('sub_order_id', $sub->id)->sole();
        $this->assertSame($owner->id, $row->marketer_id);
        $this->assertSame('peer', $row->sold_via);
        $this->assertSame(6000, $row->peer_commission_amount);
        $this->assertSame($row->net_amount, $this->wallet('marketer', $owner->id)->pending_balance);
        $this->assertGreaterThan(0, $row->net_amount);

        // Promoter earns through the conversion flow (net of admin cut), not through the owner settlement.
        $conversion->refresh();
        $this->assertSame('approved', $conversion->status);
        $this->assertSame(6000 - (int) $conversion->platform_cut_amount - (int) $conversion->platform_cut_vat_amount, (int) $conversion->net_commission_amount);
        $this->assertSame((int) $conversion->net_commission_amount, $this->wallet('marketer', $promoter->id)->pending_balance);

        // Money moves to the available balance after the clearing window; both marketers see it on their wallet page.
        $row->update(['credited_at' => now()->subDays(4)]);
        $conversion->update(['approved_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob)->handle();
        $this->assertSame($row->net_amount, $this->wallet('marketer', $owner->id)->balance);
        $this->assertSame((int) $conversion->net_commission_amount, $this->wallet('marketer', $promoter->id)->balance);
        $this->actingAs($this->login($owner), 'marketer')->get(route('marketer.finance.wallet'))
            ->assertOk()->assertSee(number_format($row->net_amount));
        $this->actingAs($this->login($promoter), 'marketer')->get(route('marketer.finance.commissions'))
            ->assertOk()->assertSee(number_format($conversion->net_commission_amount));
    }

    private function orderThroughProxy(MarketerListing $proxy, int $qty): Order
    {
        $this->actingAs($this->s->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($this->s->customer, $this->s->country->id, $this->s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $proxy->id, 'quantity' => $qty,
            'unit_price' => (int) $proxy->price, 'added_at' => now(),
        ]);
        $response = $this->postJson("/api/customer/v1/{$this->s->country->site_code}/checkout/place-order", [
            'address_id' => $this->s->customerAddress->id,
            'country_payment_gateway_id' => $this->s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(201);

        return Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();
    }

    // ───────── (5) monthly quota ─────────

    public function test_month_close_fines_a_short_marketer_once_and_the_reminder_goes_out_once(): void
    {
        $m = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 3, 5000);
        $m->update(['country_id' => $this->s->country->id]);
        $ok = $this->influencer();

        // One accepted vendor promotion this month (target 3), through the real chain.
        $campaign = $this->vendorCreates([$m]);
        $this->adminApproves($campaign);
        $this->acceptViaLink(R::invitationOf($campaign, $m));
        $this->gateway->sent = [];

        $this->actingAs($this->login($m), 'marketer')->get(route('marketer.dashboard'))->assertOk()->assertSee('1/3');

        // Last week of the month: one WhatsApp + one reminder row, however often the command runs.
        $this->travelTo(now()->endOfMonth()->subDays(2));
        Artisan::call('marketers:promotion-quota-reminders');
        Artisan::call('marketers:promotion-quota-reminders');
        $this->actingAs($this->login($m), 'marketer')->get(route('marketer.dashboard'))
            ->assertOk()->assertSee('data-quota-penalty', false)->assertSee('5,000');
        $this->assertCount(1, $this->gateway->sent);
        $this->assertSame($m->whatsapp_for_campaigns, $this->gateway->sent[0]['to']);
        $this->assertSame(1, MarketerPromotionReminder::where('marketer_id', $m->id)->count());
        $this->assertSame(0, MarketerPromotionReminder::where('marketer_id', $ok->id)->count());

        // First days of the next month: the close creates exactly one fine, a rerun adds nothing.
        $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addDays(2));
        Artisan::call('promotion:close-month');
        Artisan::call('promotion:close-month');
        $fine = MarketerPromotionFine::where('marketer_id', $m->id)->sole();
        $this->assertSame([5000, 1, 3, MarketerPromotionFine::PENDING], [$fine->amount, $fine->done, $fine->target, $fine->status]);
        $this->assertSame(1, MarketerPromotionFine::count());

        $this->actingAs($this->login($m), 'marketer')->get(route('marketer.dashboard'))
            ->assertOk()->assertSee('data-pending-fines', false)->assertSee('5,000');
        $this->actingAs($this->admin, 'admin')->get(route('admin.marketer-fines.index'))
            ->assertOk()->assertSee($m->name)->assertSee('5,000')->assertSee($fine->period);
    }
}
