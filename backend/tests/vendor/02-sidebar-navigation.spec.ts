/**
 * Phase 1 — Vendor Sidebar Navigation Tests
 *
 * Verifies that clicking each sidebar link from the dashboard actually
 * navigates to the correct URL. A broken href shows up here without
 * conflating it with page-load failures (caught in 01-sidebar-smoke.spec.ts).
 *
 * Strategy:
 *   - Start each test at the dashboard
 *   - Click the sidebar <a> whose href ends with the expected path
 *   - Assert the resulting URL matches
 *
 * Only pages with an active sidebar link and a real route are tested.
 * Skipped pages (المرتجعات, المعاملات, الإعلانات) are excluded.
 *
 * RTL note: the sidebar is visually on the right but DOM order is unchanged,
 * so href-based locators work without special RTL handling.
 */

import { test, expect } from '@playwright/test';
import { linkedVendorSidebarPages } from './fixtures/sidebar-pages';

test.describe('Vendor Sidebar Navigation — every link goes to the right URL', () => {
  for (const entry of linkedVendorSidebarPages) {
    test(`[${entry.section}] "${entry.label}" link navigates to ${entry.path}`, async ({ page }) => {
      await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

      // Locate the sidebar link by its href ending with the expected path.
      // Scoped to <aside> to avoid false matches elsewhere on the page.
      // Uses CSS attribute ends-with ($=) so /warehouses doesn't match
      // /warehouses/transfers and vice versa.
      const linkLocator = page
        .locator(`aside a[href$="${entry.path}"]`)
        .first();

      await linkLocator.scrollIntoViewIfNeeded();

      const expectedPath = entry.finalPath ?? entry.path;

      await Promise.all([
        page.waitForURL(
          (url) => {
            const pathname = new URL(url).pathname;
            return pathname === expectedPath || pathname.startsWith(expectedPath + '/');
          },
          { timeout: 10_000 }
        ),
        linkLocator.click(),
      ]);

      const finalPath = new URL(page.url()).pathname;
      expect(
        finalPath,
        `Expected navigation to "${expectedPath}" but ended up at "${finalPath}"`
      ).toBe(expectedPath);
    });
  }
});
