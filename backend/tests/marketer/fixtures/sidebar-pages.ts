/**
 * Single source of truth for the marketer portal sidebar pages.
 * Derived from layouts/marketer.blade.php and routes/marketer.php.
 *
 * The sidebar has exactly 3 destinations:
 *   Dashboard    → /          (marketer.dashboard)
 *   My Campaigns → /campaigns (marketer.campaigns.index)
 *   Earnings     → /earnings  (marketer.earnings.index)
 */

export type MarketerSidebarPage = {
  label: string;
  /** Path relative to the marketer domain root */
  path: string;
  /** Regex matched against visible text on the page */
  expectedHeading: RegExp;
  /** Laravel route name */
  routeName: string;
  /** If set, the test is skipped with this reason */
  skip?: string;
};

export const marketerSidebarPages: MarketerSidebarPage[] = [
  {
    label: 'Dashboard',
    path: '/',
    expectedHeading: /dashboard/i,
    routeName: 'marketer.dashboard',
  },
  {
    label: 'My Campaigns',
    path: '/campaigns',
    expectedHeading: /campaigns/i,
    routeName: 'marketer.campaigns.index',
  },
  {
    label: 'Earnings',
    path: '/earnings',
    expectedHeading: /earnings/i,
    routeName: 'marketer.earnings.index',
  },
];
