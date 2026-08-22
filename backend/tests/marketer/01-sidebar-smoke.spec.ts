/**
 * Phase 1 — Marketer Sidebar Smoke Tests
 *
 * For every sidebar page: navigate directly, assert no Laravel error page,
 * assert HTTP status is not 4xx/5xx, assert the expected heading is visible,
 * and assert no uncaught JS console errors.
 *
 * Also smoke-tests the "Copy Link" button on the dashboard: clicks it, reads
 * the clipboard, and asserts the value matches the displayed referral URL.
 *
 * CRUD actions are NOT tested here — see Phase 2.
 */

import { test, expect, type ConsoleMessage } from '@playwright/test';
import { marketerSidebarPages } from './fixtures/sidebar-pages';
import { assertNoErrorPage, isAssetUrl } from '../helpers/smoke';

test.describe('Marketer Sidebar Smoke — every page loads without errors', () => {
  for (const entry of marketerSidebarPages) {
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

      // 3. Expected heading/text is visible
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

test('"Copy Link" button on dashboard copies the referral URL to clipboard', async ({
  page,
}) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  // Read the displayed referral URL before clicking
  const displayedUrl = await page.locator('#global-ref-url').innerText();
  expect(displayedUrl.trim()).toMatch(/https?:\/\/.+\?ref=MKT-[A-Z0-9]+/);

  // The page runs on HTTP so navigator.clipboard may be undefined.
  // Intercept writeText before the click to capture what gets written.
  await page.evaluate(() => {
    (window as any).__clipboardCapture = undefined;
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: {
        writeText: async (text: string) => {
          (window as any).__clipboardCapture = text;
        },
      },
    });
  });

  await page.getByRole('button', { name: /copy link/i }).click();

  // Small wait for the async writeText promise to resolve
  await page.waitForTimeout(300);

  const captured = await page.evaluate(() => (window as any).__clipboardCapture);
  expect(captured?.trim()).toBe(displayedUrl.trim());
});
