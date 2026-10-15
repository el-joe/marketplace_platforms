<?php

namespace App\Services\Marketer;

use App\Enums\CommissionVertical;
use App\Models\Marketer;
use App\Models\MarketerCommissionRule;

/**
 * The platform's commission fee taken from a marketer's commission:
 *
 *     fee = percentage x commission + fixed        (never more than the commission)
 *     net = commission - fee                        (never below 0)
 *
 * Resolution per field (percentage / fixed), first non-null wins:
 *   1. the marketer's profile rule for the category (fee_percentage / fee_fixed);
 *   2. the marketer's profile rule for the whole vertical;
 *   3. the platform default from Marketer Settings (category row -> vertical-wide row, by country).
 */
class CommissionFeeService
{
    public function __construct(private readonly MarketerDefaultCommissionResolver $defaults) {}

    /**
     * @return array{0: float, 1: float} [percentage, fixed]
     */
    public function resolve(Marketer $marketer, CommissionVertical|string $vertical, ?string $categoryId = null, ?string $countryId = null): array
    {
        $detail = $this->resolveDetailed($marketer, $vertical, $categoryId, $countryId);

        return [$detail['percentage'], $detail['fixed']];
    }

    /**
     * @return array{percentage: float, fixed: float, percentage_source: string, fixed_source: string}
     *         source = profile_category | profile_vertical | default | none
     */
    public function resolveDetailed(Marketer $marketer, CommissionVertical|string $vertical, ?string $categoryId = null, ?string $countryId = null): array
    {
        $verticalEnum = $vertical instanceof CommissionVertical ? $vertical : CommissionVertical::from($vertical);
        $scope = MarketerCommissionRule::scopeFor($verticalEnum);
        $countryId ??= $marketer->country_id;

        $rules = MarketerCommissionRule::query()
            ->where('marketer_id', $marketer->id)
            ->where('scope', $scope)
            ->where(function ($q) use ($categoryId) {
                $q->whereNull('category_id');
                if ($categoryId) {
                    $q->orWhere('category_id', $categoryId);
                }
            })
            ->get();

        $categoryRule = $categoryId ? $rules->firstWhere('category_id', $categoryId) : null;
        $verticalRule = $rules->first(fn ($r) => $r->category_id === null);

        $default = $this->defaults->commissionFee($countryId, $verticalEnum, $categoryId);

        [$percentage, $percentageSource] = $this->pick(
            [[$categoryRule?->fee_percentage, 'profile_category'], [$verticalRule?->fee_percentage, 'profile_vertical']],
            (float) $default['percentage'],
            $default['source'] === MarketerDefaultCommissionResolver::SOURCE_NONE ? 'none' : 'default'
        );
        [$fixed, $fixedSource] = $this->pick(
            [[$categoryRule?->fee_fixed, 'profile_category'], [$verticalRule?->fee_fixed, 'profile_vertical']],
            (float) $default['fixed'],
            $default['source'] === MarketerDefaultCommissionResolver::SOURCE_NONE ? 'none' : 'default'
        );

        return [
            'percentage' => $percentage,
            'fixed' => $fixed,
            'percentage_source' => $percentageSource,
            'fixed_source' => $fixedSource,
        ];
    }

    /**
     * Split a gross commission into platform fee and marketer net.
     *
     * @return array{gross: float, fee: float, net: float}
     */
    public function apply(float $commission, float $percentage, float $fixed): array
    {
        return self::split($commission, $percentage, $fixed);
    }

    /**
     * Pure arithmetic of the commission fee: fee = pct x gross + fixed, capped at the gross; net never below 0.
     * Rounded half up to 2 decimals (the precision money is credited at).
     *
     * @return array{gross: float, fee: float, net: float}
     */
    public static function split(float $commission, float $percentage, float $fixed): array
    {
        $commission = max(0.0, round($commission, 2));
        $fee = round($commission * $percentage / 100 + $fixed, 2);
        $fee = min($commission, max(0.0, $fee));

        return ['gross' => $commission, 'fee' => $fee, 'net' => round($commission - $fee, 2)];
    }

    /**
     * @param  list<array{0: mixed, 1: string}>  $overrides
     * @return array{0: float, 1: string}
     */
    private function pick(array $overrides, float $default, string $defaultSource): array
    {
        foreach ($overrides as [$value, $source]) {
            if ($value !== null && $value !== '') {
                return [(float) $value, $source];
            }
        }

        return [$default, $defaultSource];
    }
}
