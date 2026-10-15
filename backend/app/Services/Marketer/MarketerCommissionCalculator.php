<?php

namespace App\Services\Marketer;

use App\Enums\CommissionVertical;
use App\Enums\MarketerType;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCommissionRule;

/**
 * The single place where a marketer's commission for a campaign is computed (v3):
 *
 *   influencer : ONE fixed amount per campaign (never per sale). Source order: the amount snapshotted on the
 *                invitation when the campaign was created -> the influencer's profile fixed rule (category, then
 *                ancestors, then the vertical rule) -> the platform default (Marketer Settings, country x vertical).
 *   affiliate  : percentage x basis.
 *   broker     : fixed + percentage x basis; either part may be empty. The fixed part is per sold item.
 *
 * basis is passed in by the vertical, already in campaign currency:
 *   product    = item price excluding VAT, after discounts, for the whole line (unit price x quantity);
 *   classified = the sold price;
 *   travel     = the booking total.
 *
 * The commission fee (percentage x gross + fixed, net never below 0) is applied by CommissionFeeService.
 * Nothing here writes to the database.
 */
class MarketerCommissionCalculator
{
    public const SOURCE_SNAPSHOT = 'invitation_snapshot';

    public const SOURCE_PROFILE = 'profile_rule';

    public const SOURCE_DEFAULT = 'default';

    public const SOURCE_NONE = 'none';

    public function __construct(
        private readonly MarketerDefaultCommissionResolver $defaults,
        private readonly CommissionFeeService $fees,
    ) {}

    /**
     * @param  int|float|string  $basis  sale amount in campaign currency (ignored for influencers)
     * @param  int  $quantity  sold items; multiplies a broker's fixed part (1 for classified and travel)
     * @param  MarketerType|null  $type  force a type; by default the invitation / marketer decide (effectiveType())
     * @param  bool  $perSale  peer promotion: an influencer is paid per sale by his profile rule (fixed + percentage)
     *                         instead of the once-per-campaign fixed amount
     */
    public function compute(
        Marketer $marketer,
        MarketerCampaign $campaign,
        int|float|string $basis = 0,
        int $quantity = 1,
        ?MarketerCampaignInvitation $invitation = null,
        ?string $categoryId = null,
        ?MarketerType $type = null,
        bool $perSale = false,
    ): CommissionResult {
        $vertical = self::verticalOf($campaign);
        $categoryId ??= $this->categoryIdFor($campaign);
        $type ??= $this->effectiveType($marketer, $invitation);
        $quantity = max(1, $quantity);
        $basis = max(0.0, round((float) $basis, 2));

        $rule = $this->profileRule($marketer, $vertical, $categoryId);

        [$gross, $source, $mode, $rate, $fixed] = match ($type) {
            MarketerType::Influencer => $perSale
                ? $this->fixedAndPercentage($rule, $basis, $quantity)
                : $this->influencer($campaign, $invitation, $rule, $vertical, $categoryId),
            MarketerType::Affiliate => $this->percentageOnly($rule, $basis),
            MarketerType::Broker => $this->fixedAndPercentage($rule, $basis, $quantity),
        };

        $detail = $this->fees->resolveDetailed($marketer, $vertical, $categoryId, $campaign->country_id);
        $split = CommissionFeeService::split($gross, $detail['percentage'], $detail['fixed']);

        return new CommissionResult(
            gross: $split['gross'],
            fee: $split['fee'],
            net: $split['net'],
            basis: $basis,
            ruleSource: $source,
            marketerType: $type,
            vertical: $vertical,
            mode: $mode,
            rate: $rate,
            fixedAmount: $fixed,
            quantity: $quantity,
            feePercentage: $detail['percentage'],
            feeFixed: $detail['fixed'],
            perCampaign: $type === MarketerType::Influencer && ! $perSale,
        );
    }

