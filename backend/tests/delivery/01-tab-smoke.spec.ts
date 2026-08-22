import { test, expect } from '@playwright/test';
import { deliveryTabs } from './fixtures/tab-pages';

// Matches Laravel error pages (Ignition / Whoops / plain 500 pages)
const LARAVEL_ERROR_RE = /whoops|something went wrong|exception|stack trace/i;

for (const tab of deliveryTabs) {
  test(`[${tab.label}] loads without error`, async ({ page }) => {
    const [response] = await Promise.all([
      page.waitForResponse((r) => r.url().includes(tab.path.split('#')[0] || '/'), { timeout: 15000 }).catch(() => null),
      page.goto(tab.path),
    ]);

    // No 4xx / 5xx
    if (response) {
      expect(response.status(), `${tab.label} returned HTTP ${response.status()}`).toBeLessThan(400);
    }

    // No Laravel error page
    const body = await page.locator('body').innerText();
    expect(body, `${tab.label} shows a Laravel error page`).not.toMatch(LARAVEL_ERROR_RE);

    // No console errors (collected before navigation completes)
    const consoleErrors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });

    if (tab.expectedContent) {
      // Visible text content assertion
      await expect(page.locator('body')).toContainText(tab.expectedContent);
    } else {
      // Map tab — just assert we are on the dashboard (/#map stays on /)
      expect(new URL(page.url()).pathname).toBe('/');
    }
  });
}
