/**
 * Phase 1 — Carrier Nav Navigation Tests
 *
 * Click-through navigation from dashboard for all 3 nav items.
 * Asserts correct URL and active-nav-item state after each click.
 *
 * Active-state indicator (from layouts/carrier.blade.php):
 *   The active link gets class "text-indigo-600 font-bold"
 *   (the inactive links are "text-gray-600").
 *
 * Also smoke-tests the "عرض الكل" (View All) link on the Recent Orders
 * panel — confirmed in dashboard.blade.php to navigate to /assignments.
 */

import { test, expect } from '@playwright/test';
import { carrierNavPages } from './fixtures/nav-pages';
import { assertNoErrorPage } from '../helpers/smoke';

test.describe('Carrier Nav — click-through navigation', () => {
  for (const entry of carrierNavPages) {
    const testTitle = `clicking "${entry.label}" navigates to ${entry.path} and marks nav active`;

    if (entry.skip) {
      test(testTitle, async () => {
        test.skip(true, entry.skip!);
      });
      continue;
    }

    test(testTitle, async ({ page }) => {
      // Start from the dashboard
      await page.goto('/', { waitUntil: 'domcontentloaded' });
      await assertNoErrorPage(page);

      // Click the nav link by its exact Arabic label
      await page.getByRole('link', { name: entry.label }).click();
      await page.waitForURL(`**${entry.path}`, { timeout: 10_000 });

      // Assert URL
      expect(page.url()).toContain(entry.path);

      // Assert no error page after navigation
      await assertNoErrorPage(page);

      // Assert active-nav indicator: the clicked link should have text-indigo-600
      // (the blade template adds "text-indigo-600 font-bold" to the active link)
      const activeLink = page.getByRole('link', { name: entry.label });
      await expect(activeLink).toHaveClass(/text-indigo-600/);
    });
  }
});

test('"عرض الكل" on dashboard navigates to /assignments', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await assertNoErrorPage(page);

  // The "عرض الكل" link routes to carrier.assignments.index (/assignments)
  const viewAllLink = page.getByRole('link', { name: 'عرض الكل' });
  await expect(viewAllLink).toBeVisible({ timeout: 5_000 });

  await viewAllLink.click();
  await page.waitForURL('**/assignments', { timeout: 10_000 });

  expect(page.url()).toContain('/assignments');
  await assertNoErrorPage(page);

  // The assignments page heading should be visible
  await expect(page.getByText(/طلبات التوصيل/).first()).toBeVisible({
    timeout: 10_000,
  });
});
