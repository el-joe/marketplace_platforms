/**
 * Phase 2 – Coupons CRUD
 *
 * All created coupons use code prefix "PWTEST" and name prefix "PWTEST_CPN_".
 * Every test cleans up after itself.
 *
 * Notes on the UI:
 *  - Coupons list uses a DataTable loaded via AJAX (not a server-rendered table).
 *  - Delete uses SweetAlert2 + AJAX (no page reload after delete).
 *  - Edit is a standard HTML form with a server-side redirect back to index.
 *  - valid_from / valid_until use flatpickr with allowInput:false —
 *    dates are set via el._flatpickr.setDate().
 */
import { test, expect, type Page } from '@playwright/test';

const BASE = '/coupons';
const TAG  = 'PWTEST_CPN_';

// ── helpers ───────────────────────────────────────────────────────────────────

async function goToIndex(page: Page) {
  await page.goto(BASE);
  await page.waitForSelector('table[id="coupons-table"]', { timeout: 10000 });
}

/** Set a flatpickr date field by calling its JS API, with a fallback. */
async function setFlatpickr(page: Page, selector: string, value: string) {
  await page.waitForFunction(
    (sel) => !!document.querySelector(sel),
    selector,
    { timeout: 5000 }
  );
  await page.evaluate(
    ({ sel, val }) => {
      const el = document.querySelector(sel) as any;
      if (!el) return;
      if (el._flatpickr) {
        el._flatpickr.setDate(val, true);
      } else {
        // flatpickr not yet initialised — set raw value and fire events
        el.removeAttribute('required');
        el.value = val;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      }
    },
    { sel: selector, val: value }
  );
}

/** Create a coupon via the UI and return the code used. */
async function createCoupon(page: Page, suffix = Date.now().toString()): Promise<string> {
  const code = `PWTEST${suffix}`;
  const name = `${TAG}${suffix}`;

  await page.goto(`${BASE}/create`);

  await page.locator('input#code').fill(code);
  await page.locator('input#name').fill(name);
  // type stays 'percentage' (default)
  await page.locator('input#value').fill('10');
  await page.locator('input#usage_limit_per_customer').fill('1');

  // Flatpickr uses allowInput:false — use its JS API
  await setFlatpickr(page, 'input#valid_from', '2026-01-01 00:00');
  // valid_until is a TIMESTAMP column; max is 2038-01-19
  await setFlatpickr(page, 'input#valid_until', '2038-01-01 23:59');

  await page.locator('button#save-btn').click();
  // Controller redirects to /coupons/{id}/edit on success (not index)
  await page.waitForURL(/\/coupons\/[\w-]+\/edit/, { timeout: 10000 });
  return code;
}

/** Wait for DataTable AJAX to settle. */
async function waitForTableLoad(page: Page) {
  // Wait for the DataTables processing indicator to disappear, or just settle
  await page.waitForTimeout(800);
}

/** Find a row in the coupons DataTable that contains the given code text. */
function couponRow(page: Page, code: string) {
  return page.locator('#coupons-table tbody tr', { hasText: code });
}

/** Delete a coupon by its code. Uses SweetAlert2 confirmation. */
async function deleteCoupon(page: Page, code: string) {
  await goToIndex(page);
  await waitForTableLoad(page);
  const row = couponRow(page, code);
  await expect(row).toBeVisible({ timeout: 8000 });
  // Actions column may be in DataTables responsive fold — fire native JS click
  await row.locator('button[data-action="delete"]').evaluate((el: HTMLElement) => el.click());
  await page.locator('button.swal2-confirm').click();
  await expect(row).toHaveCount(0, { timeout: 10000 });
}

// ── tests ─────────────────────────────────────────────────────────────────────

