/**
 * Single source of truth for carrier portal nav pages.
 * Derived from layouts/carrier.blade.php and routes/carrier.php.
 *
 * Dashboard (/) is covered by 00-smoke-login — excluded here.
 *
 * Permission notes (from blade layout):
 *   المناديب    — always visible (no permission gate)
 *   الطلبات    — gated on view_orders permission
 *   المشرفون   — gated on manage_agents permission
 *
 * The test user (CARRIER_TEST_EMAIL) is assumed to be a company owner
 * with both permissions. If tests for المشرفون / الطلبات are skipped
 * by the runner, verify the test account's permissions.
 */

export type CarrierNavPage = {
  label: string;
  /** Path relative to the carrier domain root */
  path: string;
  /** Regex matched against visible text on the page */
  expectedHeading: RegExp;
  /** Laravel route name */
  routeName: string;
  /** If set, the test is skipped with this reason */
  skip?: string;
};

export const carrierNavPages: CarrierNavPage[] = [
  {
    label: 'المناديب',
    path: '/agents',
    expectedHeading: /المناديب/,
    routeName: 'carrier.agents.index',
  },
  {
    label: 'الطلبات',
    path: '/assignments',
    expectedHeading: /طلبات التوصيل/,
    routeName: 'carrier.assignments.index',
  },
  {
    label: 'المشرفون',
    path: '/supervisors',
    expectedHeading: /المشرفون/,
    routeName: 'carrier.supervisors.index',
  },
];
