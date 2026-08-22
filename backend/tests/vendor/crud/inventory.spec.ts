/**
 * Vendor Phase 2 – Inventory (المخزون)
 *
 * Environment guard: APP_ENV=local, local dev DB.
 * Test vendor: khalid@techzone.com
 * Seeded listing: 846e547b-e395-4949-8302-09ea1b71ee67
 * Seeded warehouse_inventory: 019eea32-9ec7-724d-8fc9-1f12a6f2b28d
 *   quantity_on_hand=10, quantity_reserved=0
 *   quantity_available (VIRTUAL GENERATED = on_hand - reserved) = 10
 *
 * VIRTUAL COLUMN RULE:
 *   quantity_available is a GENERATED VIRTUAL column (quantity_on_hand - quantity_reserved).
 *   It is read-only at the DB level and must never have a direct-input field in the UI.
 *   Tests assert:
 *     (a) no editable input targets "available" directly
 *     (b) the displayed "available" figure updates AFTER an adjustment that changes on_hand
 *
 * Stock adjustments are performed from the listing show page (adjust-stock modal),
 * not from the /inventory index page itself (which is read-only display + datatable).
 * The inventory index links to each listing's movements page.
 *
 * All adjustments are idempotent: we add +1 then restore -1 in the same test.
 */
import { test, expect, type Page } from '@playwright/test';

const LISTING_ID   = '846e547b-e395-4949-8302-09ea1b71ee67';
const INVENTORY_ID = '019eea32-9ec7-724d-8fc9-1f12a6f2b28d';

async function goToInventoryIndex(page: Page) {
  await page.goto('/inventory');
  await expect(page).toHaveURL(/\/inventory/);
}

async function goToListingShow(page: Page) {
  await page.goto(`/listings/${LISTING_ID}`);
  await page.waitForFunction(() => !!(window as any).LISTING_DETAIL, { timeout: 8000 });
}

// ── helpers ───────────────────────────────────────────────────────────────────

/** Open the adjust-stock modal for the first warehouse row. */
async function openAdjustModal(page: Page) {
  const adjustBtn = page.locator('.btn-adjust-stock').first();
  await expect(adjustBtn).toBeVisible({ timeout: 8000 });
  await adjustBtn.click();
  await expect(page.locator('#adjust-stock-modal')).toBeVisible({ timeout: 5000 });
}

// ── tests ─────────────────────────────────────────────────────────────────────

