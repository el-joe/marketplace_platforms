<?php

namespace Tests\Support;

use App\Enums\CancelActor;
use App\Enums\MarketingFeeType;
use App\Enums\VendorGlobalStatus;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Models\Order;
use App\Models\VendorBankAccount;
use App\Models\Wallet;
use App\Services\AffiliateFeeSettlementService;
use App\Services\Customer\CartService;
use App\Services\MarketerCampaignService;
use App\Services\MarketerFeePayoutService;
use App\Services\MarketerInvitationRefundService;
use App\Services\MarketingFeeReportService;
use App\Services\OrderStateMachine;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\TestCase;

/**
 * P8 fixtures: one UAE/AED vendor with
 *  - FBN influencer campaign (plan section 6): 2 influencers, fees 18.00, VAT 0.90, marketing 3.79 (collected 22.69),
 *    fee-share payouts 3.60 (20 % of 9.00 each), platform margin 18.00 - 3.60 + 3.79 = 18.19;
 *  - FBM (cross_dock) campaign: one paid invitation (fee 5.00, VAT 0.25, marketing 1.00, share 10 %) and one refunded
 *    invitation (fee 4.00, VAT 0.20, marketing 0.50, share 10 %, credit reversed);
 *  - FBP affiliate campaign (section 6): commissions 20.00 + 10.00, marketing fees 3.90 + 1.00 = 4.90 accrued and settled;
 *  - a SAR/Saudi FBN campaign (fee 10.00, VAT 1.50, marketing 2.00, share 30 %) to prove currencies are never blended.
 */
