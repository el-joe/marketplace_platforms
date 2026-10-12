<?php

namespace Tests\Support;

use App\Contracts\WhatsAppGateway;
use App\Enums\PromotionCategory;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Models\PromotionCategoryTarget;
use Illuminate\Support\Str;

/** Shared helpers for the P12 promotion quota tests (marketer / admin / service). */
class PromotionQuotaFixtures
{
    public static function fakeGateway(): WhatsAppGateway
    {
        return new class implements WhatsAppGateway
        {
            public array $sent = [];

            public function send(string $to, string $message): bool
            {
                $this->sent[] = compact('to', 'message');

                return true;
            }

            public function lastError(): ?string
            {
                return null;
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };
    }

    public static function marketerIn(MarketplaceScenario $s, PromotionCategory $category, ?int $min = null, int $penalty = 0): Marketer
    {
        $m = CampaignSampleFixtures::influencer($s);
        $m->update(['whatsapp_for_campaigns' => '+97150'.random_int(1000000, 9999999)]);
        $m->marketerProfile()->update(['promotion_category' => $category->value]);
        CampaignSampleFixtures::marketerLogin($m);

        if ($min !== null) {
            PromotionCategoryTarget::updateOrCreate(
                ['promotion_category' => $category->value, 'country_id' => null],
                ['monthly_minimum' => $min, 'penalty_amount' => $penalty]
            );
        }

        return $m->fresh(['marketerProfile']);
    }

    /** An accepted invitation for the marketer on a vendor (cat 1) or admin listing (cat 3/4) campaign. */
    public static function accepted(MarketplaceScenario $s, Marketer $m, string $source = 'vendor', $respondedAt = null): MarketerCampaignInvitation
    {
        $campaign = MarketerCampaign::create([
            'vendor_id' => $s->vendor->id,
            'owner_type' => 'vendor',
            'owner_id' => $s->vendor->id,
            'vendor_listing_id' => $source === 'vendor' ? $s->vendorListingFbp->id : null,
            'admin_listing_id' => $source === 'admin' ? $s->adminListing->id : null,
            'campaign_category' => 'product',
            'country_id' => $s->country->id,
            'currency' => 'AED',
            'commission_type' => 'fixed',
            'max_commission_budget' => 100000,
            'platform_commission_amount' => 0,
            'marketer_commission_amount' => 5000,
            'status' => 'active',
        ]);

        return MarketerCampaignInvitation::create([
            'campaign_id' => $campaign->id,
            'marketer_id' => $m->id,
            'status' => 'accepted',
            'responded_at' => $respondedAt ?? now(),
            'referral_code' => 'REF-'.Str::upper(Str::random(8)),
        ]);
    }
}