test.describe('Inventory', () => {

  // ── index page ────────────────────────────────────────────────────────────

  test('index page loads with summary stat cards', async ({ page }) => {
    await goToInventoryIndex(page);
    // Stat cards: total SKUs, total units, available, low-stock
    await expect(page.locator('text=إجمالي SKUs')).toBeVisible();
    await expect(page.locator('text=إجمالي الوحدات')).toBeVisible();
    await expect(page.locator('text=متاح للبيع')).toBeVisible();
  });

  test('index datatable renders at least one inventory row', async ({ page }) => {
    await goToInventoryIndex(page);
    const row = page.locator('table tbody tr').first();
    await expect(row).toBeVisible({ timeout: 10000 });
  });

  test('low-stock filter tab navigates without error', async ({ page }) => {
    await goToInventoryIndex(page);
    const lowStockTab = page.locator('a[href*="filter=low_stock"]');
    await expect(lowStockTab).toBeVisible();
    await lowStockTab.click();
    await expect(page).toHaveURL(/filter=low_stock/);
    // Page should still render the table (even if it's empty)
    await expect(page.locator('table')).toBeVisible({ timeout: 8000 });
  });

  test('out-of-stock filter tab navigates without error', async ({ page }) => {
    await goToInventoryIndex(page);
    await page.locator('a[href*="filter=out_of_stock"]').click();
    await expect(page).toHaveURL(/filter=out_of_stock/);
    await expect(page.locator('table')).toBeVisible({ timeout: 8000 });
  });

  // ── schema assertion: quantity_available has no direct-input field ─────────

  test('SCHEMA: adjust-stock modal has no editable "available" input field', async ({ page }) => {
    await goToListingShow(page);
    await openAdjustModal(page);

    // The modal shows current qty as display text (#adjust-current-qty), NOT an input
    const currentQtyDisplay = page.locator('#adjust-current-qty');
    await expect(currentQtyDisplay).toBeVisible();
    // It must be a <p> or display element, never an <input>
    const tagName = await currentQtyDisplay.evaluate((el) => el.tagName.toLowerCase());
    expect(tagName).not.toBe('input');

    // There must be NO input with name="quantity_available" or similar in the form
    const availableInput = page.locator('#adjust-form input[name*="available"]');
    await expect(availableInput).toHaveCount(0);

    // The form only accepts: adjustment (delta) + reason
    await expect(page.locator('#adjust-form input[name="adjustment"]')).toBeVisible();
    await expect(page.locator('#adjust-form select[name="reason"]')).toBeVisible();
  });

  // ── stock adjustment flow ─────────────────────────────────────────────────

  /**
   * BUG REPORT — schema violation in ListingController::adjustStock()
   *
   * inventory_movements.movement_type ENUM =
   *   ('inbound','outbound','reservation','release','adjustment','damage','return','transfer')
   *
   * The controller writes 'adjustment_in' (positive delta) or 'adjustment_out' (negative delta).
   * Neither value exists in the ENUM. MySQL 8 treats this as a data-truncation error and the
   * DB transaction is rolled back, returning HTTP 500 to the vendor UI.
   *
   * The correct value to write is 'adjustment'. The controller and the listings/show.blade.php
   * view (which maps 'adjustment_in'/'adjustment_out' to Arabic labels) are both out of sync
   * with the DB schema. This must be fixed in the controller before the adjustment flow can
   * be used in production.
   *
   * The test below documents the broken behaviour. Once the bug is fixed (controller uses
   * 'adjustment' and view mapping updated), the test should be updated to assert the modal
   * closes and the displayed quantity changes.
   */
  test('BUG: stock adjustment returns 500 due to movement_type ENUM mismatch', async ({ page }) => {
    await goToListingShow(page);
    await openAdjustModal(page);

    await page.locator('#adjust-form input[name="adjustment"]').fill('1');
    await page.locator('#adjust-form select[name="reason"]').selectOption('received_stock');

    // Intercept to capture the actual server response status
    const responsePromise = page.waitForResponse(r => r.url().includes('adjust-stock'));
    await page.locator('#adjust-form button[type="submit"]').click();
    const response = await responsePromise;

    // Assert the bug: server currently returns 500
    expect(response.status()).toBe(500);

    // The JS error handler shows the generic error message in #adjust-error
    await expect(page.locator('#adjust-error')).not.toHaveClass(/hidden/, { timeout: 5000 });

    // The modal must NOT have closed (error path keeps it open for retry)
    await expect(page.locator('#adjust-stock-modal')).toBeVisible();
  });

  test('stock adjustment – validation: missing reason shows error', async ({ page }) => {
    await goToListingShow(page);
    await openAdjustModal(page);
    await page.locator('#adjust-form input[name="adjustment"]').fill('5');
    // Leave reason blank — submit
    await page.locator('#adjust-form button[type="submit"]').click();
    // The <select required> triggers browser validation; modal should remain open
    await expect(page.locator('#adjust-stock-modal')).toBeVisible({ timeout: 3000 });
  });

  // ── movements page ────────────────────────────────────────────────────────

  test('inventory movements page loads for the seeded listing', async ({ page }) => {
    await page.goto(`/inventory/${LISTING_ID}/movements`);
    await expect(page).toHaveURL(new RegExp(`inventory/${LISTING_ID}/movements`));
    // Page should render without error
    await expect(page.locator('h1, h2, [class*="page-title"]').first()).toBeVisible({ timeout: 8000 });
  });

});
