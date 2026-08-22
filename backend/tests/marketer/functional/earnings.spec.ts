/**
 * Marketer Phase 2 – My Earnings (functional)
 *
 * Environment: APP_ENV=local, local dev DB (marketplace_platform @ 127.0.0.1)
 * Auth: yasmin@marketer.com / password123
 *
 * VERIFIED page structure (earnings/index.blade.php):
 *   - 3 summary cards: Pending | Approved | Paid Out  (all SAR amounts)
 *   - 3 Alpine.js tabs: Pending | Approved | Payout History
 *   - Pending/Approved tabs show: Campaign | Order Value | Commission | Date
 *   - Payout History tab shows:  Payout # | Period | Conv. | Net Amount | Status
 *
 * ORDER REFERENCE check (flag raised and resolved):
 *   The earnings page does NOT show an order number or order ID anywhere in the DOM.
 *   Columns are: campaign name, order_value_cents/100 (labelled "Order Value"), commission, date.
 *   There is no "order reference" or "order #" column — so no masking issue exists on this page.
 *   This observation is documented here so future changes don't inadvertently add a raw order ID.
 *
 * No data is written — all tests are read-only.
 */

import { test, expect, type Page } from '@playwright/test';

const EARNINGS_URL = '/earnings';

async function goToEarnings(page: Page) {
  await page.goto(EARNINGS_URL, { waitUntil: 'domcontentloaded' });
  await expect(page).toHaveURL(new RegExp('/earnings'));
}

// ── Summary cards ─────────────────────────────────────────────────────────────

test.describe('Earnings – summary cards', () => {
  test('renders 3 summary cards: Pending, Approved, Paid Out', async ({ page }) => {
    await goToEarnings(page);

    await expect(page.getByText('Pending').first()).toBeVisible();
    await expect(page.getByText('Approved').first()).toBeVisible();
    await expect(page.getByText('Paid Out')).toBeVisible();
  });

  test('summary card amounts render as decimal numbers (not raw blade syntax)', async ({
    page,
  }) => {
    await goToEarnings(page);

    // Each summary card has a large bold amount (e.g. "0.00" or "1,250.75")
    const amounts = page.locator('.bg-white.rounded-2xl p.text-2xl, .bg-white.rounded-2xl p.text-2xl');
    const count = await amounts.count();
    expect(count, 'Expected 3 amount elements in summary cards').toBeGreaterThanOrEqual(3);

    for (let i = 0; i < Math.min(count, 3); i++) {
      const text = (await amounts.nth(i).innerText()).trim();
      expect(
        text,
        `Summary card ${i} amount "${text}" should be a decimal number`
      ).toMatch(/^[\d,]+\.\d{2}$/);
    }
  });

  test('page has no Laravel error and no raw blade syntax', async ({ page }) => {
    await goToEarnings(page);
    const body = await page.locator('body').innerText();
    expect(body).not.toContain('Whoops');
    expect(body).not.toContain('{{');
    expect(body).not.toContain('Stack trace');
  });
});

// ── Tabs ──────────────────────────────────────────────────────────────────────

