/**
 * Tab-bar navigation smoke tests.
 *
 * Starting from Home, taps each bottom tab in turn, asserts:
 *   1. The URL changes to the expected path (or stays on / for Map).
 *   2. The tapped tab link gains the CSS class `active` (renders as yellow #facc15).
 *
 * Active-tab class: `.nav-item.active` — confirmed from layouts/delivery.blade.php.
 * The navActive() PHP helper adds "active" when the route name prefix matches.
 * The Map tab links to /#map which keeps route name "delivery.dashboard", so the
 * Home tab stays active when Map is tapped — this is intentional and reflected below.
 */
import { test, expect } from '@playwright/test';

const tabs = [
  { label: 'Home',     path: '/',            navText: 'Home',     activeOnDashboard: true  },
  { label: 'Orders',   path: '/assignments', navText: 'Orders',   activeOnDashboard: false },
  { label: 'Earnings', path: '/earnings',    navText: 'Earnings', activeOnDashboard: false },
  { label: 'Profile',  path: '/profile',     navText: 'Profile',  activeOnDashboard: false },
] as const;

test.beforeEach(async ({ page }) => {
  await page.goto('/');
  // Wait for Alpine.js to boot (availability toggle is the earliest Alpine element)
  await expect(
    page.getByText("You're Offline").or(page.getByText("You're Online"))
  ).toBeVisible({ timeout: 10000 });
});

for (const tab of tabs) {
  test(`tapping ${tab.label} tab navigates correctly and marks it active`, async ({ page }) => {
    const nav = page.locator('#bottom-nav');

    // Click the tab by its visible label text inside the nav
    await nav.getByText(tab.navText, { exact: true }).click();
    await page.waitForLoadState('domcontentloaded');

    // URL check (Map tab stays on / with #map anchor)
    const pathname = new URL(page.url()).pathname;
    expect(pathname).toBe(tab.path);

    // Active class check — the clicked nav-item should have class "active"
    const navItem = nav.locator('a').filter({ hasText: tab.navText });
    await expect(navItem).toHaveClass(/\bactive\b/);
  });
}

test('Map tab links to dashboard anchor (stays on /)', async ({ page }) => {
  const nav = page.locator('#bottom-nav');
  await nav.getByText('Map', { exact: true }).click();
  await page.waitForLoadState('domcontentloaded');

  // Should stay on the dashboard — the Map tab is /#map
  expect(new URL(page.url()).pathname).toBe('/');

  // Home nav item remains active (route is still delivery.dashboard)
  const homeItem = nav.locator('a').filter({ hasText: 'Home' });
  await expect(homeItem).toHaveClass(/\bactive\b/);
});
