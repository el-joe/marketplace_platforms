<?php

namespace App\Services;

use App\Models\City;
use App\Models\PlatformShippingSubsidy;
use App\Models\VendorExceptionalZoneAlert;
use App\Models\VendorListing;

/**
 * All money values are BIGINT base currency units — never divide or
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
    public function resolve(VendorListing $listing, City $city, int $normalShippingFee): ?array
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

        $reportedCarrierFee = (int) $alert->reported_carrier_fee;

        if ($reportedCarrierFee <= $normalShippingFee) {
            return null;
        }

        $gap = $reportedCarrierFee - $normalShippingFee;

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
                $vendorContribution = (int) floor($gap * $subsidyRule->vendor_share_pct / 100);
                $adminSubsidy = $gap - $vendorContribution;

                if ($subsidyRule->subsidy_cap > 0 && $adminSubsidy > $subsidyRule->subsidy_cap) {
                    $excess = $adminSubsidy - $subsidyRule->subsidy_cap;
                    $adminSubsidy = $subsidyRule->subsidy_cap;
                    $vendorContribution += $excess;
                }
            } else {
                $vendorContribution = min((int) $subsidyRule->vendor_fixed_amount, $gap);
                $adminSubsidy = min((int) $subsidyRule->admin_fixed_amount, $gap - $vendorContribution);
                $residual = $gap - $vendorContribution - $adminSubsidy;
                $adminSubsidy += $residual;
            }

            // INVARIANT: vendor_contribution + admin_subsidy must always equal
            // the gap exactly (zero-sum, no rounding leakage). Any drift lands
            // on the admin side rather than silently under/over-charging the
            // vendor.
            $drift = $gap - ($vendorContribution + $adminSubsidy);
            if ($drift !== 0) {
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
            'gap' => $gap,
            'vendor_contribution' => $vendorContribution,
            'admin_subsidy' => $adminSubsidy,
            'subsidy_rule_id' => $subsidyRuleId,
        ];
    }
}
