<?php

namespace App\Services\Marketer;

use App\Enums\PromotionCategory;
use App\Models\Marketer;
use App\Models\MarketerCampaignInvitation;
use App\Models\PromotionCategoryTarget;
use Carbon\CarbonInterface;

/**
 * Monthly promotion obligation of a marketer ("2/3" counter, red warning, last-week alert).
 * Progress = invitations the marketer accepted during the month.
 */
class PromotionQuotaService
{
    /**
     * Admin-defined monthly minimum: per-marketer override, else the category target for the
     * marketer's country, else the global (no country) category target. NULL = no obligation.
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

        $targets = PromotionCategoryTarget::where('promotion_category', $category->value)
            ->where(fn ($q) => $q->whereNull('country_id')->orWhere('country_id', $marketer->country_id))
            ->get();

        $target = $targets->firstWhere('country_id', $marketer->country_id) ?? $targets->firstWhere('country_id', null);

        return $target?->monthly_minimum;
    }

    /**
     * @return array{category: ?PromotionCategory, done: int, target: ?int, remaining: int, behind: bool, last_week: bool, warning: bool, level: string, month: string}
     */
    public function progressFor(Marketer $marketer, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $target = $this->targetFor($marketer);

        $done = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->whereBetween('responded_at', [$at->copy()->startOfMonth(), $at->copy()->endOfMonth()])
            ->count();

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
            'category' => $marketer->marketerProfile?->promotion_category,
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
