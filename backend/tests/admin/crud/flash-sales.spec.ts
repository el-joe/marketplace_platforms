/**
 * Phase 2 – Flash Sales
 *
 * Tests cover:
 *  - List page loads with DataTable and status tabs
 *  - "New Flash Sale" button is visible
 *  - Create flow: fill required fields and submit
 *  - Created flash sale appears in list
 *  - Detail page loads with correct sections
 *  - Invite vendor button is visible on detail page
 *  - Delete (via status transition to "cancelled") is not tested here
 *    as it requires specific workflow; instead we test Create + verify
 *
 * All created flash sales are prefixed "PWTEST_FS_" and deleted after the test.
 * Flash sales use AJAX submit (flash-sale-form.js).
 * Dates must be in valid ranges.
 */
import { test, expect, type Page } from '@playwright/test';

const BASE = '/flash-sales';
const TAG  = 'PWTEST_FS_';

async function goToIndex(page: Page) {
  await page.goto(BASE);
  await page.waitForSelector('table#flash-sales-table', { timeout: 10000 });
  await page.waitForTimeout(800);
}

/** Set a flatpickr date field. */
async function setFlatpickr(page: Page, selector: string, value: string) {
  await page.evaluate(
    ({ sel, val }) => {
      const el = document.querySelector(sel) as any;
      if (!el) return;
      if (el._flatpickr) {
        el._flatpickr.setDate(val, true);
      } else {
        el.value = val;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      }
    },
    { sel: selector, val: value }
  );
}

/** Select2 — set value via JS API. */
async function setSelect2(page: Page, selectId: string, value: string) {
  await page.evaluate(
    ({ id, val }) => {
      const el = document.querySelector(`#${id}`) as HTMLSelectElement;
      if (!el) return;
      // Try jQuery select2 API
      if ((window as any).$ && (window as any).$(`#${id}`).data('select2')) {
        (window as any).$(`#${id}`).val(val).trigger('change');
      } else {
        el.value = val;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      }
    },
    { id: selectId, val: value }
  );
}

/**
 * Create a flash sale via the create form (AJAX submit).
 * Returns the name used.
 */
async function createFlashSale(page: Page, suffix = Date.now().toString()): Promise<string> {
  const name = `${TAG}${suffix}`;
  await page.goto(`${BASE}/create`);
  await page.waitForLoadState('networkidle');

  await page.fill('input[name="name_en"]', name);
  await page.fill('input[name="name_ar"]', `${TAG}AR${suffix}`);
  // min_discount_pct is in the "Rules" tab — click it first, then fill
  await page.locator('button', { hasText: 'Rules' }).click();
  await page.fill('input[name="min_discount_pct"]', '10');

  // Set timeline dates (all must be sequential, within TIMESTAMP range)
  await setFlatpickr(page, 'input[name="submission_opens_at"]',  '2027-01-01 08:00');
  await setFlatpickr(page, 'input[name="submission_closes_at"]', '2027-01-10 17:00');
  await setFlatpickr(page, 'input[name="review_deadline_at"]',   '2027-01-12 17:00');
  await setFlatpickr(page, 'input[name="sale_starts_at"]',       '2027-01-15 00:00');
  await setFlatpickr(page, 'input[name="sale_ends_at"]',         '2027-01-20 23:59');

  // Intercept the AJAX response to detect success
  const responsePromise = page.waitForResponse(/flash-sales/);
  await page.locator('#flash-sale-submit-btn').click();
  const response = await responsePromise;

  if (response.status() !== 200 && response.status() !== 201) {
    const body = await response.text().catch(() => '');
    throw new Error(`Flash sale create failed (${response.status()}): ${body.substring(0, 200)}`);
  }

  // JS redirects to show page on success
  await page.waitForURL(/\/flash-sales\/[\w-]+/, { timeout: 10000 });
  return name;
}

/** Delete a flash sale by navigating to its show page and cancelling it,
 *  or via the edit page. Since there's no simple "Delete" button,
 *  we use the Laravel route directly via fetch. */
