/**
 * Phase 1 — Sidebar Smoke Tests
 *
 * For every sidebar page: navigate directly, assert no error page,
 * assert HTTP status is not 4xx/5xx, assert the expected heading is visible,
 * and assert no uncaught JS console errors.
 *
 * CRUD actions are NOT tested here — see Phase 2.
 */

import { test, expect, type ConsoleMessage } from '@playwright/test';
import { sidebarPages } from './fixtures/sidebar-pages';
import { assertNoErrorPage, isAssetUrl } from '../helpers/smoke';

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('Sidebar Smoke — every page loads without errors', () => {
  for (const entry of sidebarPages) {
    const testTitle = `[${entry.section}] ${entry.label} → ${entry.path}`;

    if (entry.skip) {
      test.skip(true && false, entry.skip); // placeholder; real skip below
      test(testTitle, async () => {
        test.skip(true, entry.skip!);
      });
      continue;
    }

    test(testTitle, async ({ page }) => {
      // Track JS-level console errors (TypeError, unhandled promise rejections, etc.)
      const consoleErrors: string[] = [];
      page.on('console', (msg: ConsoleMessage) => {
        if (msg.type() === 'error') {
          consoleErrors.push(`[console.error] ${msg.text()}`);
        }
      });

      // Track API-level 4xx/5xx responses on non-asset URLs.
      // "Failed to load resource" console errors from missing seed images are
      // excluded here — those are a data gap, not a code bug.
      const apiFailures: string[] = [];
      page.on('response', (response) => {
        const url = response.url();
        const status = response.status();
        if (status >= 400 && !isAssetUrl(url)) {
          apiFailures.push(`${status} ${url}`);
        }
      });

      // Navigate and capture the main document response
      const response = await page.goto(entry.path, { waitUntil: 'domcontentloaded' });

      // 1. HTTP status check on the main document
      const status = response?.status() ?? 0;
      expect(
        status,
        `Expected a non-error HTTP status for "${entry.path}", got ${status}`
      ).toBeLessThan(400);

      // 2. No Laravel error page
      await assertNoErrorPage(page);

      // 3. Expected heading/text is visible (case-insensitive)
      const headingLocator = page.getByText(new RegExp(entry.expectedHeading, 'i')).first();
      await expect(headingLocator).toBeVisible({ timeout: 10_000 });

      // 4. Allow deferred JS to fire, then check for both JS errors and API failures
      await page.waitForTimeout(800);

      // Filter out "Failed to load resource" console errors — those are browser-generated
      // messages for network failures (missing images, etc.) and are reported separately
      // via the response listener above.
      const jsErrors = consoleErrors.filter(
        (e) => !e.includes('Failed to load resource')
      );

      const problems: string[] = [
        ...jsErrors,
        ...apiFailures.map((f) => `[api-failure] ${f}`),
      ];

      if (problems.length > 0) {
        throw new Error(
          `Problems on "${entry.path}":\n${problems.join('\n')}`
        );
      }
    });
  }
});
