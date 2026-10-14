<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CancelActor;
use App\Enums\MarketingFeeType;
use App\Enums\VendorGlobalStatus;
use App\Jobs\AffiliateFeeSettlementJob;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Jobs\GenerateVendorPayoutsJob;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignFeeAccrual;
use App\Models\MarketerCampaignFeeSettlement;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Models\Order;
use App\Models\PayoutItem;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AffiliateFeeAccrualService;
use App\Services\AffiliateFeeSettlementService;
use App\Services\Customer\CartService;
use App\Services\MarketerCampaignService;
use App\Services\MarketerConversionReversalService;
use App\Services\OrderStateMachine;
use App\Services\PayoutCalculationService;
use App\Services\Vendor\TransactionFeedService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P6 (D4/D6/D10): accrual of the marketing fees on affiliate commissions (plan section 6: 3.90 then +1.00 = 4.90),
 * reversal, settlement on finish / budget exhaustion (wallet, else weekly statement), idempotency and the history UI.
 */
class AffiliateFeeAccrualAndSettlementTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private MarketerCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
        Permission::firstOrCreate(['name' => 'payouts.approve', 'guard_name' => 'admin']);
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->s->vendor->update(['global_status' => VendorGlobalStatus::Active]);
        VendorBankAccount::create([
            'vendor_id' => $this->s->vendor->id, 'account_holder_name' => 'Test Vendor', 'bank_name' => 'Test Bank',
            'iban' => 'AE070331234567890123456', 'account_number_encrypted' => 'enc', 'currency' => $this->s->country->currency_code,
            'is_primary' => true, 'verification_status' => 'verified',
        ]);

        // The scenario marketer is an affiliate by type only: give it the affiliate job too.
        $job = MarketerJob::firstOrCreate(['key' => 'affiliate'], ['name_ar' => 'affiliate', 'name_en' => 'affiliate', 'is_active' => true, 'sort_order' => 1]);
        MarketerMarketerJob::create(['marketer_id' => $this->s->marketer->id, 'marketer_job_id' => $job->id]);
        $this->s->marketer->update(['onboarding_completed_at' => now()]);

        // UAE example: fixed 1.9 + 0 %, variable 0 + 10 % (the scenario campaign is on the FBP listing).
        MarketingFeeCountrySetting::updateOrCreate(['country_id' => $this->s->country->id], ['fixed_amount' => 1.9, 'fixed_percentage' => 0]);
        $categoryId = $this->s->vendorListingFbp->productVariant->product->category_id;
        MarketingFeeCategorySetting::updateOrCreate(
            ['country_id' => $this->s->country->id, 'category_id' => $categoryId, 'fulfillment_type' => MarketingFeeType::Fbp->value],
            ['fixed_amount' => 0, 'percentage' => 10]
        );
        $this->campaign = $this->s->marketerCampaign;
    }

    private function vendorWallet(): Wallet
    {
        return app(WalletService::class)->getOrCreateWallet('vendor', $this->s->vendor->id, 'AED');
    }

    private function topUp(float $amount): void
    {
        app(WalletService::class)->credit($this->vendorWallet(), $amount, 'test', null, 'topup');
    }

    /** Places an order of the affiliate's marketer listing, delivers it and runs the approval job. */
    private function approvedConversion(float $commission): MarketerCampaignConversion
    {
        $conversion = $this->deliveredConversion($commission);
        app()->call([new ApproveMarketerConversionsJob, 'handle']);

        return $conversion->fresh();
    }

    /** Same, but stops before the approval job (the conversion stays pending). */
    private function deliveredConversion(float $commission): MarketerCampaignConversion
    {
        $s = $this->s;
        $this->actingAs($s->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $s->marketerListing->id, 'quantity' => 1,
            'unit_price' => (int) $s->marketerListing->price, 'added_at' => now(),
        ]);
        $res = $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(201);
        $order = Order::where('order_number', $res->json('data.order.order_number') ?? $res->json('data.order_number'))->firstOrFail();

        $machine = app(OrderStateMachine::class);
        foreach ($order->subOrders()->get() as $so) {
            foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
                $machine->transition($so, $step, CancelActor::System);
            }
        }
        $item = $order->items()->get()->firstWhere('marketer_listing_id', $s->marketerListing->id);
        $item->update(['return_eligible_until' => now()->subDay()->toDateString()]);
        $conversion = MarketerCampaignConversion::where('order_item_id', $item->id)->firstOrFail();
        $conversion->update(['commission_amount' => $commission, 'flash_sale_bonus_amount' => null]);

        return $conversion->fresh();
    }

    private function accruals()
    {
        return MarketerCampaignFeeAccrual::where('campaign_id', $this->campaign->id)->orderBy('created_at')->orderBy('id')->get();
    }

    private function walletDebits()
    {
        return WalletTransaction::where('wallet_id', $this->vendorWallet()->id)
            ->where('source_type', 'marketer_campaign_affiliate_marketing_fee')->get();
    }

    public function test_section_six_numbers_running_totals_and_fixed_accrued_once(): void
    {
        $this->approvedConversion(20.00);
        $this->approvedConversion(10.00);

        [$first, $second] = $this->accruals()->all();
        $this->assertEqualsWithDelta(1.90, (float) $first->marketing_fixed_delta, 0.0001);
        $this->assertEqualsWithDelta(2.00, (float) $first->marketing_variable_delta, 0.0001);
        $this->assertEqualsWithDelta(3.90, (float) $first->marketing_total_delta, 0.0001);
        $this->assertEqualsWithDelta(3.90, (float) $first->running_marketing_total, 0.0001);
        $this->assertEqualsWithDelta(20.00, (float) $first->running_base, 0.0001);
        $this->assertSame(MarketerCampaignFeeAccrual::STATUS_ACCRUED, $first->status);
        $this->assertGreaterThan(0, $first->quantity);
        $this->assertGreaterThan(0, (float) $first->line_total);

        $this->assertEqualsWithDelta(0.0, (float) $second->marketing_fixed_delta, 0.0001);
        $this->assertEqualsWithDelta(1.00, (float) $second->marketing_total_delta, 0.0001);
        $this->assertEqualsWithDelta(4.90, (float) $second->running_marketing_total, 0.0001);
        $this->assertEqualsWithDelta(30.00, (float) $second->running_base, 0.0001);

        $campaign = $this->campaign->fresh();
        $this->assertEqualsWithDelta(4.90, (float) $campaign->affiliate_fee_accrued, 0.0001);
        $this->assertEqualsWithDelta(1.90, (float) $campaign->marketing_fee_fixed_accrued, 0.0001);
        $this->assertNull($campaign->affiliate_fee_settled_at);
    }

    public function test_accrual_is_idempotent_per_conversion(): void
    {
        $conversion = $this->approvedConversion(20.00);
        $service = app(AffiliateFeeAccrualService::class);

        $service->accrue($conversion->fresh());
        $service->accrue($conversion->fresh());
        app()->call([new ApproveMarketerConversionsJob, 'handle']);

        $this->assertCount(1, $this->accruals());
        $this->assertEqualsWithDelta(3.90, (float) $this->campaign->fresh()->affiliate_fee_accrued, 0.0001);
    }

    public function test_influencer_conversions_and_admin_campaigns_never_accrue(): void
    {
        $conversion = $this->approvedConversion(20.00);
        $influencer = F::influencer($this->s);
        $invitation = MarketerCampaignInvitation::create([
            'campaign_id' => $this->campaign->id, 'marketer_id' => $influencer->id, 'status' => 'accepted',
            'responded_at' => now(), 'referral_code' => 'REF-'.Str::upper(Str::random(8)),
        ]);
        $copy = $conversion->replicate();
        $copy->invitation_id = $invitation->id;
        $copy->order_item_id = null;
        $copy->save();
        $copy->update(['status' => 'approved']);

        $this->assertNull(app(AffiliateFeeAccrualService::class)->accrue($copy->fresh()));
        $this->assertCount(1, $this->accruals());
        $this->assertSame(0, MarketerCampaignFeeAccrual::where('conversion_id', $copy->id)->count());
    }

    public function test_reversal_writes_a_negative_row_keeps_the_flat_amount_and_is_idempotent(): void
    {
        $first = $this->approvedConversion(20.00);
        $second = $this->approvedConversion(10.00);
        $reversal = app(MarketerConversionReversalService::class);

        $reversal->reverseOne($second->fresh());
        $reversal->reverseOne($second->fresh());
        $rows = $this->accruals();
        $this->assertCount(3, $rows);
        $row = $rows->last();
        $this->assertSame(MarketerCampaignFeeAccrual::ENTRY_REVERSAL, $row->entry_type);
        $this->assertSame(MarketerCampaignFeeAccrual::STATUS_REVERSED, $row->status);
        $this->assertEqualsWithDelta(-1.00, (float) $row->marketing_total_delta, 0.0001);
        $this->assertEqualsWithDelta(3.90, (float) $row->running_marketing_total, 0.0001);
        $this->assertEqualsWithDelta(20.00, (float) $row->running_base, 0.0001);

        // The first order's refund gives back only its 10 % part: the 1.90 flat amount stays accrued.
        $reversal->reverseOne($first->fresh());
        $this->assertEqualsWithDelta(1.90, (float) $this->campaign->fresh()->affiliate_fee_accrued, 0.0001);
        $this->assertEqualsWithDelta(1.90, (float) $this->campaign->fresh()->marketing_fee_fixed_accrued, 0.0001);
    }

    public function test_finishing_the_campaign_debits_the_wallet_once_and_marks_rows_settled(): void
    {
        $this->approvedConversion(20.00);
        $this->approvedConversion(10.00);
        $this->topUp(50);

        app(MarketerCampaignService::class)->markCampaignDone($this->campaign->fresh());
        // A second trigger (cancel path, hourly job, admin button) never charges again.
        app(AffiliateFeeSettlementService::class)->settleIfClosed($this->campaign->fresh(), 'again');
        app(AffiliateFeeSettlementService::class)->settle($this->campaign->fresh());
        (new AffiliateFeeSettlementJob)->handle(app(AffiliateFeeSettlementService::class));

        $debits = $this->walletDebits();
        $this->assertCount(1, $debits);
        $this->assertEqualsWithDelta(4.90, (float) $debits->first()->amount, 0.0001);
        $this->assertSame($this->campaign->id, $debits->first()->source_id);
        $this->assertEqualsWithDelta(45.10, (float) $this->vendorWallet()->fresh()->balance, 0.0001);

        $settlement = MarketerCampaignFeeSettlement::where('campaign_id', $this->campaign->id)->sole();
        $this->assertSame('paid', $settlement->status);
        $this->assertSame('wallet', $settlement->paid_via);
        $this->assertSame(['settled', 'settled'], $this->accruals()->pluck('status')->all());
        $this->assertNotNull($this->campaign->fresh()->affiliate_fee_settled_at);
    }

    public function test_late_approval_after_finish_settles_incrementally(): void
    {
        $this->approvedConversion(20.00);
        $this->deliveredConversion(10.00); // sold before the campaign finished, approved after
        $this->topUp(50);
        app(MarketerCampaignService::class)->markCampaignDone($this->campaign->fresh());
        $this->assertCount(1, $this->walletDebits());

        app()->call([new ApproveMarketerConversionsJob, 'handle']);

        $debits = $this->walletDebits();
        $this->assertCount(2, $debits);
        $this->assertEqualsWithDelta(1.00, (float) $debits->sortBy('created_at')->last()->amount, 0.0001);
        $this->assertSame(2, MarketerCampaignFeeSettlement::where('campaign_id', $this->campaign->id)->count());
        $this->assertEqualsWithDelta(45.10, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
    }

    public function test_a_refund_after_settlement_stays_unsettled_and_never_creates_a_negative_charge(): void
    {
        $conversion = $this->approvedConversion(20.00);
        $this->topUp(50);
        app(MarketerCampaignService::class)->cancelCampaign($this->campaign->fresh());
        $this->assertCount(1, $this->walletDebits());

        app(MarketerConversionReversalService::class)->reverseOne($conversion->fresh());
        app(AffiliateFeeSettlementService::class)->settleIfClosed($this->campaign->fresh());

        $this->assertCount(1, $this->walletDebits());
        $this->assertNull($this->accruals()->last()->settlement_id);
    }

    public function test_budget_exhaustion_settles_what_was_accrued(): void
    {
        $this->approvedConversion(20.00);
        $this->topUp(50);
        $spent = (float) $this->campaign->fresh()->commission_budget_spent;
        $this->campaign->update(['max_commission_budget' => $spent + 0.5]); // the next sale exhausts it

        $this->placeOnlyOrder();

        $this->assertSame('paused', $this->campaign->fresh()->status);
        $debits = $this->walletDebits();
        $this->assertCount(1, $debits);
        $this->assertEqualsWithDelta(3.90, (float) $debits->first()->amount, 0.0001);
    }

    public function test_approval_on_a_budget_exhausted_campaign_settles_on_arrival(): void
    {
        $this->campaign->update(['max_commission_budget' => 0.5]);
        $this->topUp(50);

        $this->approvedConversion(20.00); // order placed -> paused; approval -> accrual -> settled at once

        $this->assertSame('paused', $this->campaign->fresh()->status);
        $this->assertCount(1, $this->walletDebits());
        $this->assertEqualsWithDelta(3.90, (float) $this->walletDebits()->first()->amount, 0.0001);
    }

    public function test_hourly_job_settles_closed_campaigns_and_leaves_open_ones(): void
    {
        $this->approvedConversion(20.00);
        $this->topUp(50);

        (new AffiliateFeeSettlementJob)->handle(app(AffiliateFeeSettlementService::class));
        $this->assertCount(0, $this->walletDebits()); // campaign still running

        $this->campaign->update(['status' => 'done']); // closed without going through the service
        (new AffiliateFeeSettlementJob)->handle(app(AffiliateFeeSettlementService::class));
        (new AffiliateFeeSettlementJob)->handle(app(AffiliateFeeSettlementService::class));

        $this->assertCount(1, $this->walletDebits());
    }

    public function test_short_wallet_leaves_a_pending_charge_that_the_weekly_statement_deducts(): void
    {
        $subOrder = $this->eligibleSale();
        $this->approvedConversion(20.00);
        $this->approvedConversion(10.00);
        // wallet stays empty
        app(MarketerCampaignService::class)->markCampaignDone($this->campaign->fresh());

        $settlement = MarketerCampaignFeeSettlement::where('campaign_id', $this->campaign->id)->sole();
        $this->assertSame('pending', $settlement->status);
        $this->assertCount(0, $this->walletDebits());

        (new GenerateVendorPayoutsJob(now()->subDays(7), now()))->handle(app(PayoutCalculationService::class));

        $line = PayoutItem::where('affiliate_fee_settlement_id', $settlement->id)->sole();
        $this->assertSame('affiliate_marketing_fee', $line->item_type);
        $this->assertEqualsWithDelta(-4.90, (float) $line->net, 0.0001);
        $this->assertEqualsWithDelta(4.90, (float) $line->marketing_fee_amount, 0.0001);
        $this->assertEqualsWithDelta(4.90, abs((float) $line->net), 0.0001);
        $settlement->refresh();
        $this->assertSame('paid', $settlement->status);
        $this->assertSame('statement', $settlement->paid_via);
        $this->assertEqualsWithDelta(-4.90, (float) $line->payout->other_adjustments, 0.0001);

        // Neither a second statement run nor the wallet retry charges it again.
        (new GenerateVendorPayoutsJob(now()->subDays(7), now()))->handle(app(PayoutCalculationService::class));
        $this->topUp(50);
        (new AffiliateFeeSettlementJob)->handle(app(AffiliateFeeSettlementService::class));
        $this->assertSame(1, PayoutItem::where('affiliate_fee_settlement_id', $settlement->id)->count());
        $this->assertCount(0, $this->walletDebits());

        $feed = app(TransactionFeedService::class)->getFeed($this->s->vendor, 'affiliate_marketing_fee', null, null, 1, 25);
        $row = collect($feed['items'])->firstWhere('type', 'affiliate_marketing_fee');
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(-4.90, (float) $row['net'], 0.0001);
        $this->assertSame($subOrder->vendor_id, $this->s->vendor->id);
    }

    public function test_a_pending_charge_is_collected_from_the_wallet_by_the_hourly_job_when_funded(): void
    {
        $this->approvedConversion(20.00);
        app(MarketerCampaignService::class)->markCampaignDone($this->campaign->fresh());
        $this->assertSame('pending', MarketerCampaignFeeSettlement::sole()->status);

        $this->topUp(10);
        (new AffiliateFeeSettlementJob)->handle(app(AffiliateFeeSettlementService::class));

        $this->assertSame('paid', MarketerCampaignFeeSettlement::sole()->status);
        $this->assertEqualsWithDelta(6.10, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
    }

    public function test_history_is_visible_in_the_vendor_admin_and_marketer_pages(): void
    {
        $this->approvedConversion(20.00);
        $this->approvedConversion(10.00);
        $this->topUp(50);
        app(MarketerCampaignService::class)->markCampaignDone($this->campaign->fresh());
        $order = Order::latest('created_at')->first();

        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create', 'marketer_campaigns.edit', 'marketer_campaigns.delete', 'marketer_campaigns.cancel'] as $name) {
            $vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }
        $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.show', $this->campaign))
            ->assertOk()
            ->assertSee('data-testid="affiliate-fee-history"', false)
            ->assertSee(__('marketing_fees.affiliate.history_title'))
            ->assertSee('3.90')
            ->assertSee('4.90')
            ->assertSee(__('marketing_fees.affiliate.status_settled'))
            ->assertSee($order->order_number)
            ->assertDontSee('affiliate-settle-now', false);

        $perms = ['marketer_campaigns.view', 'marketer_campaigns.approve', 'vendors.assigned_only'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($perms);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $this->campaign))
            ->assertOk()
            ->assertSee('data-testid="affiliate-fee-history"', false)
            ->assertSee('4.90')
            ->assertSee('affiliate-settle-now', false);

        $login = F::marketerLogin($this->s->marketer);
        $this->actingAs($login, 'marketer')
            ->get(route('marketer.campaigns.show', $this->s->marketerCampaignInvitation->id))
            ->assertOk()
            ->assertSee('data-audience="marketer"', false)
            ->assertSee('4.90')
            ->assertDontSee('affiliate-settled-state', false)
            ->assertDontSee('affiliate-pending-statement', false);

        // Another marketer cannot open it.
        $other = F::marketerLogin(F::affiliate($this->s));
        $this->actingAs($other, 'marketer')
            ->get(route('marketer.campaigns.show', $this->s->marketerCampaignInvitation->id))
            ->assertNotFound();
    }

    public function test_admin_settle_now_needs_the_approve_permission_and_is_idempotent(): void
    {
        $this->approvedConversion(20.00);
        $this->topUp(50);
        foreach (['marketer_campaigns.view', 'marketer_campaigns.approve', 'vendors.assigned_only'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $viewer = Admin::factory()->create();
        $viewer->givePermissionTo(['marketer_campaigns.view']);
        $approver = Admin::factory()->create();
        $approver->givePermissionTo(['marketer_campaigns.view', 'marketer_campaigns.approve']);

        $this->actingAs($viewer, 'admin')
            ->post(route('admin.marketer-campaigns.settle-affiliate-fees', $this->campaign))
            ->assertForbidden();
        $this->assertCount(0, $this->walletDebits());

        $this->actingAs($approver, 'admin')
            ->post(route('admin.marketer-campaigns.settle-affiliate-fees', $this->campaign))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->actingAs($approver, 'admin')
            ->post(route('admin.marketer-campaigns.settle-affiliate-fees', $this->campaign))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertCount(1, $this->walletDebits());
    }

    private function placeOnlyOrder(): void
    {
        $s = $this->s;
        $this->actingAs($s->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $s->marketerListing->id, 'quantity' => 1,
            'unit_price' => (int) $s->marketerListing->price, 'added_at' => now(),
        ]);
        $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(201);
    }

    private function eligibleSale()
    {
        Queue::fake();
        $this->actingAs($this->s->customer, 'customer');
        $cart = app(CartService::class)
            ->getOrCreateCart($this->s->customer, $this->s->country->id, $this->s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'vendor_listing_id' => $this->s->vendorListingFbp->id, 'quantity' => 2,
            'unit_price' => (int) $this->s->vendorListingFbp->getRawOriginal('price'), 'added_at' => now(),
        ]);
        $response = $this->postJson("/api/customer/v1/{$this->s->country->site_code}/checkout/place-order", [
            'address_id' => $this->s->customerAddress->id,
            'country_payment_gateway_id' => $this->s->countryPaymentGateways['wallet']->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $response->assertStatus(201);
        $order = Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();
        $subOrder = $order->subOrders()->where('vendor_id', $this->s->vendor->id)->firstOrFail();

        $machine = app(OrderStateMachine::class);
        foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
            $machine->transition($subOrder, $step, CancelActor::System);
        }
        $subOrder->refresh()->items()->update(['return_eligible_until' => now()->subDay()->toDateString()]);

        return $subOrder->refresh();
    }
}
