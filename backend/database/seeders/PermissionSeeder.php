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
            // AI features dashboard — Admin\AiDashboardController
            'ai.view',
            'ai.manage',
            // Catalog – Products
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'products.publish',
            'products.cost_data.view',
            'products.cost_data.edit',
            // Vendor/marketer requests for new catalog products — Admin\ProductRequestController
            'product_requests.view',
            'product_requests.review',
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
            // Vendor acquisition commissions — Admin\VendorAcquisitionController
            'acquisition_commissions.view',
            'acquisition_commissions.manage',
            // Platform-commission discount for sales through a vendor's own product link / QR — Admin\ProductLinkCommissionRuleController
            'product_link_commission.view',
            'product_link_commission.manage',
            // Orders
            'orders.view',
            'orders.edit',
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
            'transactions.edit',
            // Vendor subscriptions & plans — Admin\SubscriptionController
            'subscriptions.view',
            'subscriptions.manage',
            // Wallets, withdrawals, COD settlements — Admin\WalletController
            'wallets.view',
            'wallets.manage',
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
            'ad_slots.view',
            'ad_slots.create',
            'ad_slots.edit',
            'ad_slots.delete',
            'ad_bookings.view',
            'ad_bookings.review',
            'ad_bookings.manage',
            'ad_bookings.finance',
            'vendor_change_requests.view',
            'vendor_change_requests.approve',
            'vendor_product_certifications.view',
            'vendor_product_certifications.approve',
            'travel_agency_change_requests.view',
            'travel_agency_change_requests.approve',
            'pages.view',
            'pages.manage',
            'pages.delete_published',
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
            'disputes.manage',
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
            // Revert FBN storage fee status (Paid -> Invoiced -> Pending) — Admin\FbnController
            'fbn_storage_fees.revert_status',
            // FBN inbound / marketplace rules and storage fees — Admin\FbnController
            'fbn.view',
            'fbn.manage',
            'fbn_storage_fees.view',
            'fbn_storage_fees.manage',
            // Delivery riders, zones, assignments — Admin\Delivery*Controller
            'delivery.view',
            'delivery.manage',
            // Shipping companies (carrier portal accounts); edits use settings.edit
            'shipping_companies.view',
            'packaging.manage',
            'settings.view',
            'settings.edit',
            'settings.content',
            // Portal content (see App\Support\PortalPermissions) + analytics used by the portal funnel dashboard.
            'portal_content.view',
            'portal_content.create',
            'portal_content.edit',
            'portal_content.delete',
            'portal_content.publish',
            'portal_content.manage',
            'portal_analytics.view',
            'faqs.view',
            'faqs.create',
            'faqs.edit',
            'faqs.delete',
            // Granular content / settings sections (client test report, point 19)
            'blog.view',
            'blog.manage',
            'help_center.view',
            'help_center.manage',
            'adsupport.view',
            'adsupport.manage',
            'live_streams.view',
            'live_streams.manage',
            'cities.view',
            'cities.manage',
            'currencies.view',
            'currencies.manage',
            'newsletter.view',
            'newsletter.manage',
            'footer_settings.manage',
            // Static content pages (About Us, Terms, Privacy, ...) — Admin\StaticPageController
            'static_pages.view',
            'static_pages.create',
            'static_pages.edit',
            'static_pages.delete',
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
            'classifieds.manage',
            // Contract templates + signed customer contracts — Admin\ContractTemplateController
            'contracts.view',
            'contracts.manage',
            // Marketer accounts (admin)
            'marketers.view',
            'marketers.manage',
            // Marketer campaigns (admin)
            'marketer_campaigns.view',
            'marketer_campaigns.approve',
            'marketer_campaigns.reject',
            'marketer_commission_settings.view',
            'marketer_commission_settings.edit',
            // Writer for vendor-specific rows in the `commissions` table (see /commissions).
            'commission_rules.manage',
            'marketer_fee_settings.view',
            'marketer_fee_settings.edit',
            'vendor_marketer_type.edit',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        $countPermissions = Permission::where('guard_name', $guard)->count();
        $this->command->info('Permissions seeded: '.$countPermissions.' permissions (guard: '.$guard.').');

        $this->command->info('Permissions seeded: '.count($permissions).' permissions (guard: '.$guard.').');

        $vendorGuard = 'vendor';

        $vendorPermissions = [
            // Vendor panel — campaign management (available to vendor+marketer only)
            'marketer_campaigns.view',
            'marketer_campaigns.create',
            'marketer_campaigns.edit',
            'marketer_campaigns.cancel',
            'marketer_campaigns.delete',
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
        $this->command->info('Permissions seeded: '.$countVendorPermissions.' permissions (guard: '.$vendorGuard.').');
    }
}
