/**
 * Single source of truth for every admin sidebar page.
 * Derived from NavigationService::adminNavigation() and routes/admin.php.
 * Used by Phase 1 (smoke) and Phase 2 (CRUD) tests.
 *
 * Divergences from the original sidebar-spec prompt:
 *   - Dashboard URL is "/" not "/dashboard" (route: admin.dashboard)
 *   - Analytics is a real sidebar item (not in the original list)
 *   - Finance > Transactions exists between Payouts and Ledger
 *   - Finance paths are "/payouts", "/transactions", "/ledger" (not "/finance/*")
 *   - Applications is at "/vendors/applications" (not "/applications")
 *   - Pages is at "/page-builder" (not "/pages")
 *   - Marketer sub-pages use separate prefixes: /marketer-campaigns, etc.
 *   - Secret Promotions route prefix is "marketers-secret-promotions"
 *   - Settings is commented out of the sidebar nav but the route exists — included here
 *   - Profile (admin.profile.edit) has no route defined — marked skip
 */

export type SidebarPage = {
  section: string;
  label: string;
  /** Path relative to the admin domain root, e.g. "/products" */
  path: string;
  /** Text expected to appear somewhere on the page (case-insensitive substring match) */
  expectedHeading: string;
  /** Route name as registered in Laravel, for sidebar link lookup */
  routeName: string;
  /** If set, this page is skipped with this reason */
  skip?: string;
};

