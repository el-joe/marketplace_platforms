import { test, expect } from '@playwright/test';

test('dashboard loads with heading and GMV card', async ({ page }) => {
  await page.goto('/');

  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
  await expect(page.getByText('Gross Merchandise Value')).toBeVisible();
});
