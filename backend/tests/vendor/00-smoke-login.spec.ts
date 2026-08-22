import { test, expect } from '@playwright/test';

test('vendor dashboard loads after login', async ({ page }) => {
  await page.goto('/dashboard');

  // Sidebar "Home" item should be active — Arabic label: الرئيسية
  // VERIFY: confirm this exact Arabic text matches the live sidebar
  await expect(page.getByText('الرئيسية')).toBeVisible();

  // VERIFY: replace with the actual test vendor store name once
  // VENDOR_TEST_EMAIL / VENDOR_TEST_PASSWORD are provided
  // e.g. await expect(page.getByText('TechZone Electronics')).toBeVisible();
});
