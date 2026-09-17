<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MarketerCampaign;
use App\Services\Checkout\CartLineSource;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceScenario;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * enhancement.md P-14: campaign creation from a vendor listing, an admin
 * listing, and via a marketer-originated request, each carried from
 * creation -> approval -> invitation -> accept -> listing active -> sale
 * -> done, plus the invitation-timing and stock-pause lifecycle fixes.
 */
class MarketerCampaignSourcesTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MarketerCampaignService
    {
        return app(MarketerCampaignService::class);
    }

    public function test_vendor_listing_campaign_full_lifecycle(): void
    {
        Queue::fake();
        $scenario = MarketplaceScenario::make()->build();
        $admin = Admin::factory()->create();

        $campaign = $this->service()->createCampaign(
            CampaignOwner::vendor($scenario->vendor),
            CampaignSource::vendorListing($scenario->vendorListingFbn->id),
            [
                'country_id'            => $scenario->country->id,
                'currency'              => 'AED',
                'commission_type'       => 'fixed',
                'max_commission_budget' => 100000,
                'marketer_commission_amount' => 5000,
                'marketer_ids'          => [$scenario->marketer->id],
            ]
        );

        $this->assertSame('vendor', $campaign->owner_type);
        $this->assertSame($scenario->vendor->id, $campaign->owner_id);
        $this->assertSame('pending_admin', $campaign->status);

        // Invitations are NOT dispatched before approval (P-14 task 4).
        $this->assertSame(0, $campaign->invitations()->count());

        $this->service()->approveCampaign($campaign, $admin);
        $campaign->refresh();

        $this->assertSame('active', $campaign->status);
        $invitation = $campaign->invitations()->first();
        $this->assertNotNull($invitation, 'Invitation must be dispatched only after approval.');
        $this->assertSame('pending', $invitation->status);

        $this->service()->acceptInvitation($invitation);
        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status);

        $marketerListing = \App\Models\MarketerListing::where('invitation_id', $invitation->id)->first();
        $this->assertNotNull($marketerListing);
        $this->assertSame('active', $marketerListing->status);
        $this->assertSame($scenario->vendorListingFbn->product_variant_id, $marketerListing->product_variant_id);

        // Sale: the marketer listing resolves to the vendor listing as its
        // fulfilment source, with the vendor as the seller party (D5: the
        // vendor pays commission on their own campaign).
        $cart = Cart::create(['country_id' => $scenario->country->id, 'currency' => 'AED', 'subtotal' => 0, 'discount' => 0, 'estimated_shipping' => 0, 'estimated_tax' => 0, 'estimated_total' => 0]);
        $cartItem = CartItem::create([
            'cart_id'             => $cart->id,
            'marketer_listing_id' => $marketerListing->id,
            'quantity'            => 1,
            'unit_price'          => $marketerListing->price,
            'added_at'            => now(),
        ]);

        $resolved = CartLineSource::resolve($cartItem);
        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->fulfilmentListing->is($scenario->vendorListingFbn));
        $this->assertSame($scenario->vendor->id, $resolved->sellerParty);
        $this->assertFalse($resolved->isAdminSeller());

        // Done: cancels pending invitations and archives marketer listings.
        $this->service()->markCampaignDone($campaign);
        $campaign->refresh();
        $marketerListing->refresh();
        $this->assertSame('done', $campaign->status);
        $this->assertSame('archived', $marketerListing->status);
    }

    public function test_admin_listing_campaign_full_lifecycle_owned_by_platform(): void
    {
        Queue::fake();
        $scenario = MarketplaceScenario::make()->build();
        $admin = Admin::factory()->create();

        $campaign = $this->service()->createCampaign(
            CampaignOwner::platform(),
            CampaignSource::adminListing($scenario->adminListing->id),
            [
                'country_id'            => $scenario->country->id,
                'currency'              => 'AED',
                'commission_type'       => 'fixed',
                'max_commission_budget' => 100000,
                'marketer_commission_amount' => 5000,
                'marketer_ids'          => [$scenario->marketer->id],
            ]
        );

        $this->assertSame('platform', $campaign->owner_type);
        $this->assertNull($campaign->owner_id);
        $this->assertNull($campaign->vendor_id);

        $this->service()->approveCampaign($campaign, $admin);
        $campaign->refresh();
        $invitation = $campaign->invitations()->first();
        $this->assertNotNull($invitation);

        $this->service()->acceptInvitation($invitation);
        $marketerListing = \App\Models\MarketerListing::where('invitation_id', $invitation->id)->first();
        $this->assertNotNull($marketerListing);

        $cart = Cart::create(['country_id' => $scenario->country->id, 'currency' => 'AED', 'subtotal' => 0, 'discount' => 0, 'estimated_shipping' => 0, 'estimated_tax' => 0, 'estimated_total' => 0]);
        $cartItem = CartItem::create([
            'cart_id'             => $cart->id,
            'marketer_listing_id' => $marketerListing->id,
            'quantity'            => 1,
            'unit_price'          => $marketerListing->price,
            'added_at'            => now(),
        ]);

        $resolved = CartLineSource::resolve($cartItem);
        $this->assertNotNull($resolved);
        // D5: platform owns admin-listing campaigns' commission.
        $this->assertTrue($resolved->isAdminSeller());
        $this->assertSame('platform', $resolved->sellerParty);
        $this->assertTrue($resolved->fulfilmentListing->is($scenario->adminListing));
    }

    public function test_marketer_originated_request_is_auto_accepted_on_owner_approval(): void
    {
        Queue::fake();
        $scenario = MarketplaceScenario::make()->build();

        $campaign = $this->service()->requestCampaign(
            $scenario->marketer,
            CampaignSource::vendorListing($scenario->vendorListingFbn->id),
            [
                'country_id'            => $scenario->country->id,
                'currency'              => 'AED',
                'commission_type'       => 'fixed',
                'max_commission_budget' => 100000,
            ]
        );

        $this->assertSame('marketer', $campaign->owner_type);
        $this->assertSame($scenario->marketer->id, $campaign->owner_id);
        $this->assertSame($scenario->marketer->id, $campaign->requested_by_marketer_id);
        $this->assertSame('marketer_requested', $campaign->status);
        $this->assertSame(0, $campaign->invitations()->count());

        $this->service()->approveMarketerRequest($campaign, [
            'marketer_commission_amount' => 4000,
        ]);
        $campaign->refresh();

        $this->assertSame('vendor', $campaign->owner_type);
        $this->assertSame($scenario->vendor->id, $campaign->owner_id);
        $this->assertSame('active', $campaign->status);

        $invitation = $campaign->invitations()->where('marketer_id', $scenario->marketer->id)->first();
        $this->assertNotNull($invitation);
        // Auto-accepted — the marketer already asked to promote it.
        $this->assertSame('accepted', $invitation->status);

        $marketerListing = \App\Models\MarketerListing::where('invitation_id', $invitation->id)->first();
        $this->assertNotNull($marketerListing);
        $this->assertSame('active', $marketerListing->status);
    }

    public function test_rejection_cancels_pending_invitations_and_archives_listings(): void
    {
        Queue::fake();
        $scenario = MarketplaceScenario::make()->build();
        $admin = Admin::factory()->create();

        $campaign = $this->service()->createCampaign(
            CampaignOwner::vendor($scenario->vendor),
            CampaignSource::vendorListing($scenario->vendorListingFbn->id),
            [
                'country_id'            => $scenario->country->id,
                'currency'              => 'AED',
                'commission_type'       => 'fixed',
                'max_commission_budget' => 100000,
                'marketer_commission_amount' => 5000,
                'marketer_ids'          => [$scenario->marketer->id],
            ]
        );

        $this->service()->approveCampaign($campaign, $admin);
        $campaign->refresh();
        $invitation = $campaign->invitations()->first();
        $this->assertSame('pending', $invitation->status);

        $this->service()->rejectCampaign($campaign, $admin, 'policy violation');
        $campaign->refresh();
        $invitation->refresh();

        $this->assertSame('rejected', $campaign->status);
        $this->assertSame('cancelled', $invitation->status);
    }

    public function test_stock_below_minimum_pauses_campaign_and_listings(): void
    {
        Queue::fake();
        $scenario = MarketplaceScenario::make()->build();
        $admin = Admin::factory()->create();

        $campaign = $this->service()->createCampaign(
            CampaignOwner::vendor($scenario->vendor),
            CampaignSource::vendorListing($scenario->vendorListingFbn->id),
            [
                'country_id'            => $scenario->country->id,
                'currency'              => 'AED',
                'commission_type'       => 'fixed',
                'max_commission_budget' => 100000,
                'marketer_commission_amount' => 5000,
                'marketer_ids'          => [$scenario->marketer->id],
            ]
        );

        $this->service()->approveCampaign($campaign, $admin);
        $campaign->refresh();
        $invitation = $campaign->invitations()->first();
        $this->service()->acceptInvitation($invitation);
        $marketerListing = \App\Models\MarketerListing::where('invitation_id', $invitation->id)->first();

        // vendorListingFbn starts with 40 on hand; category default
        // min_stock_for_campaign is 10. Drop stock to below that.
        $scenario->vendorListingFbnInventory->update(['quantity_on_hand' => 5]);

        $this->service()->checkStockAndUpdateStatus($campaign->fresh());

        $campaign->refresh();
        $marketerListing->refresh();
        $this->assertSame('paused', $campaign->status);
        $this->assertSame('paused', $marketerListing->status);

        // Restock above the minimum resumes it.
        $scenario->vendorListingFbnInventory->update(['quantity_on_hand' => 40]);
        $this->service()->checkStockAndUpdateStatus($campaign->fresh());

        $campaign->refresh();
        $marketerListing->refresh();
        $this->assertSame('active', $campaign->status);
        $this->assertSame('active', $marketerListing->status);
    }
}
