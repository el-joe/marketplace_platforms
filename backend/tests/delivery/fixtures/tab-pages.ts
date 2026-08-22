/**
 * Delivery bottom-tab smoke-test fixtures.
 *
 * MAP NOTE: The Map tab links to /#map (an anchor on the dashboard page, not a
 * separate route).  There is no dedicated map library — the tab just scrolls
 * the dashboard.  expectedContent is null so the smoke test skips text
 * assertion and instead verifies the page stays on the dashboard URL.
 */
export const deliveryTabs = [
  {
    label: 'Orders',
    path: '/assignments',
    expectedContent: /today's orders|orders|assignments/i,
  },
  {
    label: 'Map',
    path: '/#map',
    expectedContent: null, // no separate page — anchor on dashboard
  },
  {
    label: 'Earnings',
    path: '/earnings',
    expectedContent: /earnings|pending|paid/i,
  },
  {
    label: 'Profile',
    path: '/profile',
    expectedContent: /profile/i,
  },
] as const;