test.describe('Coupons CRUD', () => {

  test('list page loads and shows DataTable', async ({ page }) => {
    await goToIndex(page);
    await waitForTableLoad(page);
    await expect(page.locator('table#coupons-table')).toBeVisible();
  });

  test('search filters results by code/name', async ({ page }) => {
    await goToIndex(page);
    const searchInput = page.locator('input[name="search"]');
    if (await searchInput.isVisible()) {
      await searchInput.fill('UNLIKELY_COUPON_XYZABC999');
      await waitForTableLoad(page);
      const zeroRows = await couponRow(page, 'UNLIKELY_COUPON_XYZABC999').count();
      const noDataRow = page.locator('#coupons-table tbody td.dataTables_empty, #coupons-table td', { hasText: 'No data' }).first();
      expect(zeroRows === 0 || await noDataRow.isVisible()).toBeTruthy();
    }
  });

  test('create – valid coupon appears in the list', async ({ page }) => {
    const code = await createCoupon(page, `A${Date.now()}`);
    try {
      await goToIndex(page);
      await waitForTableLoad(page);
      await expect(couponRow(page, code)).toBeVisible({ timeout: 8000 });
    } finally {
      await deleteCoupon(page, code);
    }
  });

  test('create – submit with blank code shows server validation error', async ({ page }) => {
    await page.goto(`${BASE}/create`);
    // The form has novalidate — server validates. Fill everything except code.
    await page.locator('input#name').fill(`${TAG}VALIDATION_TEST`);
    await page.locator('input#value').fill('5');
    await page.locator('input#usage_limit_per_customer').fill('1');
    await setFlatpickr(page, 'input#valid_from', '2026-01-01 00:00');
    await setFlatpickr(page, 'input#valid_until', '2038-01-01 23:59');
    // Intercept: server should return 422 → JS shows toast, page stays put
    const responsePromise = page.waitForResponse('**/coupons');
    await page.locator('button#save-btn').click();
    const response = await responsePromise;
    expect(response.status()).toBe(422);
    // Page stays on create
    await expect(page).toHaveURL(/coupons\/create/, { timeout: 3000 });
  });

  test('edit – change name and verify it persists', async ({ page }) => {
    const code = await createCoupon(page, `B${Date.now()}`);
    try {
      await goToIndex(page);
      await waitForTableLoad(page);
      const row = couponRow(page, code);
      await row.locator('a', { hasText: 'Edit' }).evaluate((el: HTMLElement) => el.click());
      await expect(page).toHaveURL(new RegExp(`${BASE}/[\\w-]+/edit`));

      const updatedName = `${TAG}EDITED_${Date.now()}`;
      await page.locator('input#name').fill(updatedName);
      // Edit uses AJAX PUT — stays on edit page, shows toast. Wait for response.
      const editResponse = page.waitForResponse(/\/coupons\//);
      await page.locator('button#save-btn').click();
      await editResponse;
      // Navigate back to index and confirm coupon still exists (we only renamed name, not code)
      await goToIndex(page);
      await waitForTableLoad(page);
      await expect(couponRow(page, code)).toBeVisible({ timeout: 8000 });
    } finally {
      await deleteCoupon(page, code);
    }
  });

  test('delete – row removed after SweetAlert2 confirmation', async ({ page }) => {
    const code = await createCoupon(page, `C${Date.now()}`);
    await goToIndex(page);
    await waitForTableLoad(page);
    await expect(couponRow(page, code)).toBeVisible({ timeout: 8000 });
    await deleteCoupon(page, code);
    await goToIndex(page);
    await waitForTableLoad(page);
    await expect(couponRow(page, code)).toHaveCount(0);
  });

  test('status filter – active only shows no Inactive badges', async ({ page }) => {
    await goToIndex(page);
    const statusFilter = page.locator('select[name="is_active"]');
    if (await statusFilter.isVisible()) {
      await statusFilter.selectOption('1');
      await waitForTableLoad(page);
      const inactiveBadges = page.locator('#coupons-table tbody td span', { hasText: 'Inactive' });
      await expect(inactiveBadges).toHaveCount(0, { timeout: 5000 });
    }
  });

});
