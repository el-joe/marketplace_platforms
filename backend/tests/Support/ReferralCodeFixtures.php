<?php

namespace Tests\Support;

use App\Models\CartItem;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Models\Order;
use App\Services\Customer\CartService;
use App\Services\MarketerCampaignService;
use Illuminate\Support\Str;

/** P14 builders: a live campaign with a pending invitation that can be rejected / timed out, and customer orders placed over HTTP. */
class ReferralCodeFixtures
{
    /** Active vendor campaign with ONE pending invitation for $first and $spare available as the replacement. */
    public static function pendingCampaign(MarketplaceScenario $s, Marketer $first, ?Marketer $spare = null): MarketerCampaign
    {
        $campaign = CampaignSampleFixtures::campaign($s, [$first]);
        $campaign->update(['status' => 'active']);
        app(MarketerCampaignService::class)->dispatchInvitation($campaign, $first->id);

        return $campaign->fresh();
    }

    public static function invitationOf(MarketerCampaign $campaign, Marketer $marketer): MarketerCampaignInvitation
    {
        return MarketerCampaignInvitation::where('campaign_id', $campaign->id)->where('marketer_id', $marketer->id)->firstOrFail();
    }

    public static function reject(MarketerCampaignInvitation $invitation): void
    {
        app(MarketerCampaignService::class)->rejectInvitation($invitation, 'busy');
    }

    public static function timeout(MarketerCampaignInvitation $invitation): void
    {
        app(MarketerCampaignService::class)->handleInvitationTimeout($invitation->id);
    }

    /** Customer scans a code (GET /api/r/{code}) under the given tracking session. */
    public static function scan(\Illuminate\Foundation\Testing\TestCase $t, string $code, string $session)
    {
        return $t->getJson('/api/r/'.$code, ['X-Session-Id' => $session]);
    }

    /**
     * Place a COD order over the real checkout endpoint with plain vendor-listing lines (no marketer listing),
     * so only a remembered referral click can attribute it.
     *
     * @param  array<int, \App\Models\VendorListing>  $listings
     */
    public static function placeOrder(\Illuminate\Foundation\Testing\TestCase $t, MarketplaceScenario $s, string $session, array $listings = [], array $marketerListingLines = [], string $gateway = 'cod'): Order
    {
        $listings = $listings ?: [$s->vendorListingFbp];
        $t->actingAs($s->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);

        foreach ($listings as $listing) {
            CartItem::create([
                'cart_id' => $cart->id, 'vendor_listing_id' => $listing->id, 'quantity' => 1,
                'unit_price' => (int) $listing->getRawOriginal('price'), 'added_at' => now(),
            ]);
        }
        foreach ($marketerListingLines as $ml) {
            CartItem::create([
                'cart_id' => $cart->id, 'marketer_listing_id' => $ml->id, 'quantity' => 1,
                'unit_price' => (int) $ml->price, 'added_at' => now(),
            ]);
        }

        $response = $t->withHeaders(['X-Session-Id' => $session])
            ->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
                'address_id' => $s->customerAddress->id,
                'country_payment_gateway_id' => $s->countryPaymentGateways[$gateway]->id,
                'idempotency_key' => (string) Str::uuid(),
            ]);
        $response->assertStatus(201);

        $number = $response->json('data.order.order_number') ?? $response->json('data.order_number');

        return Order::where('order_number', $number)->firstOrFail();
    }
}
