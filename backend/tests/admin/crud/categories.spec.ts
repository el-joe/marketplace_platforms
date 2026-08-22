/**
 * Phase 2 – Categories CRUD
 *
 * All created records are prefixed "PWTEST_" so they are easy to
 * bulk-clean and never collide with real data on repeated runs.
 * Every test that creates a record also deletes it (idempotent).
 */
import { test, expect, type Page } from '@playwright/test';

const BASE = '/categories';
const TAG  = 'PWTEST_CAT_';

// ── helpers ──────────────────────────────────────────────────────────────────

async function goToIndex(page: Page) {
  await page.goto(BASE);
  await expect(page).toHaveURL(new RegExp(BASE));
}

/** Create a category via the UI and return the name used */
async function createCategory(page: Page, suffix = Date.now().toString()): Promise<string> {
  const name = `${TAG}${suffix}`;
  await page.goto(`${BASE}/create`);
  await page.locator('input[name="name_en"]').fill(name);
  await page.locator('input[name="name_ar"]').fill(`${TAG}AR_${suffix}`);
  await page.locator('button#submit-btn').click();
  // Should redirect back to the list after creation
  await expect(page).toHaveURL(new RegExp(BASE));
  return name;
}

/** Delete a category row by its English name via the Delete button in the table.
 *  Categories use a JS-driven delete backed by SweetAlert2 for confirmation.
 *  After the AJAX call the row is removed from the DOM without a page reload. */
async function deleteCategory(page: Page, name: string) {
  await goToIndex(page);
  const row = page.locator('tr', { hasText: name });
  await row.locator('button.delete-cat-btn').click();
  // SweetAlert2 confirm button
  await page.locator('button.swal2-confirm').click();
  // Row should disappear from the DOM
  await expect(row).toHaveCount(0, { timeout: 10000 });
}

// ── tests ─────────────────────────────────────────────────────────────────────

test.describe('Categories CRUD', () => {

  test('list page loads and shows table', async ({ page }) => {
    await goToIndex(page);
    await expect(page.locator('table#categories-table')).toBeVisible();
    // Either rows or the empty-state message
    const rows = page.locator('table#categories-table tbody tr');
    const emptyCell = page.locator('table#categories-table td', { hasText: 'No categories yet.' });
    const hasContent = await rows.count() > 0 || await emptyCell.isVisible();
    expect(hasContent).toBeTruthy();
  });

  test('create – valid category appears in the list', async ({ page }) => {
    const name = await createCategory(page);
    try {
      await goToIndex(page);
      await expect(page.locator('td', { hasText: name })).toBeVisible();
    } finally {
      await deleteCategory(page, name);
    }
  });

  test('create – validation: missing required field shows error', async ({ page }) => {
    await page.goto(`${BASE}/create`);
    await page.waitForLoadState('domcontentloaded');
    // Submit with name_en blank (name_ar filled to isolate which field fails)
    await page.locator('input[name="name_ar"]').fill('بدون اسم انجليزي');
    await page.locator('button#submit-btn').click();
    // Standard HTML POST → server validates → redirects back with flash errors
    await page.waitForLoadState('domcontentloaded');
    await expect(page).toHaveURL(new RegExp(`${BASE}/create`));
    // Laravel renders errors in a .text-red-700 container inside the submit card
    await expect(page.locator('.text-red-700').first()).toBeVisible({ timeout: 8000 });
  });

  test('edit – change name_en and verify it persists', async ({ page }) => {
    const original = await createCategory(page, `EDIT_${Date.now()}`);
    try {
      // Find and click Edit link
      await goToIndex(page);
      const row = page.locator('tr', { hasText: original });
      await row.locator('a', { hasText: 'Edit' }).click();
      await expect(page).toHaveURL(new RegExp(`${BASE}/[\\w-]+/edit`));

      const updated = `${original}_RENAMED`;
      await page.locator('input[name="name_en"]').fill(updated);
      await page.locator('button#submit-btn').click();
      // Edit uses AJAX PUT — stays on edit page, shows toast. Wait for toast.
      await expect(page.locator('.toast, [class*="toast"], [class*="swal"], [class*="Toast"]').first()).toBeVisible({ timeout: 5000 }).catch(() => {});
      // Navigate back to index and confirm updated name
      await goToIndex(page);
      await expect(page.locator('td', { hasText: updated })).toBeVisible();

      // Clean up using updated name
      await deleteCategory(page, updated);
    } catch (e) {
      // Best-effort cleanup with original name if rename failed
      await deleteCategory(page, original).catch(() => {});
      throw e;
    }
  });

  test('delete – row removed after confirmation', async ({ page }) => {
    const name = await createCategory(page, `DEL_${Date.now()}`);
    await goToIndex(page);
    await expect(page.locator('td', { hasText: name })).toBeVisible();
    await deleteCategory(page, name);
    await goToIndex(page);
    await expect(page.locator('td', { hasText: name })).toHaveCount(0);
  });

  test('toggle-featured button visible on category rows', async ({ page }) => {
    const name = await createCategory(page, `FEAT_${Date.now()}`);
    try {
      await goToIndex(page);
      const row = page.locator('tr', { hasText: name });
      await expect(row.locator('button.featured-btn')).toBeVisible();
    } finally {
      await deleteCategory(page, name);
    }
  });

});