abstract class MarketingFeeReportCase extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceScenario $s;

    protected MarketerCampaign $influencerCampaign;

    /** @var array<int, Marketer> */
    protected array $influencers = [];

    protected MarketerCampaign $affiliateCampaign;

    protected MarketerCampaign $fbmCampaign;

    protected Marketer $fbmMarketer;

    protected MarketerCampaign $sarCampaign;

    protected Country $sarCountry;

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
            'iban' => 'AE070331234567890123456', 'account_number_encrypted' => 'enc', 'currency' => 'AED',
            'is_primary' => true, 'verification_status' => 'verified',
        ]);
        F::setting('marketer_influencer_fee_payer', 'vendor');

        MarketerInfluencerFeeCountrySetting::updateOrCreate(['country_id' => $this->s->country->id], ['fee_per_influencer' => 9, 'currency' => 'AED', 'updated_by_admin_id' => null]);
        MarketingFeeCountrySetting::updateOrCreate(['country_id' => $this->s->country->id], ['fixed_amount' => 1.9, 'fixed_percentage' => 0]);
        foreach ([MarketingFeeType::Fbn, MarketingFeeType::Fbp] as $type) {
            MarketingFeeCategorySetting::updateOrCreate(
                ['country_id' => $this->s->country->id, 'category_id' => $this->s->category->id, 'fulfillment_type' => $type->value],
                ['fixed_amount' => 0, 'percentage' => 10]
            );
        }
        MarketerCommissionCountrySetting::updateOrCreate(
            ['category_id' => $this->s->category->id, 'country_id' => $this->s->country->id],
            ['currency' => 'AED', 'influencer_fee_share_pct' => 20]
        );

        $affiliateJob = MarketerJob::firstOrCreate(['key' => 'affiliate'], ['name_ar' => 'affiliate', 'name_en' => 'affiliate', 'is_active' => true, 'sort_order' => 1]);
        MarketerMarketerJob::create(['marketer_id' => $this->s->marketer->id, 'marketer_job_id' => $affiliateJob->id]);
        $this->s->marketer->update(['onboarding_completed_at' => now()]);

        $this->topUpVendor(500);
        $this->buildInfluencerSectionSix();
        $this->buildFbmCampaignWithRefund();
        $this->buildSarCampaign();
        $this->buildAffiliateSectionSix();
    }

    protected function wallets(): WalletService
    {
        return app(WalletService::class);
    }

    protected function vendorWallet(): Wallet
    {
        return $this->wallets()->getOrCreateWallet('vendor', $this->s->vendor->id, 'AED');
    }

    protected function topUpVendor(float $amount): void
    {
        $this->wallets()->credit($this->vendorWallet(), $amount, 'test', null, 'topup');
    }

    protected function marketerWallet(Marketer $m, string $currency = 'AED'): Wallet
    {
        return $this->wallets()->getOrCreateWallet('marketer', $m->id, $currency);
    }

    /** Plan section 6, influencer side (FBN listing, 2 influencers, 20 % share). */
    private function buildInfluencerSectionSix(): void
    {
        Queue::fake();
        $this->influencers = [F::influencer($this->s), F::influencer($this->s)];
        $campaign = F::campaign($this->s, $this->influencers);
        app(MarketerCampaignService::class)->approveCampaign($campaign, Admin::factory()->create());
        $this->influencerCampaign = $campaign->fresh();

        foreach ($this->influencers as $m) {
            app(MarketerCampaignService::class)->acceptInvitation($this->influencerCampaign->invitations()->where('marketer_id', $m->id)->sole());
        }
    }

    /** A cross_dock (FBM) campaign: one paid + credited invitation, one paid then refunded (credit reversed). */
    private function buildFbmCampaignWithRefund(): void
    {
        $this->fbmMarketer = F::influencer($this->s);
        $other = F::influencer($this->s);

        $this->fbmCampaign = MarketerCampaign::create([
            'vendor_id' => $this->s->vendor->id, 'owner_type' => 'vendor', 'owner_id' => $this->s->vendor->id,
            'vendor_listing_id' => $this->s->vendorListingFbn->id, 'campaign_category' => 'product',
            'country_id' => $this->s->country->id, 'currency' => 'AED', 'commission_type' => 'fixed',
            'max_commission_budget' => 1000, 'platform_commission_amount' => 0, 'marketer_commission_amount' => 0,
            'status' => 'active', 'fulfillment_type' => MarketingFeeType::Fbm->value,
            'campaign_subtotal' => 9.45, 'marketing_fee_fixed' => 1.0, 'marketing_fee_variable' => 0.5,
            'marketing_fee_total' => 1.5, 'campaign_grand_total' => 10.95, 'title' => 'FBM campaign',
        ]);

        $kept = $this->paidInvitation($this->fbmCampaign, $this->fbmMarketer, 5.00, 0.25, 1.00, 10);
        $refunded = $this->paidInvitation($this->fbmCampaign, $other, 4.00, 0.20, 0.50, 10);
        app(MarketerFeePayoutService::class)->creditForInvitation($kept);
        app(MarketerFeePayoutService::class)->creditForInvitation($refunded);

        // Only the second invitation is given back (fee + VAT + marketing credited to the vendor, share reversed).
        app(MarketerInvitationRefundService::class)->refundInvitation($refunded->fresh());
    }

    /** SAR campaign (never blended with AED). */
    private function buildSarCampaign(): void
    {
        $this->sarCountry = Country::create([
            'id' => (string) Str::uuid(), 'iso_code_2' => 'SA', 'iso_code_3' => 'SAU', 'name_ar' => 'السعودية',
            'name_en' => 'Saudi Arabia - '.Str::random(6), 'currency_code' => 'SAR', 'vat_rate' => 15.00,
            'is_active' => true, 'is_launched' => true, 'cod_available' => true, 'timezone' => 'Asia/Riyadh',
        ]);
        $marketer = F::influencer($this->s);

        $this->sarCampaign = MarketerCampaign::create([
            'vendor_id' => $this->s->vendor->id, 'owner_type' => 'vendor', 'owner_id' => $this->s->vendor->id,
            'vendor_listing_id' => $this->s->vendorListingFbn->id, 'campaign_category' => 'product',
            'country_id' => $this->sarCountry->id, 'currency' => 'SAR', 'commission_type' => 'fixed',
            'max_commission_budget' => 1000, 'platform_commission_amount' => 0, 'marketer_commission_amount' => 0,
            'status' => 'active', 'fulfillment_type' => MarketingFeeType::Fbn->value,
            'campaign_subtotal' => 11.5, 'marketing_fee_fixed' => 2.0, 'marketing_fee_variable' => 0,
            'marketing_fee_total' => 2.0, 'campaign_grand_total' => 13.5, 'title' => 'SAR campaign',
        ]);

        $inv = $this->paidInvitation($this->sarCampaign, $marketer, 10.00, 1.50, 2.00, 30, 'SAR');
        app(MarketerFeePayoutService::class)->creditForInvitation($inv);
    }

    protected function paidInvitation(MarketerCampaign $campaign, Marketer $marketer, float $fee, float $vat, float $marketing, float $sharePct, string $currency = 'AED'): MarketerCampaignInvitation
    {
        return MarketerCampaignInvitation::create([
            'campaign_id' => $campaign->id, 'marketer_id' => $marketer->id, 'status' => 'accepted', 'responded_at' => now(),
            'referral_code' => 'REF-'.Str::upper(Str::random(8)),
            'platform_fee_amount' => $fee, 'platform_fee_vat_rate' => 5, 'platform_fee_vat_amount' => $vat,
            'platform_fee_marketing_amount' => $marketing, 'platform_fee_currency' => $currency,
            'platform_fee_status' => 'paid', 'platform_fee_paid_via' => 'wallet', 'platform_fee_paid_at' => now(),
            'platform_fee_payer' => 'vendor', 'platform_fee_recorded_at' => now(),
            'marketer_fee_share_pct' => $sharePct, 'marketer_payout_amount' => round($fee * $sharePct / 100, 2),
        ]);
    }

    /** Plan section 6, affiliate side: 20.00 + 10.00 commission -> fees 3.90 + 1.00 = 4.90, settled on finish. */
    private function buildAffiliateSectionSix(): void
    {
        $this->affiliateCampaign = $this->s->marketerCampaign;
        $this->approvedConversion(20.00);
        $this->approvedConversion(10.00);

        app(MarketerCampaignService::class)->markCampaignDone($this->affiliateCampaign->fresh());
        app(AffiliateFeeSettlementService::class)->settleIfClosed($this->affiliateCampaign->fresh());
    }

    private function approvedConversion(float $commission): MarketerCampaignConversion
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
        app()->call([new ApproveMarketerConversionsJob, 'handle']);

        return $conversion->fresh();
    }

    /** @return array<int, array<string, mixed>> platform report rows of this month */
    protected function reportRows(array $filters = []): array
    {
        return app(MarketingFeeReportService::class)
            ->platformReport(now()->startOfMonth(), now(), $filters)['rows'];
    }

    /** @return array<string, mixed>|null */
    protected function row(array $rows, string $type, string $currency, ?string $countryId = null): ?array
    {
        foreach ($rows as $r) {
            if ($r['type'] === $type && $r['currency'] === $currency && ($countryId === null || $r['country_id'] === $countryId)) {
                return $r;
            }
        }

        return null;
    }
}
