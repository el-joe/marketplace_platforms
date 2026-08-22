/**
 * Vendor Phase 2 – Listings (قوائم المنتجات)
 *
 * Environment guard: APP_ENV=local, local dev DB confirmed (marketplace_platform @ 127.0.0.1).
 * Test vendor: khalid@techzone.com (owner), vendor_id 019ee664-6da7-7278-b147-4305bb9b5580
 * Seeded listing: id 846e547b-e395-4949-8302-09ea1b71ee67  status=active  price=1200 (cents → 12.00)
 *
 * Tests that mutate data target that seeded listing and restore it to its original state.
 *
 * CREATE NOTE: Vendor listings have no delete route in the partner panel.
 * The create test is intentionally scoped to verifying the form UI only (page load,
 * product-search interaction, placeholder → form reveal), NOT actual submission,
 * because:
 *   (a) any created listing would be uncleanable without admin access, and
 *   (b) the vendor already has the only seeded product variant listed, so a
 *       second attempt would hit a unique-constraint error anyway.
 * Actual end-to-end create flow should be tested in a dedicated seeded-DB fixture context.
 */
import { test, expect, type Page } from '@playwright/test';

const BASE = '/listings';
const LISTING_ID = '846e547b-e395-4949-8302-09ea1b71ee67';
const LISTING_URL = `${BASE}/${LISTING_ID}`;

async function goToIndex(page: Page) {
  await page.goto(BASE);
  await expect(page).toHaveURL(new RegExp(BASE));
}

async function goToShow(page: Page) {
  await page.goto(LISTING_URL);
  await expect(page).toHaveURL(new RegExp(LISTING_ID));
  // Wait for the JS bundle to initialise LISTING_DETAIL
  await page.waitForFunction(() => !!(window as any).LISTING_DETAIL, { timeout: 8000 });
}

// ── tests ─────────────────────────────────────────────────────────────────────