test.describe('Earnings – tab navigation', () => {
  test('all three tabs are visible: Pending, Approved, Payout History', async ({ page }) => {
    await goToEarnings(page);

    await expect(page.getByRole('button', { name: /^pending/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /^approved/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /payout history/i })).toBeVisible();
  });

  test('Pending tab is active by default and its panel is visible', async ({ page }) => {
    await goToEarnings(page);

    // Pending panel starts visible; shows either a table or empty-state message
    const pendingPanel = page.locator('[x-show="tab === \'pending\'"]').first();
    await expect(pendingPanel).toBeVisible();

    const hasTable = await pendingPanel.locator('table').isVisible();
    const hasEmpty = await pendingPanel.getByText('No pending commissions').isVisible();
    expect(hasTable || hasEmpty, 'Pending tab should show a table or empty state').toBe(true);
  });

  test('clicking Approved tab shows the approved panel', async ({ page }) => {
    await goToEarnings(page);
    await page.waitForTimeout(500); // let Alpine hydrate

    await page.getByRole('button', { name: /^approved/i }).click();
    await page.waitForTimeout(300);

    const approvedPanel = page.locator('[x-show="tab === \'approved\'"]').first();
    await expect(approvedPanel).toBeVisible();

    const hasTable = await approvedPanel.locator('table').isVisible();
    const hasEmpty = await approvedPanel.getByText('No approved commissions').isVisible();
    expect(hasTable || hasEmpty, 'Approved tab should show a table or empty state').toBe(true);
  });

  test('clicking Payout History tab shows the payouts panel', async ({ page }) => {
    await goToEarnings(page);
    await page.waitForTimeout(500);

    await page.getByRole('button', { name: /payout history/i }).click();
    await page.waitForTimeout(300);

    const payoutsPanel = page.locator('[x-show="tab === \'payouts\'"]').first();
    await expect(payoutsPanel).toBeVisible();

    const hasTable = await payoutsPanel.locator('table').isVisible();
    const hasEmpty = await payoutsPanel.getByText('No payouts yet').isVisible();
    expect(hasTable || hasEmpty, 'Payout History tab should show a table or empty state').toBe(true);
  });

  test('empty-state panels show a meaningful message, not a blank area', async ({ page }) => {
    await goToEarnings(page);

    // Check each tab; if it shows an empty state, confirm the message is present
    const tabs: Array<{ buttonName: RegExp; tabKey: string; emptyText: RegExp }> = [
      { buttonName: /^pending/i,      tabKey: 'pending',  emptyText: /no pending commissions/i },
      { buttonName: /^approved/i,     tabKey: 'approved', emptyText: /no approved commissions/i },
      { buttonName: /payout history/i, tabKey: 'payouts', emptyText: /no payouts yet/i },
    ];

    for (const { buttonName, tabKey, emptyText } of tabs) {
      await page.getByRole('button', { name: buttonName }).click();
      await page.waitForTimeout(200);

      const panel = page.locator(`[x-show="tab === '${tabKey}'"]`).first();
      const hasTable = await panel.locator('table').isVisible();

      if (!hasTable) {
        // Empty state must show a non-blank message
        const emptyMsg = panel.getByText(emptyText);
        await expect(
          emptyMsg,
          `Tab "${tabKey}" shows no table — expected empty-state message matching ${emptyText}`
        ).toBeVisible();
      }
    }
  });
});

// ── Pending table column assertions (when data exists) ───────────────────────

test.describe('Earnings – pending commissions table', () => {
  test('pending table has correct columns: Campaign, Order Value, Commission, Date', async ({
    page,
  }) => {
    await goToEarnings(page);

    const pendingPanel = page.locator('[x-show="tab === \'pending\'"]').first();
    const hasTable = await pendingPanel.locator('table').isVisible();

    if (!hasTable) {
      test.skip(true, 'No pending commissions to assert columns on');
      return;
    }

    const thead = pendingPanel.locator('table thead');
    await expect(thead.getByText('Campaign')).toBeVisible();
    await expect(thead.getByText('Order Value')).toBeVisible();
    await expect(thead.getByText('Commission')).toBeVisible();
    await expect(thead.getByText('Date')).toBeVisible();
  });

  /**
   * ORDER REFERENCE VERIFICATION
   * The page does not include an order_id, order_number, or any order reference column.
   * Confirmed by reading earnings/index.blade.php: columns are campaign name, order_value_cents/100,
   * commission_amount_cents/100, created_at. No masking concern exists here.
   *
   * This test asserts that no order identifier field is rendered, so any future change that adds
   * one without masking will be caught.
   */
  test('page content does not include raw order ID or order number columns', async ({ page }) => {
    await goToEarnings(page);

    const pageContent = await page.content();

    // The blade renders order_value_cents/100 as "Order Value" — that's a money amount, not a reference.
    // There must be no column header or data cell labelled "Order #", "Order ID", or "Order Ref".
    // We check the rendered HTML for column headers only — looking for order reference patterns.
    expect(pageContent).not.toMatch(/Order\s*(#|ID|Ref|Number|Reference)/i);
  });
});

// ── Security: sensitive fields must not appear in the DOM ────────────────────

test.describe('Earnings – security assertions', () => {
  test('page DOM contains no admin_share_pct', async ({ page }) => {
    await goToEarnings(page);
    const content = await page.content();
    expect(
      content,
      'SECURITY: admin_share_pct must not appear in the earnings page DOM'
    ).not.toContain('admin_share_pct');
  });

  test('page DOM contains no product_value_cents', async ({ page }) => {
    await goToEarnings(page);
    const content = await page.content();
    expect(
      content,
      'SECURITY: product_value_cents must not appear in the earnings page DOM'
    ).not.toContain('product_value_cents');
  });

  test('page DOM contains no marketer_secret_promotions reference', async ({ page }) => {
    await goToEarnings(page);
    const content = await page.content();
    expect(
      content,
      'SECURITY: marketer_secret_promotions must not appear in the earnings page DOM'
    ).not.toContain('marketer_secret_promotions');
  });
});
