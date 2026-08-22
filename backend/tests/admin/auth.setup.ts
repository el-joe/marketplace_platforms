import { test as setup, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const authFile = path.join(__dirname, '.auth/admin.json');

setup('authenticate as admin', async ({ page }) => {
  const email = process.env.ADMIN_TEST_EMAIL;
  const password = process.env.ADMIN_TEST_PASSWORD;

  if (!email || !password) {
    throw new Error(
      'ADMIN_TEST_EMAIL and ADMIN_TEST_PASSWORD must be set in the environment'
    );
  }

  await page.goto('/login');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('#login-btn').click();

  // Wait until we're off the login page (redirected to dashboard)
  await expect(page).not.toHaveURL(/\/login/);

  await page.context().storageState({ path: authFile });
});
