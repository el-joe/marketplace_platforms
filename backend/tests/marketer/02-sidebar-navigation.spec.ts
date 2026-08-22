/**
 * Phase 1 — Marketer Sidebar Navigation Tests
 *
 * Verifies that clicking each sidebar link from the dashboard actually
 * navigates to the correct URL. A broken href shows up here without
 * conflating it with page-load failures (caught in 01-sidebar-smoke.spec.ts).
 *
 * Strategy:
 *   - Start each test at the dashboard (/)
 *   - Click the sidebar <a> whose href ends with the expected path
 *   - Assert the resulting URL matches
 */

import { test, expect } from '@playwright/test';
import { marketerSidebarPages } from './fixtures/sidebar-pages';

test.describe('Marketer Sidebar Navigation — every link goes to the right URL', () => {
  for (const entry of marketerSidebarPages) {
    if (entry.skip) {
      test(`"${entry.label}" link navigates to ${entry.path}`, async () => {
        test.skip(true, entry.skip!);
      });
      continue;
    }

    test(`"${entry.label}" link navigates to ${entry.path}`, async ({ page }) => {
      await page.goto('/', { waitUntil: 'domcontentloaded' });

      // Locate the sidebar link by its href ending with the path.
      // Laravel's route() helper generates full URLs, so we use $= (ends-with).
      // For the dashboard ("/"), the generated href may have no trailing slash
      // (e.g. "http://marketer.noon.loc"), so fall back to locating by text.
      const linkLocator =
        entry.path === '/'
          ? page.locator('aside a', { hasText: entry.label }).first()
          : page.locator(`aside a[href$="${entry.path}"]`).first();

      await linkLocator.scrollIntoViewIfNeeded();

      await Promise.all([
        page.waitForURL(
          (url) => {
            const pathname = new URL(url).pathname;
            return pathname === entry.path || pathname.startsWith(entry.path + '/');
          },
          { timeout: 10_000 }
        ),
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
