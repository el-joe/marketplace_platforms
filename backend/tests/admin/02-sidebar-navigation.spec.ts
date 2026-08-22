/**
 * Phase 1 — Sidebar Navigation Tests
 *
 * Verifies that clicking each sidebar link from the dashboard actually
 * navigates to the correct URL. A broken href or broken JS intercept
 * shows up here without conflating it with page-load failures
 * (which are caught separately in 01-sidebar-smoke.spec.ts).
 *
 * Strategy:
 *   - Start each test at the dashboard
 *   - Click the sidebar link whose href contains the expected path
 *   - Assert the resulting URL matches
 *
 * Only pages that have an active sidebar link are tested here.
 * Settings (commented out of the nav) and Profile (no route) are excluded.
 */

import { test, expect } from '@playwright/test';
import { linkedSidebarPages } from './fixtures/sidebar-pages';

test.describe('Sidebar Navigation — every link goes to the right URL', () => {
  for (const entry of linkedSidebarPages) {
    test(`[${entry.section}] "${entry.label}" link navigates to ${entry.path}`, async ({ page }) => {
      // Start at dashboard
      await page.goto('/', { waitUntil: 'domcontentloaded' });

      // The sidebar renders links as <a class="nav-item" href="...">
      // We locate by href to be resilient to label text differences.
      // For the root dashboard path ("/") we look for href ending in "/" or equal to origin.
      let linkLocator;
      if (entry.path === '/') {
        // Dashboard: href is exactly the base URL or ends with "/"
        linkLocator = page
          .locator('a.nav-item')
          .filter({ hasText: /dashboard/i })
          .first();
      } else {
        linkLocator = page
          .locator(`a.nav-item[href*="${entry.path}"]`)
          .first();
      }

      // Scroll into view in case the sidebar needs expanding
      await linkLocator.scrollIntoViewIfNeeded();

      // Click and wait for navigation
      await Promise.all([
        page.waitForURL((url) => {
          const pathname = new URL(url).pathname;
          // Exact match or the path is a prefix (for nested routes)
          return pathname === entry.path || pathname.startsWith(entry.path + '/');
        }, { timeout: 10_000 }),
        linkLocator.click(),
      ]);

      const finalPath = new URL(page.url()).pathname;
      expect(
        finalPath,
        `Expected navigation to "${entry.path}" but ended up at "${finalPath}"`
      ).toBe(entry.path);
    });
  }
});
