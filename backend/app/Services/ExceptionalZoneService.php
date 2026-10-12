<?php

namespace App\Services;

use App\Models\City;
use App\Models\PlatformShippingSubsidy;
use App\Models\VendorExceptionalZoneAlert;
use App\Models\VendorListing;
use App\Support\Money;

/**
 * All money values are DECIMAL(19,4) base currency units — never divide or
 * multiply by 100 here.
 */
class ExceptionalZoneService
{
    /**
     * Given a customer's city and a vendor listing, check if there is an
     * accepted exceptional zone alert whose city_ids include that city,
     * scoped to the listing's warehouse and vendor.
     *
     * Returns null if no exceptional zone applies.
     * Returns an array with gap details if one does.
     */
    public function resolve(VendorListing $listing, City $city, int|float $normalShippingFee): ?array
    {
        if (! $listing->warehouse_id) {
            return null;
        }

        $alert = VendorExceptionalZoneAlert::where('vendor_id', $listing->vendor_id)
            ->where('warehouse_id', $listing->warehouse_id)
            ->where('status', 'accepted')
            ->whereJsonContains('city_ids', $city->id)
            ->first();

        if (! $alert) {
            return null;
        }

        $reportedCarrierFee = $alert->reported_carrier_fee;

        if ($reportedCarrierFee <= $normalShippingFee) {
            return null;
        }

        $gap = round($reportedCarrierFee - $normalShippingFee, 2);

        $zoneId = $city->shipping_zone_id;
        $subsidyRule = null;

        if ($zoneId) {
            $subsidyRule = PlatformShippingSubsidy::where('shipping_zone_id', $zoneId)
                ->where('is_active', 1)
                ->where(function ($q) use ($listing) {
                    $q->where('warehouse_id', $listing->warehouse_id)
                        ->orWhereNull('warehouse_id');
                })
                ->orderByRaw('CASE WHEN warehouse_id = ? THEN 0 ELSE 1 END', [$listing->warehouse_id])
                ->first();
        }

        if ($subsidyRule) {
            if ($subsidyRule->split_type === 'percentage') {
                $vendorContribution = round($gap * $subsidyRule->vendor_share_pct / 100, 2);
                $adminSubsidy = round($gap - $vendorContribution, 2);

                if ($subsidyRule->subsidy_cap > 0 && $adminSubsidy > $subsidyRule->subsidy_cap) {
                    $excess = round($adminSubsidy - $subsidyRule->subsidy_cap, 2);
                    $adminSubsidy = $subsidyRule->subsidy_cap;
                    $vendorContribution += $excess;
                }
            } else {
                $vendorContribution = min($subsidyRule->vendor_fixed_amount, $gap);
                $adminSubsidy = min($subsidyRule->admin_fixed_amount, round($gap - $vendorContribution, 2));
                $residual = round($gap - $vendorContribution - $adminSubsidy, 2);
                $adminSubsidy += $residual;
            }

            // INVARIANT: vendor_contribution + admin_subsidy must always equal
            // the gap exactly (zero-sum, no rounding leakage). Any drift lands
            // on the admin side rather than silently under/over-charging the
            // vendor.
            $drift = round($gap - ($vendorContribution + $adminSubsidy), 2);
            if (abs($drift) > 0.00005) {
                $adminSubsidy += $drift;
            }

            $subsidyRuleId = $subsidyRule->id;
        } else {
            // No subsidy rule configured yet: vendor absorbs 100% of the gap.
            $vendorContribution = $gap;
            $adminSubsidy = 0;
            $subsidyRuleId = null;
        }

        return [
            'alert_id' => $alert->id,
            'reported_carrier_fee' => $reportedCarrierFee,
            'gap' => Money::number($gap),
            'vendor_contribution' => Money::number(round($vendorContribution, 2)),
            'admin_subsidy' => Money::number(round($adminSubsidy, 2)),
            'subsidy_rule_id' => $subsidyRuleId,
        ];
    }
}
