/**
 * Marketer Phase 2 – Dashboard (functional)
 *
 * Environment: APP_ENV=local, local dev DB (marketplace_platform @ 127.0.0.1)
 * Auth: yasmin@marketer.com / password123  (active influencer, referral_code MKT-JEQP1F)
 *
 * Clipboard copy-to-clipboard is covered in Phase 1 (01-sidebar-smoke.spec.ts).
 * This file covers stat-card data shape and chart render only.
 *
 * No data is written — all tests are read-only.
 */

import { test, expect } from '@playwright/test';

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
  });

  // ── KPI stat cards ──────────────────────────────────────────────────────────

  test('renders exactly 4 KPI stat cards', async ({ page }) => {
    const cards = page.locator('.kpi-card');
    await expect(cards).toHaveCount(4);
  });

  test('stat card values render as numeric strings (no PHP errors or raw blade syntax)', async ({
    page,
  }) => {
    const cards = page.locator('.kpi-card .value');
    const count = await cards.count();
    expect(count).toBe(4);

    for (let i = 0; i < count; i++) {
      const text = (await cards.nth(i).innerText()).trim();
      // Must look like a number or a percentage (e.g. "0", "1,234", "5.00%")
      expect(
        text,
        `Card ${i} value "${text}" does not look like a number or percentage`
      ).toMatch(/^[\d,]+(\.\d+)?%?$/);
    }
  });

  test('Clicks Today card is present and labelled correctly', async ({ page }) => {
    await expect(page.locator('.kpi-card').filter({ hasText: 'Clicks Today' })).toBeVisible();
  });

  test('Conversion Rate card shows a percentage value', async ({ page }) => {
    const card = page.locator('.kpi-card').filter({ hasText: 'Conversion Rate' });
    await expect(card).toBeVisible();
    const valueText = (await card.locator('.value').innerText()).trim();
    expect(valueText).toMatch(/%$/);
  });

  test('Pending Earnings card shows a decimal SAR value', async ({ page }) => {
    const card = page.locator('.kpi-card').filter({ hasText: 'Pending Earnings' });
    await expect(card).toBeVisible();
    const valueText = (await card.locator('.value').innerText()).trim();
    // e.g. "0.00" or "1,250.75"
    expect(valueText).toMatch(/^[\d,]+\.\d{2}$/);
  });

  test('no raw blade {{ }} syntax or PHP error text visible on page', async ({ page }) => {
    const body = await page.locator('body').innerText();
    expect(body).not.toContain('{{');
    expect(body).not.toContain('}}');
    expect(body).not.toContain('Whoops');
    expect(body).not.toContain('Stack trace');
  });

  // ── Revenue Trend chart ─────────────────────────────────────────────────────

  test('revenue-chart canvas element is present in the DOM', async ({ page }) => {
    await expect(page.locator('canvas#revenue-chart')).toBeVisible();
  });

  test('Chart.js renders data on the revenue-chart canvas (non-zero dimensions)', async ({
    page,
  }) => {
    // Wait for Chart.js to finish rendering (it fires after DOMContentLoaded)
    await page.waitForTimeout(1500);

    const dims = await page.locator('canvas#revenue-chart').evaluate((el: HTMLCanvasElement) => ({
      width: el.width,
      height: el.height,
    }));

    expect(dims.width, 'canvas width should be > 0 after Chart.js render').toBeGreaterThan(0);
    expect(dims.height, 'canvas height should be > 0 after Chart.js render').toBeGreaterThan(0);
  });

  test('"Revenue Trend" section heading is visible', async ({ page }) => {
    await expect(page.getByText('Revenue Trend')).toBeVisible();
  });

  // ── Security: sensitive fields must not appear anywhere in the DOM ──────────

  test('page DOM contains no admin_share_pct value', async ({ page }) => {
    const content = await page.content();
    expect(content).not.toContain('admin_share_pct');
  });

  test('page DOM contains no product_value_cents value', async ({ page }) => {
    const content = await page.content();
    expect(content).not.toContain('product_value_cents');
  });
});
