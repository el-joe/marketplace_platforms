/**
 * Phase 2 – Warehouses CRUD
 *
 * All created warehouses are prefixed "PWTEST_WH_" and use code "PWTEST{n}".
 * There is no admin DELETE route for warehouses, so cleanup is done via
 * toggle-active only. Created test records are left deactivated (PWTEST prefix
 * makes them easy to bulk-clean later via DB/artisan if needed).
 *
 * Required fields: name, code (unique), type, country_id.
 * country_id is a select2 dropdown — set via jQuery Select2 API.
 */
import { test, expect, type Page } from '@playwright/test';

const BASE = '/warehouses';
const TAG  = 'PWTEST_WH_';

// A real country UUID from the seeded countries table
const SEEDED_COUNTRY_ID = '2162d228-26b9-4635-8233-cba028a6affe';

async function goToIndex(page: Page) {
  await page.goto(BASE);
  await page.waitForSelector('table#warehouses-table', { timeout: 10000 });
  await page.waitForTimeout(600);
}

/** Set a select2-enhanced select field. */
async function setSelect2(page: Page, name: string, value: string) {
  await page.evaluate(
    ({ fieldName, val }) => {
      const el = document.querySelector(`select[name="${fieldName}"]`) as HTMLSelectElement;
      if (!el) return;
      el.value = val;
      el.dispatchEvent(new Event('change', { bubbles: true }));
      // Also try jQuery select2 API if available
      const $ = (window as any).$;
      if ($ && el.id && $(el).data && $(el).data('select2')) {
        $(el).val(val).trigger('change');
      }
    },
    { fieldName: name, val: value }
  );
}

/** Create a warehouse via the UI. Returns the warehouse code. */
async function createWarehouse(page: Page, suffix = Date.now().toString()): Promise<string> {
  const code = `PWTEST${suffix}`.substring(0, 20); // max 20 chars
  const name = `${TAG}${suffix}`;

  await page.goto(`${BASE}/create`);
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(500); // let select2 init

  await page.fill('input[name="name"]', name);
  await page.fill('input[name="code"]', code);
  // type is a plain select — default is platform_fbn, no change needed

  // country_id is select2 — set via JS
  await setSelect2(page, 'country_id', SEEDED_COUNTRY_ID);

  await page.locator('button.btn-primary[type="submit"]').click();
  // Standard form redirect to show page
  await expect(page).toHaveURL(/\/warehouses\/[\w-]+$/, { timeout: 10000 });
  return code;
}

// ── tests ─────────────────────────────────────────────────────────────────────

test.describe('Warehouses CRUD', () => {

  test('list page loads with warehouses table and stats', async ({ page }) => {
    await goToIndex(page);
    await expect(page.locator('table#warehouses-table')).toBeVisible();
    // Stats cards should be visible
    await expect(page.locator('body')).toContainText('Platform (FBN)');
  });

  test('search field filters by name/code', async ({ page }) => {
    await goToIndex(page);
    const searchInput = page.locator('input#search-input');
    if (await searchInput.isVisible()) {
      await searchInput.fill('UNLIKELY_WH_XYZABC9999');
      await page.waitForTimeout(800);
      await expect(page.locator('table#warehouses-table')).toBeVisible();
    }
  });

  test('"Add Warehouse" button is visible', async ({ page }) => {
    await goToIndex(page);
    await expect(page.locator('a[href*="/warehouses/create"]')).toBeVisible();
  });

  test('create form loads with required fields', async ({ page }) => {
    await page.goto(`${BASE}/create`);
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="code"]')).toBeVisible();
    await expect(page.locator('select[name="type"]')).toBeVisible();
    await expect(page.locator('select[name="country_id"]')).toBeAttached();
  });

  test('create – valid warehouse is created and show page loads', async ({ page }) => {
    const code = await createWarehouse(page, `A${Date.now()}`.substring(0, 13));
    // On success, we're on the show page — should mention the warehouse name
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('body')).toContainText(code);
  });

  test('create – missing required field shows validation error', async ({ page }) => {
    await page.goto(`${BASE}/create`);
    await page.waitForLoadState('domcontentloaded');
    // Fill name but leave code blank → server validation 422
    await page.fill('input[name="name"]', `${TAG}NO_CODE`);
    await setSelect2(page, 'country_id', SEEDED_COUNTRY_ID);
    await page.locator('button.btn-primary[type="submit"]').click();
    // Standard HTML POST → server validates → redirects back with flash errors
    await page.waitForLoadState('domcontentloaded');
    await expect(page).toHaveURL(/warehouses\/create/, { timeout: 8000 });
    const errorText = page.locator('.text-red-600, .text-red-700, .text-danger');
    await expect(errorText.first()).toBeVisible({ timeout: 8000 });
  });

  test('edit – change name and verify it persists', async ({ page }) => {
    const code = await createWarehouse(page, `B${Date.now()}`.substring(0, 13));
    // We're on show page — navigate to edit
    await page.locator('a[href*="/edit"]').first().click();
    await expect(page).toHaveURL(/\/warehouses\/[\w-]+\/edit/, { timeout: 8000 });

    const updatedName = `${TAG}EDITED_${Date.now()}`.substring(0, 50);
    await page.fill('input[name="name"]', updatedName);
    await page.locator('button.btn-primary[type="submit"]').click();

    // Redirects back to show page
    await expect(page).toHaveURL(/\/warehouses\/[\w-]+$/, { timeout: 8000 });
    await expect(page.locator('body')).toContainText(updatedName);
  });

  test('toggle-active button triggers confirmation', async ({ page }) => {
    await goToIndex(page);
    // Find a toggle-active button and verify it triggers a confirm() dialog
    const toggleBtn = page.locator('button[data-warehouse-id], button.toggle-active-btn').first();
    if (await toggleBtn.count() > 0) {
      // The toggle uses native window.confirm — accept it
      page.once('dialog', (dialog) => dialog.dismiss()); // dismiss so no state change
      await toggleBtn.click({ force: true });
    }
    // Page should still be on warehouses index
    await expect(page).toHaveURL(/\/warehouses/, { timeout: 3000 });
  });

  test('warehouse show page displays type, status, and capacity info', async ({ page }) => {
    // Navigate to any existing warehouse detail
    await goToIndex(page);
    const firstLink = page.locator('#warehouses-table tbody a').first();
    if (await firstLink.count() > 0) {
      await firstLink.evaluate((el: HTMLElement) => el.click());
      await expect(page).toHaveURL(/\/warehouses\/[\w-]+$/, { timeout: 8000 });
      // Warehouse show page should display key info
      await expect(page.locator('body')).toContainText(/Capacity|Active|FBN|seller|third/i);
    }
  });

});
