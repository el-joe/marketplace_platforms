<?php

namespace App\Services\Marketer;

use App\Enums\PromotionCategory;
use App\Models\Marketer;
use App\Models\MarketerCampaignInvitation;
use App\Models\PromotionCategoryTarget;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly promotion obligation of a marketer ("2/3" counter, red warning, last-week alert).
 * Progress = invitations the marketer accepted during the month for the products of his category (see doneFor()).
 */
class PromotionQuotaService
{
    /**
     * Admin row for the marketer's category: the marketer's country row, else the global (no country) row.
     */
    public function categoryRow(Marketer $marketer): ?PromotionCategoryTarget
    {
        $category = $marketer->marketerProfile?->promotion_category;

        if (! $category) {
            return null;
        }

        $targets = PromotionCategoryTarget::where('promotion_category', $category->value)
            ->where(fn ($q) => $q->whereNull('country_id')->orWhere('country_id', $marketer->country_id))
            ->get();

        return $targets->firstWhere('country_id', $marketer->country_id) ?? $targets->firstWhere('country_id', null);
    }

    /**
     * Admin-defined monthly minimum: per-marketer override, else the category target for the
     * marketer's country, else the global category target, else the contract default (categories 1/2).
     * NULL = no obligation.
     */
    public function targetFor(Marketer $marketer): ?int
    {
        $profile = $marketer->marketerProfile;
        $category = $profile?->promotion_category;

        if (! $category) {
            return null;
        }

        if ($profile->monthly_promotion_target !== null) {
            return $profile->monthly_promotion_target;
        }

        return $this->categoryRow($marketer)?->monthly_minimum ?? $category->defaultMinimum();
    }

    /** Fine (base currency units) charged when the month closes short; 0 = none configured. */
    public function penaltyFor(Marketer $marketer): int
    {
        return (int) ($this->categoryRow($marketer)?->penalty_amount ?? 0);
    }

    /**
     * Ads the marketer activated in the month, counted per category by what the accepted invitation promotes:
     *  1 VendorRequests          - campaign on a seller's (vendor) listing
     *  2 PeerInfluencerProducts  - campaign on another influencer's own listing (marketer_listing_id, added by P13)
     *  3 AdminIntermediary / 4 Nawy - campaign on an admin listing flagged with that category
     */
    public function doneFor(Marketer $marketer, PromotionCategory $category, CarbonInterface $at): int
    {
        $query = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->whereBetween('responded_at', [$at->copy()->startOfMonth(), $at->copy()->endOfMonth()]);

        return match ($category) {
            PromotionCategory::VendorRequests => $query->whereHas('campaign', fn ($q) => $q->whereNotNull('vendor_listing_id'))->count(),
            PromotionCategory::PeerInfluencerProducts => Schema::hasColumn('marketer_campaigns', 'marketer_listing_id')
                ? $query->whereHas('campaign', fn ($q) => $q->whereNotNull('marketer_listing_id'))->count()
                : 0,
            default => $query->whereHas('campaign.adminListing', fn ($q) => $q->where('promotion_category', $category->value))->count(),
        };
    }

    /**
     * @return array{category: ?PromotionCategory, penalty: int, done: int, target: ?int, remaining: int, behind: bool, last_week: bool, warning: bool, level: string, month: string}
     */
    public function progressFor(Marketer $marketer, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $target = $this->targetFor($marketer);

        $category = $marketer->marketerProfile?->promotion_category;
        $done = $category ? $this->doneFor($marketer, $category, $at) : 0;

        $behind = $target !== null && $done < $target;
        $lastWeek = $at->copy()->endOfMonth()->diffInDays($at, true) < (int) setting('marketer_promotion_reminder_days', 7);

        // Graduated alert: ok (target met) → on_track → watch (amber, behind the month's pro-rata pace after day 10)
        // → critical (red + WhatsApp, last week and still short).
        $expectedByNow = $target === null ? 0 : (int) ceil($target * $at->day / $at->daysInMonth);
        $level = match (true) {
            $target === null || ! $behind => 'ok',
            $lastWeek => 'critical',
            $at->day > 10 && $done < $expectedByNow => 'watch',
            default => 'on_track',
        };

        return [
            'category' => $category,
            'penalty' => $target === null ? 0 : $this->penaltyFor($marketer),
            'done' => $done,
            'target' => $target,
            'remaining' => $target === null ? 0 : max(0, $target - $done),
            'behind' => $behind,
            'last_week' => $lastWeek,
            'warning' => $level === 'critical',
            'level' => $level,
            'month' => $at->format('Y-m'),
        ];
    }
}