test.describe('Listings', () => {

  // ── list ──────────────────────────────────────────────────────────────────

  test('index page loads with status-filter tabs and search input', async ({ page }) => {
    await goToIndex(page);
    // Status tabs are anchor links; scope to the tab bar to avoid matching table badges
    const tabBar = page.locator('.bg-white.rounded-2xl.border.border-gray-200.mb-4').first();
    await expect(tabBar.locator('a', { hasText: 'الكل' })).toBeVisible();
    await expect(tabBar.locator('a[href*="status=active"]')).toBeVisible();
    await expect(tabBar.locator('a[href*="status=paused"]')).toBeVisible();
    // Search field
    await expect(page.locator('input#listing-search')).toBeVisible();
  });

  test('index datatable loads at least one row', async ({ page }) => {
    await goToIndex(page);
    // The table is populated by the datatable JS; wait for at least one row
    const row = page.locator('table tbody tr').first();
    await expect(row).toBeVisible({ timeout: 10000 });
  });

  test('"نشط" tab filters the datatable', async ({ page }) => {
    await page.goto(`${BASE}?status=active`);
    await expect(page).toHaveURL(/status=active/);
    // Table should still be visible after filter — no crash
    const row = page.locator('table tbody tr').first();
    await expect(row).toBeVisible({ timeout: 10000 });
  });

  // ── create page ───────────────────────────────────────────────────────────

  test('create page loads product-search step', async ({ page }) => {
    await page.goto(`${BASE}/create`);
    await expect(page.locator('input#product-search-input')).toBeVisible();
    // Right-hand form should show the "select a product" placeholder, not the form
    await expect(page.locator('#listing-form-placeholder')).toBeVisible();
    await expect(page.locator('#listing-form-container')).toBeHidden();
  });

  test('create – product search returns results (min 2 chars required)', async ({ page }) => {
    await page.goto(`${BASE}/create`);
    const searchInput = page.locator('input#product-search-input');
    // JS requires q.length >= 2 before firing the fetch
    await searchInput.fill('te');
    // Results container should populate
    const results = page.locator('#product-search-results');
    // At least one clickable result card with data-variant-id
    const firstResult = results.locator('[data-variant-id]').first();
    await expect(firstResult).toBeVisible({ timeout: 10000 });
  });

  test('create – selecting a product reveals the listing form', async ({ page }) => {
    await page.goto(`${BASE}/create`);
    await page.locator('input#product-search-input').fill('te');
    const firstResult = page.locator('#product-search-results [data-variant-id]').first();
    await expect(firstResult).toBeVisible({ timeout: 10000 });
    await firstResult.click();
    // Form container should become visible
    await expect(page.locator('#listing-form-container')).toBeVisible({ timeout: 5000 });
    // Placeholder should hide
    await expect(page.locator('#listing-form-placeholder')).toBeHidden();
  });

  // ── show page ─────────────────────────────────────────────────────────────

  test('show page loads with product name and current price', async ({ page }) => {
    await goToShow(page);
    // Price is rendered as "12.00" (price=1200 cents / 100)
    await expect(page.locator('#display-price')).toBeVisible();
    const priceText = await page.locator('#display-price').textContent();
    expect(priceText?.trim()).toContain('12');
    // Action buttons exist
    await expect(page.locator('#btn-update-price')).toBeVisible();
    await expect(page.locator('#btn-toggle-status')).toBeVisible();
  });

  // ── price update (dedicated test as per spec) ─────────────────────────────

  test('price update – opens modal, submits new price, display reflects change', async ({ page }) => {
    await goToShow(page);

    const originalPriceText = (await page.locator('#display-price').textContent())?.trim() ?? '';
    const originalPrice = parseFloat(originalPriceText.replace(/[^\d.]/g, ''));

    // Open price modal
    await page.locator('#btn-update-price').click();
    await expect(page.locator('#update-price-modal')).toBeVisible({ timeout: 5000 });

    // Clear and enter a new price (toggle between 12.00 and 13.00)
    const newPrice = originalPrice === 12 ? '13.00' : '12.00';
    await page.locator('#new-price-input').fill(newPrice);
    await page.locator('#price-update-form button[type="submit"]').click();

    // Modal should close and displayed price should update
    await expect(page.locator('#update-price-modal')).toBeHidden({ timeout: 8000 });
    await expect(page.locator('#display-price')).toContainText(
      newPrice.replace(/\.00$/, ''),
      { timeout: 8000 },
    );

    // Restore original price
    await page.locator('#btn-update-price').click();
    await expect(page.locator('#update-price-modal')).toBeVisible({ timeout: 5000 });
    await page.locator('#new-price-input').fill(String(originalPrice));
    await page.locator('#price-update-form button[type="submit"]').click();
    await expect(page.locator('#update-price-modal')).toBeHidden({ timeout: 8000 });
  });

  test('price update – validation: price exceeding max (999999) shows server error', async ({ page }) => {
    await goToShow(page);
    await page.locator('#btn-update-price').click();
    await expect(page.locator('#update-price-modal')).toBeVisible({ timeout: 5000 });
    // 9999999 passes browser min/step validation but fails server max:999999 rule
    await page.locator('#new-price-input').fill('9999999');
    await page.locator('#price-update-form button[type="submit"]').click();
    // JS error div should appear (class 'hidden' removed by showError())
    await expect(page.locator('#price-update-error')).not.toHaveClass(/hidden/, { timeout: 8000 });
    // Close without saving
    await page.locator('#price-close-btn').click();
  });

  // ── status toggle (pause / unpause) ──────────────────────────────────────

  test('status toggle – pauses an active listing then restores it', async ({ page }) => {
    await goToShow(page);

    // Confirm we start on an active listing
    const toggleBtn = page.locator('#btn-toggle-status');
    await expect(toggleBtn).toBeVisible();
    const initialLabel = (await toggleBtn.textContent())?.trim() ?? '';

    // If already paused (from a previous incomplete test run), activate first
    if (initialLabel.includes('تفعيل')) {
      await toggleBtn.click();
      await page.waitForTimeout(1500);
      await page.reload();
      await page.waitForFunction(() => !!(window as any).LISTING_DETAIL, { timeout: 8000 });
    }

    // Now pause
    const pauseBtn = page.locator('#btn-toggle-status', { hasText: 'إيقاف' });
    await expect(pauseBtn).toBeVisible({ timeout: 5000 });
    await pauseBtn.click();
    // Status badge should update to "موقوف مؤقتاً"
    await expect(page.locator('span', { hasText: 'موقوف' }).first()).toBeVisible({ timeout: 8000 });

    // Restore: activate again
    const activateBtn = page.locator('#btn-toggle-status', { hasText: 'تفعيل' });
    await expect(activateBtn).toBeVisible({ timeout: 5000 });
    await activateBtn.click();
    await expect(page.locator('span', { hasText: 'نشط' }).first()).toBeVisible({ timeout: 8000 });
  });

});
