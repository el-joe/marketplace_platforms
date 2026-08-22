import { test as setup, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const authFile = path.join(__dirname, '.auth/marketer.json');

setup('authenticate as marketer', async ({ page }) => {
  const email = process.env.MARKETER_TEST_EMAIL;
  const password = process.env.MARKETER_TEST_PASSWORD;

  if (!email || !password) {
    throw new Error(
      'MARKETER_TEST_EMAIL and MARKETER_TEST_PASSWORD must be set in the environment'
    );
  }

  // VERIFY: confirm /login is the correct path for this panel
  await page.goto('/login');
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('[type="submit"]').click();

  // Wait for redirect away from login — confirmed by dashboard sidebar or referral panel
  await expect(page).not.toHaveURL(/\/login/);

  await page.context().storageState({ path: authFile });
});
