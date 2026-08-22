/**
 * Single source of truth for every vendor (partner) sidebar page.
 * Derived from sidebar.blade.php and routes/partner.php.
 * Used by Phase 1 (smoke) and Phase 2 (CRUD) tests.
 *
 * Divergences from the original sidebar-spec prompt:
 *   - Dashboard URL is "/dashboard" (not "/" — root redirects to /dashboard)
 *   - نظام التوصيل maps to /fulfillment (not /delivery — route: partner.fulfillment.index)
 *   - اشتراكاتي maps to /subscription (not /subscriptions — singular prefix in routes)
 *   - طلبات العينات maps to /marketer-samples (not /sample-requests)
 *   - التحويلات maps to /warehouses/transfers (nested under warehouses prefix)
 *   - Payouts is at /payouts (not /finance/payouts)
 *   - المرتجعات (Returns) — sidebar link exists but partner.returns.index has no route — SKIP
 *   - المعاملات (Transactions) — sidebar link partner.finance.transactions has no route — SKIP
 *   - الإعلانات (Ads) — sidebar link partner.ads.index has no route — SKIP
 */

export type VendorSidebarPage = {
  section: string;
  label: string;
  /** Path relative to the partner domain root, e.g. "/orders" */
  path: string;
  /** Regex matched against visible text on the page (case-insensitive) */
  expectedHeading: RegExp;
  /** Laravel route name as registered in routes/partner.php */
  routeName: string;
  /**
   * If the route redirects, the final URL pathname the browser lands on.
   * Used by the navigation test instead of `path` to verify the correct destination.
   */
  finalPath?: string;
  /** If set, the test is skipped with this reason */
  skip?: string;
};

export const vendorSidebarPages: VendorSidebarPage[] = [
  // ── Overview ──────────────────────────────────────────────────────────────
  {
    section: 'الرئيسية',
    label: 'الرئيسية',
    path: '/dashboard',
    expectedHeading: /dashboard|الرئيسية|لوحة/i,
    routeName: 'partner.dashboard',
  },

  // ── الطلبات (Orders) ──────────────────────────────────────────────────────
  {
    section: 'الطلبات',
    label: 'طلباتي',
    path: '/orders',
    expectedHeading: /طلب/i,
    routeName: 'partner.orders.index',
  },
  {
    section: 'الطلبات',
    label: 'المرتجعات',
    path: '/returns',
    expectedHeading: /مرتجع/i,
    routeName: 'partner.returns.index',
    skip: 'partner.returns.index route is not defined in routes/partner.php',
  },

  // ── الكتالوج (Catalog) ────────────────────────────────────────────────────
  {
    section: 'الكتالوج',
    label: 'قوائم المنتجات',
    path: '/listings',
    expectedHeading: /listing|قائمة|منتج/i,
    routeName: 'partner.listings.index',
  },
  {
    section: 'الكتالوج',
    label: 'المخزون',
    path: '/inventory',
    expectedHeading: /inventor|مخزون/i,
    routeName: 'partner.inventory.index',
  },

  // ── التوصيل والتخزين (Delivery & Storage) ────────────────────────────────
  {
    section: 'التوصيل والتخزين',
    label: 'نظام التوصيل',
    // Sidebar uses partner.fulfillment.index → /fulfillment (not /delivery)
    path: '/fulfillment',
    expectedHeading: /fulfillment|توصيل/i,
    routeName: 'partner.fulfillment.index',
  },
  {
    section: 'التوصيل والتخزين',
    label: 'مستودعاتي',
    path: '/warehouses',
    expectedHeading: /warehouse|مستودع/i,
    routeName: 'partner.warehouses.index',
  },
  {
    section: 'التوصيل والتخزين',
    label: 'التحويلات',
    // Nested under warehouses prefix: /warehouses/transfers
    path: '/warehouses/transfers',
    expectedHeading: /transfer|تحويل/i,
    routeName: 'partner.warehouses.transfers.index',
  },

  // ── المالية (Finance) ─────────────────────────────────────────────────────
  {
    section: 'المالية',
    label: 'المدفوعات',
    // Real path: /payouts (not /finance/payouts)
    path: '/payouts',
    expectedHeading: /payout|مدفوع/i,
    routeName: 'partner.payouts.index',
  },
  {
    section: 'المالية',
    label: 'المعاملات',
    path: '/finance/transactions',
    expectedHeading: /transaction|معاملة/i,
    routeName: 'partner.finance.transactions',
    skip: 'partner.finance.transactions route is not defined in routes/partner.php',
  },
  {
    section: 'المالية',
    label: 'اشتراكاتي',
    // Route prefix is "subscription" (singular), not "subscriptions"
    path: '/subscription',
    expectedHeading: /subscri|اشتراك/i,
    routeName: 'partner.subscription.index',
  },

  // ── التسويق (Marketing) ───────────────────────────────────────────────────
  {
    section: 'التسويق',
    label: 'التخفيضات السريعة',
    path: '/flash-sales',
    expectedHeading: /flash.sale|تخفيض/i,
    routeName: 'partner.flash-sales.index',
  },
  {
    section: 'التسويق',
    label: 'الإعلانات',
    path: '/ads',
    expectedHeading: /ad|إعلان/i,
    routeName: 'partner.ads.index',
    skip: 'partner.ads.index route is not defined in routes/partner.php',
  },
  {
    section: 'التسويق',
    label: 'حملات المسوّقين',
    path: '/marketer-campaigns',
    expectedHeading: /campaign|حملات/i,
    routeName: 'partner.marketer-campaigns.index',
  },
  {
    section: 'التسويق',
    label: 'طلبات العينات',
    // Real prefix: /marketer-samples (not /sample-requests)
    path: '/marketer-samples',
    expectedHeading: /sample|عينا/i,
    routeName: 'partner.marketer-samples.index',
  },
  {
    section: 'التسويق',
    label: 'الأداء',
    path: '/performance',
    expectedHeading: /performance|أداء/i,
    routeName: 'partner.performance.index',
  },
  {
    section: 'التسويق',
    label: 'الدعم الفني',
    path: '/support/tickets',
    expectedHeading: /support|ticket|دعم/i,
    routeName: 'partner.support.tickets.index',
  },

  // ── الإعدادات (Settings) ──────────────────────────────────────────────────
  {
    section: 'الإعدادات',
    label: 'الملف الشخصي',
    path: '/profile',
    expectedHeading: /profile|ملف/i,
    routeName: 'partner.profile.index',
  },
  {
    section: 'الإعدادات',
    label: 'الفريق',
    path: '/team',
    expectedHeading: /team|فريق/i,
    routeName: 'partner.team.index',
  },
  {
    section: 'الإعدادات',
    label: 'الحسابات البنكية',
    path: '/bank-accounts',
    expectedHeading: /bank|حساب/i,
    routeName: 'partner.bank-accounts.index',
  },
  {
    section: 'الإعدادات',
    label: 'المستندات',
    path: '/documents',
    expectedHeading: /document|مستند/i,
    routeName: 'partner.documents.index',
    // documentsIndex() redirects to /profile?tab=documents — the profile page hosts this tab
    finalPath: '/profile',
  },
];

/** Pages with an active sidebar link and a real route (excludes skipped pages) */
export const linkedVendorSidebarPages = vendorSidebarPages.filter((p) => !p.skip);
