import { test as setup, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const authFile = path.join(__dirname, '.auth/delivery.json');

setup('authenticate as delivery agent', async ({ page }) => {
  const phone = process.env.DELIVERY_TEST_PHONE;
  const password = process.env.DELIVERY_TEST_PASSWORD;

  if (!phone || !password) {
    throw new Error(
      'DELIVERY_TEST_PHONE and DELIVERY_TEST_PASSWORD must be set in the environment'
    );
  }

  // Login form uses name="phone" — confirmed from login.blade.php and AuthController
  await page.goto('/login');
  await page.locator('[name="phone"]').fill(phone);
  await page.locator('[name="password"]').fill(password);
  await page.locator('[type="submit"]').click();

  // Dashboard availability toggle is rendered by Alpine.js — wait for it
  await expect(page.getByText("You're Offline").or(page.getByText("You're Online"))).toBeVisible({
    timeout: 10000,
  });

  await page.context().storageState({ path: authFile });
});
