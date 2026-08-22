import { test as setup, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const authFile = path.join(__dirname, '.auth/vendor.json');

setup('authenticate as vendor', async ({ page }) => {
  const email = process.env.VENDOR_TEST_EMAIL;
  const password = process.env.VENDOR_TEST_PASSWORD;

  if (!email || !password) {
    throw new Error(
      'VENDOR_TEST_EMAIL and VENDOR_TEST_PASSWORD must be set in the environment'
    );
  }

  await page.goto('/login');
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('[type="submit"]').click();

  // Wait until we're off the login page (redirected to dashboard)
  await expect(page).not.toHaveURL(/\/login/);

  await page.context().storageState({ path: authFile });
});