export const sidebarPages: SidebarPage[] = [
  // ── Overview ──────────────────────────────────────────────────────────────
  {
    section: 'Overview',
    label: 'Dashboard',
    path: '/',
    expectedHeading: 'Dashboard',
    routeName: 'admin.dashboard',
  },
  {
    section: 'Overview',
    label: 'Analytics',
    path: '/analytics',
    expectedHeading: 'Analytics',
    routeName: 'admin.analytics.index',
  },

  // ── Catalog ───────────────────────────────────────────────────────────────
  {
    section: 'Catalog',
    label: 'Products',
    path: '/products',
    expectedHeading: 'Products',
    routeName: 'admin.products.index',
  },
  {
    section: 'Catalog',
    label: 'Categories',
    path: '/categories',
    expectedHeading: 'Categories',
    routeName: 'admin.categories.index',
  },
  {
    section: 'Catalog',
    label: 'Brands',
    path: '/brands',
    expectedHeading: 'Brands',
    routeName: 'admin.brands.index',
  },
  {
    section: 'Catalog',
    label: 'Attributes',
    path: '/attributes',
    expectedHeading: 'Attributes',
    routeName: 'admin.attributes.index',
  },

  // ── Sales ─────────────────────────────────────────────────────────────────
  {
    section: 'Sales',
    label: 'Orders',
    path: '/orders',
    expectedHeading: 'Orders',
    routeName: 'admin.orders.index',
  },
  {
    section: 'Sales',
    label: 'Disputes',
    path: '/disputes',
    expectedHeading: 'Disputes',
    routeName: 'admin.disputes.index',
  },
  {
    section: 'Sales',
    label: 'Coupons',
    path: '/coupons',
    expectedHeading: 'Coupons',
    routeName: 'admin.coupons.index',
  },
  {
    section: 'Sales',
    label: 'Flash Sales',
    path: '/flash-sales',
    expectedHeading: 'Flash Sales',
    routeName: 'admin.flash-sales.index',
  },
  {
    section: 'Sales',
    label: 'Support Tickets',
    path: '/support-tickets',
    expectedHeading: 'Support Tickets',
    routeName: 'admin.support-tickets.index',
  },

  // ── Marketing ─────────────────────────────────────────────────────────────
  {
    section: 'Marketing',
    label: 'Banners',
    path: '/banners',
    expectedHeading: 'Banners',
    routeName: 'admin.banners.index',
  },
  {
    section: 'Marketing',
    label: 'Ad Campaigns',
    path: '/ad-campaigns',
    expectedHeading: 'Ad Campaigns',
    routeName: 'admin.ad-campaigns.index',
  },

  // ── Users ─────────────────────────────────────────────────────────────────
  {
    section: 'Users',
    label: 'Customers',
    path: '/customers',
    expectedHeading: 'Customers',
    routeName: 'admin.customers.index',
  },
  {
    section: 'Users',
    label: 'Vendors',
    path: '/vendors',
    expectedHeading: 'Vendors',
    routeName: 'admin.vendors.index',
  },
  {
    section: 'Users',
    label: 'Applications',
    // Real path: /vendors/applications (route: admin.vendors.applications)
    // NOT /applications as the original spec inferred
    path: '/vendors/applications',
    expectedHeading: 'Applications',
    routeName: 'admin.vendors.applications',
  },
  {
    section: 'Users',
    label: 'Admins',
    path: '/admins',
    expectedHeading: 'Admins',
    routeName: 'admin.admins.index',
  },
  {
    section: 'Users',
    label: 'Roles',
    path: '/roles',
    expectedHeading: 'Roles',
    routeName: 'admin.roles.index',
  },

  // ── Marketers ─────────────────────────────────────────────────────────────
  {
    section: 'Marketers',
    label: 'All Marketers',
    path: '/marketers',
    expectedHeading: 'Marketers',
    routeName: 'admin.marketers.all.index',
  },
  {
    section: 'Marketers',
    label: 'Campaigns',
    // Real prefix is /marketer-campaigns (NOT /marketers/campaigns)
    path: '/marketer-campaigns',
    expectedHeading: 'Campaigns',
    routeName: 'admin.marketers.campaigns.index',
  },
  {
    section: 'Marketers',
    label: 'Conversions',
    path: '/marketer-conversions',
    expectedHeading: 'Conversions',
    routeName: 'admin.marketers.conversions.index',
  },
  {
    section: 'Marketers',
    label: 'Payouts',
    path: '/marketer-payouts',
    expectedHeading: 'Payouts',
    routeName: 'admin.marketers.payouts.index',
  },
  {
    section: 'Marketers',
    label: 'Sample Requests',
    path: '/marketer-samples',
    expectedHeading: 'Sample',
    routeName: 'admin.marketers.samples.index',
  },
  {
    section: 'Marketers',
    label: 'Secret Promotions',
    // Real prefix: /marketers-secret-promotions (NOT /marketers/secret-promotions)
    path: '/marketers-secret-promotions',
    expectedHeading: 'Secret Promotions',
    routeName: 'admin.secret-promotions.index',
  },

  // ── Finance ───────────────────────────────────────────────────────────────
  {
    section: 'Finance',
    label: 'Payouts',
    // Real path: /payouts (NOT /finance/payouts)
    path: '/payouts',
    expectedHeading: 'Payouts',
    routeName: 'admin.payouts.index',
  },
  {
    section: 'Finance',
    label: 'Transactions',
    // Extra sidebar item not in the original spec
    path: '/transactions',
    expectedHeading: 'Transactions',
    routeName: 'admin.transactions.index',
  },
  {
    section: 'Finance',
    label: 'Ledger',
    // Real path: /ledger (NOT /finance/ledger)
    path: '/ledger',
    expectedHeading: 'Ledger',
    routeName: 'admin.ledger.index',
  },

  // ── Content ───────────────────────────────────────────────────────────────
  {
    section: 'Content',
    label: 'Pages',
    // Real path: /page-builder (NOT /pages)
    path: '/page-builder',
    expectedHeading: 'Page',
    routeName: 'admin.page-builder.index',
  },
  {
    section: 'Content',
    label: 'Reviews',
    path: '/reviews',
    expectedHeading: 'Reviews',
    routeName: 'admin.reviews.index',
  },

  // ── System ────────────────────────────────────────────────────────────────
  {
    section: 'System',
    label: 'Countries',
    path: '/countries',
    expectedHeading: 'Countries',
    routeName: 'admin.countries.index',
  },
  {
    section: 'System',
    label: 'Cities',
    path: '/cities',
    expectedHeading: 'Cities',
    routeName: 'admin.cities.index',
  },
  {
    section: 'System',
    label: 'Currencies',
    path: '/currencies',
    expectedHeading: 'Currencies',
    routeName: 'admin.currencies.index',
  },
  {
    section: 'System',
    label: 'Shipping Zones',
    path: '/shipping-zones',
    expectedHeading: 'Shipping Zones',
    routeName: 'admin.shipping-zones.index',
  },
  {
    section: 'System',
    label: 'Shipping Methods',
    path: '/shipping-methods',
    expectedHeading: 'Shipping Methods',
    routeName: 'admin.shipping-methods.index',
  },
  {
    section: 'System',
    label: 'Payment Methods',
    path: '/payment-methods',
    expectedHeading: 'Payment Methods',
    routeName: 'admin.payment-methods.index',
  },
  {
    section: 'System',
    label: 'Warehouses',
    path: '/warehouses',
    expectedHeading: 'Warehouses',
    routeName: 'admin.warehouses.index',
  },
  {
    section: 'System',
    label: 'Inventory Transfers',
    // Real path: /warehouses/transfers (nested under warehouses prefix)
    path: '/warehouses/transfers',
    expectedHeading: 'Transfers',
    routeName: 'admin.warehouses.transfers.index',
  },
  {
    section: 'System',
    label: 'Activity Log',
    path: '/activity-log',
    expectedHeading: 'Activity',
    routeName: 'admin.activity-log.index',
  },

  // ── Account ───────────────────────────────────────────────────────────────
  {
    section: 'Account',
    label: 'Settings',
    // Route exists (admin.settings.index) but is commented out of the sidebar nav.
    // Still testing the page itself loads correctly.
    path: '/settings',
    expectedHeading: 'Settings',
    routeName: 'admin.settings.index',
  },
  {
    section: 'Account',
    label: 'Profile',
    path: '/profile',
    expectedHeading: 'Profile',
    routeName: 'admin.profile.edit',
    // No route registered for admin.profile.edit in routes/admin.php
    skip: 'admin.profile.edit route is not defined in routes/admin.php',
  },
];

/** Pages that have a sidebar link (excludes Settings which is commented out and Profile which has no route) */
export const linkedSidebarPages = sidebarPages.filter(
  (p) => !p.skip && p.routeName !== 'admin.settings.index'
);
