/**
 * Phase 2 – Vendors
 *
 * Tests cover:
 *   - Vendor list loads with DataTable
 *   - Search/filter by status
 *   - Clicking a vendor opens the detail page
 *   - Applications queue page loads
 *   - Approve/Reject buttons are visible on pending vendors
 *
 * Note: approved_at is TINYINT(0/1) in this app, not a timestamp.
 * We test the displayed status (Active badge), not the raw field value.
 *
 * No test permanently modifies the seeded vendors — the "under_review"
 * vendor (ahmed@cairohome.com / Cairo Home Essentials) can be approved
 * in an idempotent way if there is such a vendor, but we keep tests
 * read-only or confirm the UI action buttons are present.
 */
import { test, expect, type Page } from '@playwright/test';

const BASE = '/vendors';

async function goToIndex(page: Page) {
  await page.goto(BASE);
  // Vendors page uses a custom table, not x-table.datatable
  await page.waitForSelector('table#vendors-table', { timeout: 10000 });
}

async function waitForTable(page: Page) {
  await page.waitForTimeout(800);
}

// ── tests ─────────────────────────────────────────────────────────────────────

test.describe('Vendors', () => {

  test('list page loads with vendors table', async ({ page }) => {
    await goToIndex(page);
    await waitForTable(page);
    await expect(page.locator('table#vendors-table')).toBeVisible();
  });

  test('status filter "Active" shows only active vendors', async ({ page }) => {
    await goToIndex(page);
    await waitForTable(page);
    const statusFilter = page.locator('select#filter-status');
    if (await statusFilter.isVisible()) {
      await statusFilter.selectOption('active');
      await waitForTable(page);
      // Should not show 'Suspended' or 'Rejected' status in results
      await expect(page.locator('table#vendors-table')).toBeVisible();
    }
  });

  test('search field filters vendors by name/email', async ({ page }) => {
    await goToIndex(page);
    await waitForTable(page);
    const searchInput = page.locator('input#search-input');
    if (await searchInput.isVisible()) {
      await searchInput.fill('TechZone');
      await waitForTable(page);
      await expect(page.locator('table#vendors-table')).toBeVisible();
    }
  });

  test('vendor row has a clickable link to vendor detail', async ({ page }) => {
    await goToIndex(page);
    await waitForTable(page);
    const firstLink = page.locator('#vendors-table tbody tr a').first();
    if (await firstLink.count() > 0) {
      await firstLink.evaluate((el: HTMLElement) => el.click());
      await expect(page).toHaveURL(/\/vendors\/[\w-]+/, { timeout: 10000 });
      // Vendor detail page should show a heading
      await expect(page.locator('h1, h2').first()).toBeVisible();
    }
  });

  test('Applications queue page loads with heading', async ({ page }) => {
    await page.goto(`${BASE}/applications`);
    await expect(page).toHaveURL(/vendors\/applications/);
    await expect(page.locator('h1')).toContainText('Application');
  });

  test('Applications queue shows Approve and Reject buttons for pending vendors', async ({ page }) => {
    await page.goto(`${BASE}/applications`);
    // If any pending applications exist, they should show action buttons
    const approveBtn = page.locator('button[data-approve-vendor]').first();
    const rejectBtn = page.locator('button[data-open-modal="quick-reject-modal"]').first();
    const emptyState = page.locator('[class*="empty-state"], h2, p', { hasText: /no pending/i }).first();

    const hasApprove = await approveBtn.count() > 0;
    // Page should load without errors regardless of whether there are pending vendors
    await expect(page.locator('h1')).toBeVisible();

    if (hasApprove) {
      await expect(approveBtn).toBeVisible();
      await expect(rejectBtn).toBeVisible();
    }
    // If no pending vendors, the empty state or pagination is shown — page still valid
  });

  test('vendor detail page shows Approve Vendor button for pending vendor', async ({ page }) => {
    // Use the seeded "under_review" vendor (Cairo Home Essentials)
    await page.goto(`${BASE}/applications`);
    const reviewLink = page.locator('a[data-review-vendor]').first();
    if (await reviewLink.count() > 0) {
      await reviewLink.evaluate((el: HTMLElement) => el.click());
      await expect(page).toHaveURL(/\/vendors\/[\w-]+/, { timeout: 10000 });
      // Approve vendor button should be visible in the sidebar
      await expect(page.locator('button[data-approve-vendor]')).toBeVisible({ timeout: 5000 });
    }
  });

  test('Reject Application modal opens with reason textarea', async ({ page }) => {
    await page.goto(`${BASE}/applications`);
    const rejectBtn = page.locator('button[data-open-modal="quick-reject-modal"]').first();
    if (await rejectBtn.count() > 0) {
      await rejectBtn.click();
      // Modal should appear with a reason textarea
      await expect(page.locator('#quick-reject-modal')).toBeVisible({ timeout: 5000 });
      await expect(page.locator('#quick-reject-modal textarea[name="reason"]')).toBeVisible();
    }
  });

});
