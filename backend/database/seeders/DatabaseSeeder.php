<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Master orchestrator. All seeders use firstOrCreate() so this is safe to
 * re-run on an existing database without duplicating rows or breaking logins.
 *
 * Dev password for every NEW seeded account: password123
 * Existing admin accounts created by AdminSeeder keep password: 123456
 */
class DatabaseSeeder extends Seeder
{
    public function run(bool $permissionsOnly = false): void
    {
        if ($permissionsOnly) {
            $this->call([
                AdminSeeder::class,

                PermissionSeeder::class,
                PermissionRoleSeeder::class,
                RolesAndPermissionsSeeder::class,
                AdminRoleAssignmentSeeder::class,

                TravelAgencyPermissionSeeder::class,
                TravelAgencyMemberRoleMigrationSeeder::class,

                VendorPermissionSeeder::class,
                VendorAdminRoleMigrationSeeder::class,


            ]);

            return;
        }

        $this->call([
                // ── Core reference data ────────────────────────────────────────
            BlockTypeSeeder::class,
            BlockTypeDescriptionSeeder::class,
            CountrySeeder::class,
            PaymentInstallmentConfigSeeder::class,
            CitySeeder::class,
            TravelCountrySeeder::class,
            TravelCitySeeder::class,
            CategoryAttributeSeeder::class,
            BrandShippingSeeder::class,
            SettingsSeeder::class,
            SubscriptionPlanSeeder::class,
            BannerPlacementDefinitionsSeeder::class,

                // ── Admin accounts (creates the 4 base rows) ──────────────────
            AdminSeeder::class,

            PermissionSeeder::class,
            PermissionRoleSeeder::class,
            RolesAndPermissionsSeeder::class,
            AdminRoleAssignmentSeeder::class,

                // ── Cart card cashback offers (needs countries + admins) ────────
            CartCardOfferSeeder::class,

                // ── All guard user accounts ────────────────────────────────────
            VendorSeeder::class,

                // ── Vendor guard permissions & role migration ──────────────────
            VendorPermissionSeeder::class,
            VendorAdminRoleMigrationSeeder::class,

            CustomerSeeder::class,
            MarketerSeeder::class,
            ShippingCompanySeeder::class,
            DeliveryAgentSeeder::class,
            TravelAgencySeeder::class,

                // ── Travel agency guard permissions & role migration ────────────
            TravelAgencyPermissionSeeder::class,
            TravelAgencyMemberRoleMigrationSeeder::class,

                // ── Products (needs vendor data from VendorSeeder above) ───────
            ProductSeeder::class,

                // ── Delivery assignment test fixtures (Phase 2 Playwright tests) ─
            DeliveryAssignmentSeeder::class,

                // ── Travel categories starter set ─────────────────────────────
            TravelCategorySeeder::class,

                // ── Blog module starter content ────────────────────────────────
            BlogSeeder::class,

                // ── Knowledge Hub (Ad Support) starter content ─────────────────
            AdSupportSeeder::class,

                // ── Seller Help Center starter content ──────────────────────────
            HelpCenterSeeder::class,

                // ── FAQ CRUD module starter content ─────────────────────────────
            FaqSeeder::class,

                // ── Portal Content CMS (admin-editable bilingual marketing text) ─
            PortalContentSeeder::class,
            PortalContentSeederBatch1::class,
            PortalContentSeederBatch2::class,
            PortalContentSeederBatch3::class,
            PortalContentSeederBatch4::class,

                // ── Light cross-reference demo data (run last) ─────────────────
            DemoDataSeeder::class,

                // ── Home page CMS blocks (per-country) ──────────────────────────
            HomePageSeeder::class,
        ]);

        // ── Dashboard QA lifecycle orders (opt-in, TEST/DEV only) ─────────
        // Run separately: php artisan db:seed --class=OrderLifecycleTestSeeder
        // $this->call(OrderLifecycleTestSeeder::class);

        // ── Credentials summary table ──────────────────────────────────────
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  ALL SEEDED ACCOUNTS  |  password for new accounts: password123');
        $this->command->info('  Existing admin rows (AdminSeeder) still use password:  123456');
        $this->command->info('═══════════════════════════════════════════════════════════════');

        $this->command->table(
            ['Guard', 'Panel URL', 'Email', 'Role / Status'],
            [
                ['admin', 'admin.noon.loc', 'admin@admin.com', 'super_admin (pw: 123456)'],
                ['admin', 'admin.noon.loc', 'mohamed@admin.com', 'operations_admin (pw: 123456)'],
                ['admin', 'admin.noon.loc', 'layla@admin.com', 'marketing_admin (pw: 123456)'],
                ['admin', 'admin.noon.loc', 'sara@admin.com', 'finance_admin (pw: 123456)'],
                ['vendor', 'partner.noon.loc', 'khalid@techzone.com', 'owner — TechZone (active)'],
                ['vendor', 'partner.noon.loc', 'reem@bellafashion.com', 'owner — Bella Fashion (active)'],
                ['vendor', 'partner.noon.loc', 'ahmed@cairohome.com', 'owner — Cairo Home (under_review)'],
                ['vendor', 'partner.noon.loc', 'fahad@kuwaitgadgets.com', 'owner — Kuwait Gadgets (active)'],
                ['vendor', 'partner.noon.loc', 'suspended-owner@vendor.com', 'owner — Suspended Store'],
                ['web', 'storefront', 'ali@customer.com', 'active customer'],
                ['web', 'storefront', 'dina@customer.com', 'active customer'],
                ['web', 'storefront', 'suspended@customer.com', 'suspended customer'],
                ['marketer', 'marketer.noon.loc', 'yasmin@marketer.com', 'active influencer'],
                ['marketer', 'marketer.noon.loc', 'hana@marketer.com', 'active celebrity'],
                ['marketer', 'marketer.noon.loc', 'pending-marketer@marketer.com', 'pending influencer'],
                ['marketer', 'marketer.noon.loc', 'suspended-marketer@marketer.com', 'suspended brand_ambassador'],
                ['delivery', 'delivery.noon.loc', 'mahmoud@delivery.com', 'platform agent (active)'],
                ['delivery', 'delivery.noon.loc', 'khalifa@delivery.com', 'platform agent (on_shift)'],
                ['delivery', 'delivery.noon.loc', 'aramex-rider1@delivery.com', 'third-party (Aramex Gulf)'],
                ['shipping_supervisor', 'carrier.noon.loc', 'tariq@aramex.com', 'supervisor — Aramex Gulf'],
                ['shipping_supervisor', 'carrier.noon.loc', 'salim@localexpress.om', 'supervisor — Local Express Oman'],
                ['shipping_supervisor', 'carrier.noon.loc', 'mona@cairoswift.com', 'supervisor — Cairo Swift (pending)'],
                ['travel_agency', 'travel-agency.noon.loc', 'info@gulfhorizons.com', 'Gulf Horizons Travel (active)'],
                ['travel_agency', 'travel-agency.noon.loc', 'info@nilestar.com', 'Nile Star Tourism (active)'],
                ['travel_agency', 'travel-agency.noon.loc', 'hello@riyadhwings.sa', 'Riyadh Wings Travel (pending)'],
            ]
        );
    }
}
