import { test, expect } from '@playwright/test';

test('carrier dashboard smoke test', async ({ page }) => {
  await page.goto('/');

  // Company name in header
  // VERIFY: update if the seeded test account belongs to a different carrier company
  await expect(page.getByText('Aramex Gulf', { exact: true }).first()).toBeVisible();

  // Welcome heading
  await expect(page.getByText(/مرحباً/)).toBeVisible();

  // All 5 top nav items (targeted as links to avoid collision with stat card labels)
  await expect(page.getByRole('link', { name: 'الرئيسية' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'المناديب' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'الطلبات' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'المشرفون' })).toBeVisible();
  await expect(page.getByText('خروج')).toBeVisible();

  // 5 stat card labels (assert labels, not live numeric values)
  await expect(page.getByText('مسلّم اليوم')).toBeVisible();
  await expect(page.getByText('قيد التنفيذ')).toBeVisible();
  await expect(page.getByText('طلبات معلّقة')).toBeVisible();
  await expect(page.getByText('المناديب النشطون')).toBeVisible();
  await expect(page.getByText('المناديب الكليون')).toBeVisible();
});
