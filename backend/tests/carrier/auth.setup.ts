import { test as setup, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const authFile = path.join(__dirname, '.auth/carrier.json');

setup('authenticate as carrier supervisor', async ({ page }) => {
  const email = process.env.CARRIER_TEST_EMAIL;
  const password = process.env.CARRIER_TEST_PASSWORD;

  if (!email || !password) {
    throw new Error(
      'CARRIER_TEST_EMAIL and CARRIER_TEST_PASSWORD must be set in the environment'
    );
  }

  // VERIFY: confirm /login is the correct path for this panel
  await page.goto('/login');
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('[type="submit"]').click();

  // Wait for redirect to dashboard — confirmed by active "الرئيسية" nav item
  await expect(page).not.toHaveURL(/\/login/);
  await expect(page.getByText('الرئيسية')).toBeVisible();

  await page.context().storageState({ path: authFile });
});
