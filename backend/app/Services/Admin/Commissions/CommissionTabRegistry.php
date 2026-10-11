<?php

namespace App\Services\Admin\Commissions;

use App\Models\Admin;

/**
 * Catalogue of everything shown on the unified /commissions page.
 *
 * `item` is the number of the entry in COMMISSIONS_REPORT.md section 0 / the QA brief, so coverage can be
 * audited. `effect` records what the stored value really does in the code today (verified against the
 * call paths, not inferred from column names); the UI renders it as a badge so nothing is presented as
 * active when it is not.
 *
 * @phpstan-type Tab array{key: string, item: int, group: string, permission: string, effect: string, access: string, manage: list<array{label: string, route: string, params?: array<string, mixed>}>}
 */
class CommissionTabRegistry
{
    /** Drives checkout, payout or marketer-conversion math. */
    public const EFFECT_APPLIED = 'applied';

    /** Computed by the system; no configurable input. */
    public const EFFECT_SYSTEM = 'system';

    /** Stored but no code path reads it when money moves. */
    public const EFFECT_NOT_APPLIED = 'not_applied';

    /** Read only by analytics/reporting, never by a charge or payout. */
    public const EFFECT_ANALYTICS = 'analytics_only';

    /** Report figure with no ledger or payout deduction found. */
    public const EFFECT_REPORT = 'report_only';

    /** Fees that are not commissions, listed so the money split is complete. */
    public const EFFECT_REFERENCE = 'reference';

    /** Editable on this page. */
    public const ACCESS_EDITABLE = 'editable';

    /** Edited in an existing screen; this page links to it. */
    public const ACCESS_LINKED = 'linked';

    /** No editor exists. */
    public const ACCESS_READ_ONLY = 'read_only';

    public const GROUP_PLATFORM = 'platform';

    public const GROUP_MARKETER = 'marketer';

    public const GROUP_ACQUISITION = 'acquisition';

    public const GROUP_TRAVEL = 'travel';

    public const GROUP_SHIPPING = 'shipping';

