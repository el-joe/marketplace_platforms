import { test, expect } from '@playwright/test';

test('marketer dashboard smoke test', async ({ page }) => {
  await page.goto('/');

  // Heading
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();

  // Referral link panel — assert shape, not exact code (per-account unique)
  const referralPanel = page.getByText('YOUR REFERRAL LINK', { exact: false });
  await expect(referralPanel).toBeVisible();

  const referralLink = page.locator('a[href*="?ref=MKT-"]');
  await expect(referralLink).toBeVisible();
  const href = await referralLink.getAttribute('href');
  expect(href).toMatch(/\?ref=MKT-[A-Z0-9]+$/);

  // Stat card labels — assert presence, not values (live data)
  await expect(page.getByText('Clicks Today', { exact: false })).toBeVisible();
  await expect(page.getByText('Conversions', { exact: false })).toBeVisible();
  await expect(page.getByText('Pending Earnings', { exact: false })).toBeVisible();
  await expect(page.getByText('Conversion Rate', { exact: false })).toBeVisible();
});