    /**
     * The type a marketer acts as on a campaign. An invitation carrying an influencer fixed-amount snapshot is an
     * influencer slot; otherwise a marketer who is both broker and affiliate acts as broker, an influencer-only
     * marketer as influencer, and a marketer without a v3 type (legacy job) as affiliate.
     */
    public function effectiveType(Marketer $marketer, ?MarketerCampaignInvitation $invitation = null): MarketerType
    {
        if ($invitation && ($invitation->influencer_fixed_status ?? MarketerCampaignInvitation::FIXED_NONE) !== MarketerCampaignInvitation::FIXED_NONE) {
            return MarketerType::Influencer;
        }

        $held = MarketerType::heldBy($marketer);
        foreach ([MarketerType::Broker, MarketerType::Affiliate] as $candidate) {
            if (in_array($candidate, $held, true)) {
                return $candidate;
            }
        }

        return in_array(MarketerType::Influencer, $held, true) ? MarketerType::Influencer : MarketerType::Affiliate;
    }

    public static function verticalOf(MarketerCampaign $campaign): CommissionVertical
    {
        return CommissionVertical::tryFrom((string) $campaign->campaign_category) ?? CommissionVertical::Product;
    }

    /**
     * The category the commission rules and fees are looked up by: the product category, or the classified category.
     * Travel campaigns have no category here (their rules are vertical-wide unless the caller passes one).
     */
    public function categoryIdFor(MarketerCampaign $campaign): ?string
    {
        return match (self::verticalOf($campaign)) {
            CommissionVertical::Classified => $campaign->classifiedListing?->classified_category_id,
            CommissionVertical::Travel => null,
            CommissionVertical::Product => $campaign->vendorListing?->productVariant?->product?->category_id
                ?? $campaign->adminListing?->productVariant?->product?->category_id
                ?? $campaign->marketerListing?->productVariant?->product?->category_id,
        };
    }

    /**
     * The influencer's fixed amount for a campaign context, before any campaign exists (wizard preview and the
     * snapshot taken at creation).
     *
     * @return array{amount: float, source: string} source = profile_rule | default | none
     */
    public function influencerFixedFor(Marketer $marketer, ?string $countryId, CommissionVertical $vertical, ?string $categoryId): array
    {
        $rule = $this->profileRule($marketer, $vertical, $categoryId);
        $flat = $rule && $rule->commission_flat_amount !== null && in_array($rule->commission_mode, ['fixed', 'both'], true)
            ? (float) $rule->commission_flat_amount
            : 0.0;
        if ($flat > 0) {
            return ['amount' => round($flat, 2), 'source' => self::SOURCE_PROFILE];
        }

        $default = $this->defaults->influencerFixed($countryId, $vertical, $categoryId);
        if ($default['amount'] > 0) {
            return ['amount' => round($default['amount'], 2), 'source' => self::SOURCE_DEFAULT];
        }

        return ['amount' => 0.0, 'source' => self::SOURCE_NONE];
    }

    /**
     * @return array{0: float, 1: string, 2: string, 3: ?float, 4: ?float} gross, source, mode, rate, fixed
     */
    private function influencer(MarketerCampaign $campaign, ?MarketerCampaignInvitation $invitation, ?MarketerCommissionRule $rule, CommissionVertical $vertical, ?string $categoryId): array
    {
        if ($invitation
            && $invitation->influencer_fixed_amount !== null
            && ($invitation->influencer_fixed_status ?? MarketerCampaignInvitation::FIXED_NONE) !== MarketerCampaignInvitation::FIXED_NONE) {
            $amount = round((float) $invitation->influencer_fixed_amount, 2);

            return [$amount, self::SOURCE_SNAPSHOT, $amount > 0 ? CommissionResult::MODE_FIXED : CommissionResult::MODE_NONE, null, $amount];
        }

        $flat = $rule && $rule->commission_flat_amount !== null && in_array($rule->commission_mode, ['fixed', 'both'], true)
            ? round((float) $rule->commission_flat_amount, 2)
            : 0.0;
        if ($flat > 0) {
            return [$flat, self::SOURCE_PROFILE, CommissionResult::MODE_FIXED, null, $flat];
        }

        $default = $this->defaults->influencerFixed($campaign->country_id, $vertical, $categoryId);
        if ($default['amount'] > 0) {
            $amount = round($default['amount'], 2);

            return [$amount, self::SOURCE_DEFAULT, CommissionResult::MODE_FIXED, null, $amount];
        }

        return [0.0, self::SOURCE_NONE, CommissionResult::MODE_NONE, null, null];
    }