    /**
     * @return array<string, Tab> keyed by tab key, in display order
     */
    public static function tabs(): array
    {
        $tabs = [
            ['platform-rules', 1, self::GROUP_PLATFORM, 'categories.view', self::EFFECT_APPLIED, self::ACCESS_EDITABLE, [
                ['label' => 'manage_categories', 'route' => 'admin.categories.index'],
            ]],
            ['vendor-rules', 2, self::GROUP_PLATFORM, 'vendors.view', self::EFFECT_APPLIED, self::ACCESS_EDITABLE, []],
            ['vendor-flat-rate', 3, self::GROUP_PLATFORM, 'vendors.view', self::EFFECT_APPLIED, self::ACCESS_EDITABLE, [
                ['label' => 'manage_vendors', 'route' => 'admin.vendors.index'],
            ]],
            ['vendor-discount', 4, self::GROUP_PLATFORM, 'vendors.view', self::EFFECT_APPLIED, self::ACCESS_EDITABLE, [
                ['label' => 'manage_vendors', 'route' => 'admin.vendors.index'],
            ]],
            ['plan-discount', 5, self::GROUP_PLATFORM, 'vendors.view', self::EFFECT_NOT_APPLIED, self::ACCESS_LINKED, [
                ['label' => 'manage_plans', 'route' => 'admin.subscriptions.plans.index'],
            ]],
            ['admin-listing', 6, self::GROUP_PLATFORM, 'admin_listings.view', self::EFFECT_SYSTEM, self::ACCESS_READ_ONLY, []],
            ['flash-override', 7, self::GROUP_PLATFORM, 'flash_sales.view', self::EFFECT_ANALYTICS, self::ACCESS_LINKED, [
                ['label' => 'manage_flash_sales', 'route' => 'admin.flash-sales.index'],
            ]],

            ['campaigns', 8, self::GROUP_MARKETER, 'marketer_campaigns.view', self::EFFECT_APPLIED, self::ACCESS_EDITABLE, [
                ['label' => 'manage_campaigns', 'route' => 'admin.marketer-campaigns.index'],
            ]],
            ['marketer-rules', 9, self::GROUP_MARKETER, 'marketers.view', self::EFFECT_APPLIED, self::ACCESS_EDITABLE, [
                ['label' => 'manage_marketers', 'route' => 'admin.marketers.index'],
            ]],
            ['legacy-stores', 10, self::GROUP_MARKETER, 'marketers.view', self::EFFECT_NOT_APPLIED, self::ACCESS_READ_ONLY, []],
            ['country-amounts', 11, self::GROUP_MARKETER, 'marketer_commission_settings.view', self::EFFECT_APPLIED, self::ACCESS_EDITABLE, [
                ['label' => 'manage_categories', 'route' => 'admin.categories.index'],
            ]],
            ['listing-percentages', 12, self::GROUP_MARKETER, 'marketer_commission_settings.view', self::EFFECT_NOT_APPLIED, self::ACCESS_READ_ONLY, []],
            ['marketer-discount', 13, self::GROUP_MARKETER, 'marketers.view', self::EFFECT_APPLIED, self::ACCESS_LINKED, [
                ['label' => 'manage_marketers', 'route' => 'admin.marketers.index'],
            ]],
            ['flash-bonus', 14, self::GROUP_MARKETER, 'flash_sales.view', self::EFFECT_APPLIED, self::ACCESS_EDITABLE, [
                ['label' => 'manage_flash_sales', 'route' => 'admin.flash-sales.index'],
            ]],
            ['platform-share', 15, self::GROUP_MARKETER, 'marketer_campaigns.view', self::EFFECT_APPLIED, self::ACCESS_LINKED, [
                ['label' => 'manage_campaigns', 'route' => 'admin.marketer-campaigns.index'],
            ]],

            ['acquisition', 16, self::GROUP_ACQUISITION, 'vendors.view', self::EFFECT_APPLIED, self::ACCESS_EDITABLE, [
                ['label' => 'manage_acquisition', 'route' => 'admin.acquisition-commissions.index'],
            ]],

            ['travel-platform', 17, self::GROUP_TRAVEL, 'travel.view', self::EFFECT_REPORT, self::ACCESS_READ_ONLY, []],
            ['travel-offers', 18, self::GROUP_TRAVEL, 'travel.view', self::EFFECT_APPLIED, self::ACCESS_READ_ONLY, []],

            ['shipping-rules', 19, self::GROUP_SHIPPING, 'admin_listings.view', self::EFFECT_NOT_APPLIED, self::ACCESS_EDITABLE, [
                ['label' => 'manage_admin_listings', 'route' => 'admin.admin-listings.index'],
            ]],
            ['related-fees', 20, self::GROUP_SHIPPING, 'marketer_commission_settings.view', self::EFFECT_REFERENCE, self::ACCESS_LINKED, [
                ['label' => 'manage_influencer_fee', 'route' => 'admin.marketer-settings.index'],
            ]],
        ];

        $indexed = [];
        foreach ($tabs as [$key, $item, $group, $permission, $effect, $access, $manage]) {
            $indexed[$key] = compact('key', 'item', 'group', 'permission', 'effect', 'access', 'manage');
        }

        return $indexed;
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return [
            self::GROUP_PLATFORM,
            self::GROUP_MARKETER,
            self::GROUP_ACQUISITION,
            self::GROUP_TRAVEL,
            self::GROUP_SHIPPING,
        ];
    }

    /**
     * Tabs the admin may open, grouped, in display order. Empty groups are dropped.
     *
     * @return array<string, array<string, Tab>>
     */
    public static function visibleFor(Admin $admin): array
    {
        $grouped = array_fill_keys(self::groups(), []);

        foreach (self::tabs() as $key => $tab) {
            if ($admin->can($tab['permission'])) {
                $grouped[$tab['group']][$key] = $tab;
            }
        }

        return array_filter($grouped);
    }
}