async function deleteFlashSaleById(page: Page, id: string) {
  const csrf = await page.evaluate(
    () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
  );
  await page.evaluate(
    async ({ flashId, token }) => {
      await fetch(`/flash-sales/${flashId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
    },
    { flashId: id, token: csrf }
  );
}

// ── tests ─────────────────────────────────────────────────────────────────────

test.describe('Flash Sales', () => {

  test('list page loads with DataTable and status tabs', async ({ page }) => {
    await goToIndex(page);
    await expect(page.locator('table#flash-sales-table')).toBeVisible();
    // Status filter tabs — scoped to the tab bar above the table
    const tabBar = page.locator('[x-data*="tab"]').first();
    await expect(tabBar.locator('button', { hasText: /^All$/ })).toBeVisible();
    await expect(tabBar.locator('button', { hasText: /^Live$/ })).toBeVisible();
  });

  test('"New Flash Sale" create button is visible', async ({ page }) => {
    await goToIndex(page);
    await expect(page.locator('a[href*="flash-sales/create"]')).toBeVisible();
  });

  test('create form loads with required field inputs', async ({ page }) => {
    await page.goto(`${BASE}/create`);
    // "Details" tab is active by default
    await expect(page.locator('input[name="name_en"]')).toBeVisible();
    await expect(page.locator('#flash-sale-submit-btn')).toBeVisible();
    // Switch to Rules tab and verify discount field is there
    await page.locator('button', { hasText: 'Rules' }).click();
    await expect(page.locator('input[name="min_discount_pct"]')).toBeVisible();
  });

  test('create – valid flash sale is created and appears in list', async ({ page }) => {
    const name = await createFlashSale(page, `A${Date.now()}`);
    const id = page.url().split('/flash-sales/')[1].replace('/edit', '');
    try {
      await goToIndex(page);
      // Flash sale should appear in the DataTable
      const row = page.locator('#flash-sales-table tbody td', { hasText: name });
      await expect(row.first()).toBeVisible({ timeout: 8000 });
    } finally {
      await page.goto(`${BASE}/${id}/edit`).catch(() => {});
      await deleteFlashSaleById(page, id);
    }
  });

  test('create – validation: missing required date shows server error', async ({ page }) => {
    await page.goto(`${BASE}/create`);
    await page.waitForLoadState('networkidle');
    await page.fill('input[name="name_en"]', `${TAG}VALIDATION`);
    await page.fill('input[name="name_ar"]', `${TAG}AR_VAL`);
    await page.locator('button', { hasText: 'Rules' }).click();
    await page.fill('input[name="min_discount_pct"]', '10');
    // Don't fill dates → server returns 422
    const responsePromise = page.waitForResponse(/flash-sales/);
    await page.locator('#flash-sale-submit-btn').click();
    const response = await responsePromise;
    expect(response.status()).toBe(422);
    // Stay on create
    await expect(page).toHaveURL(/flash-sales\/create/, { timeout: 3000 });
  });

  test('flash sale detail page has Invite Vendors button', async ({ page }) => {
    // Use an existing flash sale from the seeded list (avoid create complexity)
    await goToIndex(page);
    const firstLink = page.locator('#flash-sales-table tbody a').first();
    if (await firstLink.count() === 0) {
      test.skip(true, 'No flash sales in list to test detail page');
      return;
    }
    await firstLink.evaluate((el: HTMLElement) => el.click());
    await expect(page).toHaveURL(/\/flash-sales\/[\w-]+/, { timeout: 10000 });
    await page.waitForTimeout(500);
    // Click the Invitations tab to reveal invite buttons
    const invitationsTab = page.locator('button', { hasText: 'Invitations' }).first();
    await invitationsTab.click({ force: true });
    const inviteBtn = page.locator('#btn-auto-invite').first();
    await expect(inviteBtn).toBeVisible({ timeout: 8000 });
  });

  test('status tab filter "Draft" reloads table', async ({ page }) => {
    await goToIndex(page);
    const draftTab = page.locator('button', { hasText: 'Draft' });
    await draftTab.click();
    await page.waitForTimeout(800);
    await expect(page.locator('table#flash-sales-table')).toBeVisible();
  });

});
