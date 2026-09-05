<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'admin';

        $permissions = [
            // Dashboard
            'dashboard.view',
            // Catalog – Products
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'products.publish',
            'products.cost_data.view',
            'products.cost_data.edit',
            // Catalog – Admin Listings
            'admin_listings.view',
            'admin_listings.create',
            'admin_listings.edit',
            'admin_listings.delete',
            'admin_listings.toggle_status',
            // Catalog – Categories
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',
            // Catalog – Brands
            'brands.view',
            'brands.create',
            'brands.edit',
            'brands.delete',
            // Catalog – Warranty Plans
            'warranty_plans.view',
            'warranty_plans.create',
            'warranty_plans.edit',
            'warranty_plans.delete',
            // Catalog – Attributes
            'attributes.view',
            'attributes.create',
            'attributes.edit',
            // Vendors
            'vendors.view',
            'vendors.create',
            'vendors.approve',
            'vendors.suspend',
            'vendors.blacklist',
            'vendors.documents.verify',
            'vendors.strikes.issue',
            'vendors.payouts.hold',
            'vendors.assigned_only',
            'vendors.edit',
            // Orders
            'orders.view',
            'orders.cancel',
            'orders.refund',
            'orders.dispute',
            'orders.flag_fraud',
            // Finance
            'payouts.view',
            'payouts.approve',
            'payouts.export',
            'commissions.view',
            'commissions.create',
            'commissions.edit',
            'ledger.view',
            'analytics.view',
            'transactions.view',
            // Marketing
            'banners.view',
            'banners.create',
            'banners.edit',
            'banners.delete',
            'announcement_bars.view',
            'announcement_bars.create',
            'announcement_bars.edit',
            'announcement_bars.delete',
            'flash_sales.view',
            'flash_sales.create',
            'flash_sales.edit',
            'flash_sales.review_submissions',
            'coupons.view',
            'coupons.create',
            'coupons.edit',
            'coupons.delete',
            'cart_card_offers.view',
            'cart_card_offers.create',
            'cart_card_offers.edit',
            'cart_card_offers.delete',
            'gift_cards.view',
            'gift_cards.create',
            'gift_cards.edit',
            'vouchers.view',
            'vouchers.create',
            'vouchers.edit',
            'vouchers.delete',
            'ad_campaigns.view',
            'ad_campaigns.create',
            'ad_campaigns.edit',
            'ad_campaigns.delete',
            'ad_campaigns.manage',
            'vendor_change_requests.view',
            'vendor_change_requests.approve',
            'vendor_product_certifications.view',
            'vendor_product_certifications.approve',
            'travel_agency_change_requests.view',
            'travel_agency_change_requests.approve',
            'pages.view',
            'pages.manage',
            'app_contexts.view',
            'app_contexts.manage',
            // Customers
            'customers.view',
            'customers.edit',
            'customers.suspend',
            'wallet.manual_adjust',
            'wishlists.view',
            // Notifications
            'notifications.view',
            'notifications.send',
            // Reviews
            'reviews.view',
            'reviews.approve',
            'reviews.reject',
            'reviews.delete',
            'reviews.edit',
            // Disputes
            'disputes.view',
            'disputes.resolve',
            // Returns
            'returns.view',
            'returns.manage',
            // Carrier Claims
            'carrier-claims.view',
            'carrier-claims.resolve',
            // Warranty Claims
            'warranty_claims.view',
            'warranty_claims.manage',
            // Support
            'support.view',
            'support.reply',
            'support.assign',
            // System
            'countries.view',
            'countries.edit',
            'countries.launch',
            'warehouses.view',
            'warehouses.manage',
            'packaging.manage',
            'settings.view',
            'settings.edit',
            'settings.content',
            'portal_content.view',
            'portal_content.edit',
            'faqs.view',
            'faqs.create',
            'faqs.edit',
            'faqs.delete',
            'admins.view',
            'admins.create',
            'admins.edit',
            'admins.delete',
            'admins.impersonate',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'activity-log.view',
            // Travel
            'travel.view',
            'travel.approve',
            'travel.reject',
            'travel.suspend',
            'travel.geography.manage',
            // Classifieds
            'classifieds.view',
            // Marketer accounts (admin)
            'marketers.view',
            'marketers.manage',
            // Marketer campaigns (admin)
            'marketer_campaigns.view',
            'marketer_campaigns.approve',
            'marketer_campaigns.reject',
            'marketer_commission_settings.view',
            'marketer_commission_settings.edit',
            'marketer_fee_settings.view',
            'marketer_fee_settings.edit',
            'vendor_marketer_type.edit',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        $countPermissions = Permission::where('guard_name', $guard)->count();
        $this->command->info('Permissions seeded: ' . $countPermissions . ' permissions (guard: ' . $guard . ').');

        $this->command->info('Permissions seeded: ' . count($permissions) . ' permissions (guard: ' . $guard . ').');

        $vendorGuard = 'vendor';

        $vendorPermissions = [
            // Vendor panel — campaign management (available to vendor+marketer only)
            'marketer_campaigns.view',
            'marketer_campaigns.create',
            'marketer_campaigns.edit',
            'marketer_campaigns.cancel',
            // Vendor panel — marketer profile management
            'marketer_profile.view',
            'marketer_profile.edit',
            // Vendor panel — marketer campaign requests (invitations received as marketer)
            'marketer_invitations.view',
            'marketer_invitations.respond',
            // Vendor panel — marketer reports
            'marketer_reports.view',
        ];

        foreach ($vendorPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $vendorGuard]);
        }

        $countVendorPermissions = Permission::where('guard_name', $vendorGuard)->count();
        $this->command->info('Permissions seeded: ' . $countVendorPermissions . ' permissions (guard: ' . $vendorGuard . ').');
    }
}