    /**
     * @return array{0: float, 1: string, 2: string, 3: ?float, 4: ?float}
     */
    private function percentageOnly(?MarketerCommissionRule $rule, float $basis): array
    {
        $rate = $this->ruleRate($rule);
        if ($rate <= 0) {
            return [0.0, self::SOURCE_NONE, CommissionResult::MODE_NONE, null, null];
        }

        return [round($basis * $rate / 100, 2), self::SOURCE_PROFILE, CommissionResult::MODE_PERCENTAGE, $rate, null];
    }

    /**
     * @return array{0: float, 1: string, 2: string, 3: ?float, 4: ?float}
     */
    private function fixedAndPercentage(?MarketerCommissionRule $rule, float $basis, int $quantity): array
    {
        $rate = $this->ruleRate($rule);
        $flat = $rule && $rule->commission_flat_amount !== null && in_array($rule->commission_mode, ['fixed', 'both'], true)
            ? round((float) $rule->commission_flat_amount, 2)
            : 0.0;
        if ($rate <= 0 && $flat <= 0) {
            return [0.0, self::SOURCE_NONE, CommissionResult::MODE_NONE, null, null];
        }

        $gross = round(($flat * $quantity) + ($rate > 0 ? round($basis * $rate / 100, 2) : 0.0), 2);
        $mode = $rate > 0 && $flat > 0 ? CommissionResult::MODE_BOTH : ($flat > 0 ? CommissionResult::MODE_FIXED : CommissionResult::MODE_PERCENTAGE);

        return [$gross, self::SOURCE_PROFILE, $mode, $rate > 0 ? $rate : null, $flat > 0 ? $flat : null];
    }

    private function ruleRate(?MarketerCommissionRule $rule): float
    {
        if (! $rule || ! in_array($rule->commission_mode ?: 'percentage', ['percentage', 'both'], true)) {
            return 0.0;
        }

        return max(0.0, (float) $rule->commission_rate);
    }

    /**
     * The marketer's own rule for the category (nearest ancestor first), else his rule for the whole vertical
     * (unless that rule excludes the category). Platform-wide rows (marketer_id NULL) are legacy and ignored: the
     * platform default for an influencer is the Marketer Settings grid.
     */
    private function profileRule(Marketer $marketer, CommissionVertical $vertical, ?string $categoryId): ?MarketerCommissionRule
    {
        $scope = MarketerCommissionRule::scopeFor($vertical);
        $chain = $this->categoryChain($scope, $categoryId);

        $rules = MarketerCommissionRule::query()
            ->where('marketer_id', $marketer->id)
            ->where('scope', $scope)
            ->where(fn ($q) => $q->whereNull('category_id')->orWhereIn('category_id', $chain ?: ['-']))
            ->get()
            ->keyBy(fn (MarketerCommissionRule $r) => $r->category_id ?? '_default');

        foreach ($chain as $id) {
            if ($rules->has($id)) {
                return $rules->get($id);
            }
        }

        $default = $rules->get('_default');

        return $default && ! $default->excludes($chain) ? $default : null;
    }

    /** @return list<string> category id, then its ancestors */
    private function categoryChain(string $scope, ?string $categoryId): array
    {
        if (! $categoryId) {
            return [];
        }

        $model = MarketerCommissionRule::categoryClassFor($scope);
        $ids = [];
        $current = $categoryId;
        while ($current && ! in_array($current, $ids, true) && count($ids) < 20) {
            $ids[] = $current;
            $current = $model::query()->whereKey($current)->value('parent_id');
        }

        return $ids;
    }
}
