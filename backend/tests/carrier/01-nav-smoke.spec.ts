/**
 * Phase 1 — Carrier Nav Smoke Tests
 *
 * For every nav page (excluding dashboard, which is covered by 00-smoke-login):
 * navigate directly, assert no Laravel error page, assert HTTP status < 400,
 * assert the expected heading is visible, and assert no uncaught JS console errors.
 */

import { test, expect, type ConsoleMessage } from '@playwright/test';
import { carrierNavPages } from './fixtures/nav-pages';
import { assertNoErrorPage, isAssetUrl } from '../helpers/smoke';

test.describe('Carrier Nav Smoke — every page loads without errors', () => {
  for (const entry of carrierNavPages) {
    const testTitle = `"${entry.label}" → ${entry.path}`;

    if (entry.skip) {
      test(testTitle, async () => {
        test.skip(true, entry.skip!);
      });
      continue;
    }

    test(testTitle, async ({ page }) => {
      const consoleErrors: string[] = [];
      page.on('console', (msg: ConsoleMessage) => {
        if (msg.type() === 'error') {
          consoleErrors.push(`[console.error] ${msg.text()}`);
        }
      });

      const apiFailures: string[] = [];
      page.on('response', (response) => {
        const url = response.url();
        const status = response.status();
        if (status >= 400 && !isAssetUrl(url)) {
          apiFailures.push(`${status} ${url}`);
        }
      });

      const response = await page.goto(entry.path, { waitUntil: 'domcontentloaded' });

      // 1. HTTP status on the main document
      const status = response?.status() ?? 0;
      expect(
        status,
        `Expected a non-error HTTP status for "${entry.path}", got ${status}`
      ).toBeLessThan(400);

      // 2. No Laravel error page
      await assertNoErrorPage(page);

      // 3. Expected heading is visible
      const headingLocator = page.getByText(entry.expectedHeading).first();
      await expect(headingLocator).toBeVisible({ timeout: 10_000 });

      // 4. Allow deferred JS to fire, then check for errors
      await page.waitForTimeout(800);

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
