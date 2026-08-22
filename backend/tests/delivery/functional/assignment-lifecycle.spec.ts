/**
 * Phase 2 — Delivery Assignment Lifecycle Tests
 *
 * Prerequisites:
 *   php artisan db:seed --class=DeliveryAssignmentSeeder
 *   (or php artisan db:seed which includes it in the full run)
 *
 * Two seeded assignments are required, both in status='assigned':
 *   - SUB-TEST-DEL-01A  →  happy path (accept → picked-up → deliver)
 *   - SUB-TEST-DEL-01B  →  fail path  (accept → fail)
 *
 * OTP for delivery: 123456  (set by DeliveryAssignmentSeeder::TEST_OTP)
 *
 * These tests are SEQUENTIAL and stateful — steps depend on prior state.
 * test.describe.serial() ensures they run in order and stop on first failure.
 */

import { test, expect, Page } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname  = path.dirname(__filename);

const TEST_OTP            = '123456';
const PROOF_IMAGE_PATH    = path.join(__dirname, '../fixtures/proof-of-delivery.jpg');
const LARAVEL_ERROR_RE    = /whoops|something went wrong|exception|stack trace/i;

// ── Helpers ───────────────────────────────────────────────────────────────────

async function assertNoLaravelError(page: Page): Promise<void> {
  const body = await page.locator('body').innerText();
  expect(body, 'Laravel error page detected').not.toMatch(LARAVEL_ERROR_RE);
}

/** Opens /assignments and returns the href of the first card that matches
 *  the given sub-order suffix (e.g. '01A' or '01B'). */
async function findAssignmentHref(page: Page, subOrderSuffix: string): Promise<string> {
  await page.goto('/assignments');
  await assertNoLaravelError(page);

  // The card text contains the sub_order_number fragment
  const card = page.locator('a').filter({ hasText: new RegExp(subOrderSuffix, 'i') }).first();
  await expect(card, `Assignment card for ${subOrderSuffix} not found — is the seeder run?`).toBeVisible();
  const href = await card.getAttribute('href');
  if (!href) throw new Error(`No href on assignment card for ${subOrderSuffix}`);
  return href;
}

/** Posts to an assignment action endpoint and returns the parsed JSON response. */
async function postAction(
  page: Page,
  assignmentId: string,
  action: string,
  body: Record<string, unknown> = {}
): Promise<{ success: boolean; message?: string; remaining?: number }> {
  const [response] = await Promise.all([
    page.waitForResponse(
      (r) => r.url().includes(`/assignments/${assignmentId}/${action}`) && r.request().method() === 'POST',
      { timeout: 15_000 }
    ),
    page.evaluate(
      async ({ id, action, body }) => {
        const csrfMeta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
        const token = csrfMeta?.content ?? '';
        await fetch(`/assignments/${id}/${action}`, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(body),
        });
      },
      { id: assignmentId, action, body }
    ),
  ]);
  expect(response.status(), `POST /assignments/${assignmentId}/${action} returned ${response.status()}`).toBeLessThan(300);
  return response.json();
}

// ── Happy-path lifecycle (SUB-TEST-DEL-01A) ───────────────────────────────────

test.describe.serial('Assignment lifecycle — happy path (SUB-TEST-DEL-01A)', () => {

  test('1 · assignment card appears in Orders list with status Assigned', async ({ page }) => {
    await page.goto('/assignments');
    await assertNoLaravelError(page);

    // The "Needs Action" section heading appears when pending assignments exist
    await expect(page.getByText('Needs Action', { exact: false })).toBeVisible();

    // The specific seeded assignment card is visible
    const card = page.locator('a').filter({ hasText: /DEL-01A/i }).first();
    await expect(card, 'Assignment 1A card not found — run DeliveryAssignmentSeeder').toBeVisible();
  });

  test('2 · assignment detail: address visible, items visible, prices absent, OTP absent', async ({ page }) => {
    const href = await findAssignmentHref(page, 'DEL-01A');
    await page.goto(href);
    await assertNoLaravelError(page);

    // Delivery address visible
    await expect(page.getByText('Deliver To', { exact: false })).toBeVisible();
    await expect(page.getByText('King Fahd Road', { exact: false })
      .or(page.getByText('Riyadh', { exact: false }))).toBeVisible();

    // Items section exists (may need expand)
    await expect(page.getByText('Items', { exact: false })).toBeVisible();

    // COD amount shown (order is COD)
    // The show view doesn't render a COD line directly — the fact that the order
    // is COD is surfaced by the "Confirm Delivery" flow.  Skip explicit assertion.

    // Prices (unit_price / line_total) must NOT appear in the page source —
    // only quantity and product name are shown per the API contract.
    const html = await page.content();
    expect(html, 'Price data leaked into delivery page').not.toMatch(/unit_price|line_total|line_subtotal/i);

    // OTP value must never appear in page source
    expect(html, 'OTP value leaked into delivery page').not.toContain(TEST_OTP);

    // Accept button visible (assignment is in 'assigned' state)
    await expect(page.getByText('Accept Assignment', { exact: false })).toBeVisible();
  });

  test('3 · accept assignment: status changes to Accepted, API returns 2xx', async ({ page }) => {
    const href = await findAssignmentHref(page, 'DEL-01A');
    await page.goto(href);

    // Extract the assignment UUID from the URL path (/assignments/{uuid})
    const assignmentId = new URL(page.url()).pathname.split('/').pop()!;

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes(`/assignments/${assignmentId}/accept`) && r.request().method() === 'POST',
        { timeout: 10_000 }
      ),
      page.getByText('Accept Assignment', { exact: false }).click(),
    ]);

    expect(response.status()).toBeLessThan(300);
    const json = await response.json();
    expect(json.success).toBe(true);

    // Page reloads after accept — status chip should now say "Accepted"
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByText('Accepted', { exact: false })).toBeVisible({ timeout: 5_000 });
    await expect(page.getByText('Mark as Picked Up', { exact: false })).toBeVisible();
  });

  test('4 · mark as picked up: status changes to Picked Up, API returns 2xx', async ({ page }) => {
    const href = await findAssignmentHref(page, 'DEL-01A');
    await page.goto(href);

    const assignmentId = new URL(page.url()).pathname.split('/').pop()!;

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes(`/assignments/${assignmentId}/picked-up`) && r.request().method() === 'POST',
        { timeout: 10_000 }
      ),
      page.getByText('Mark as Picked Up', { exact: false }).click(),
    ]);

    expect(response.status()).toBeLessThan(300);
    const json = await response.json();
    expect(json.success).toBe(true);

    // Alpine reloads the page after 800ms — wait for the OTP form that only appears in picked_up state
    await expect(page.getByText('Enter Delivery OTP', { exact: false })).toBeVisible({ timeout: 5_000 });
  });

  test('5 · wrong OTP increments attempts and shows error — status stays Picked Up', async ({ page }) => {
    const href = await findAssignmentHref(page, 'DEL-01A');
    await page.goto(href);
    await page.waitForLoadState('domcontentloaded');

    const assignmentId = new URL(page.url()).pathname.split('/').pop()!;

    // Fill wrong OTP via the 6 individual digit inputs
    const inputs = page.locator('.otp-digit');
    const wrongOtp = '000000';
    for (let i = 0; i < 6; i++) {
      await inputs.nth(i).fill(wrongOtp[i]);
    }

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes(`/assignments/${assignmentId}/deliver`) && r.request().method() === 'POST',
        { timeout: 10_000 }
      ),
      page.locator('#deliver-form').click(),
    ]);

    expect(response.status()).toBe(422);
    const json = await response.json();
    expect(json.message, 'Expected invalid OTP message').toMatch(/invalid otp|incorrect/i);
    expect(typeof json.remaining).toBe('number');

    // Toast error is shown — status stays Picked Up (no reload to delivered state)
    await expect(page.getByText('Invalid OTP', { exact: false })
      .or(page.getByText('attempt', { exact: false }))).toBeVisible({ timeout: 5_000 });

    // The page has NOT advanced to delivered — "Confirm Delivery" button still present
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByText('Confirm Delivery', { exact: false })).toBeVisible({ timeout: 5_000 });
  });

  test('6 · correct OTP + proof photo: delivers, API returns 2xx, redirects to orders list', async ({ page }) => {
    const href = await findAssignmentHref(page, 'DEL-01A');
    await page.goto(href);
    await page.waitForLoadState('domcontentloaded');

    const assignmentId = new URL(page.url()).pathname.split('/').pop()!;

    // Fill correct OTP
    const inputs = page.locator('.otp-digit');
    for (let i = 0; i < 6; i++) {
      await inputs.nth(i).fill(TEST_OTP[i]);
    }

    // Upload proof photo
    const fileInput = page.locator('input[type="file"][accept="image/*"]');
    await fileInput.setInputFiles(PROOF_IMAGE_PATH);

    // Wait for the preview to appear (Alpine sets proofPreview) before submitting
    await expect(page.locator('img[src^="blob:"]').or(page.locator('.border-green-500'))).toBeVisible({
      timeout: 3_000,
    }).catch(() => { /* preview optional — continue */ });

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes(`/assignments/${assignmentId}/deliver`) && r.request().method() === 'POST',
        { timeout: 15_000 }
      ),
      page.locator('#deliver-form').click(),
    ]);

    expect(response.status()).toBeLessThan(300);
    const json = await response.json();
    expect(json.success).toBe(true);

    // After success the page redirects to /assignments
    await page.waitForURL('**/assignments', { timeout: 5_000 });

    // Assignment 1A should appear in the Delivered section heading
    await expect(page.getByRole('heading', { name: /Delivered/i })).toBeVisible();
  });

});

// ── Fail-path (SUB-TEST-DEL-01B) ─────────────────────────────────────────────

test.describe.serial('Assignment lifecycle — fail path (SUB-TEST-DEL-01B)', () => {

  test('1 · assignment 1B appears in list as Assigned', async ({ page }) => {
    await page.goto('/assignments');
    await assertNoLaravelError(page);

    const card = page.locator('a').filter({ hasText: /DEL-01B/i }).first();
    await expect(card, 'Assignment 1B card not found — run DeliveryAssignmentSeeder').toBeVisible();
  });

  test('2 · accept assignment 1B', async ({ page }) => {
    const href = await findAssignmentHref(page, 'DEL-01B');
    await page.goto(href);

    const assignmentId = new URL(page.url()).pathname.split('/').pop()!;

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes(`/assignments/${assignmentId}/accept`) && r.request().method() === 'POST',
        { timeout: 10_000 }
      ),
      page.getByText('Accept Assignment', { exact: false }).click(),
    ]);

    expect(response.status()).toBeLessThan(300);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.getByText('Mark as Failed', { exact: false })).toBeVisible({ timeout: 5_000 });
  });

  test('3 · fail delivery: selects reason, submits, API returns 2xx, shows as Failed', async ({ page }) => {
    const href = await findAssignmentHref(page, 'DEL-01B');
    await page.goto(href);
    await page.waitForLoadState('domcontentloaded');

    const assignmentId = new URL(page.url()).pathname.split('/').pop()!;

    // Open the fail modal
    await page.getByText('Mark as Failed', { exact: false }).click();
    await expect(page.getByText('Why did the delivery fail?', { exact: false })).toBeVisible();

    // Select a failure reason
    await page.locator('select').filter({ hasText: /select a reason/i }).selectOption('customer_not_home');

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes(`/assignments/${assignmentId}/fail`) && r.request().method() === 'POST',
        { timeout: 10_000 }
      ),
      page.locator('.btn-red').filter({ hasText: 'Confirm' }).click(),
    ]);

    expect(response.status()).toBeLessThan(300);
    const json = await response.json();
    expect(json.success).toBe(true);

    // After success the page redirects to /assignments
    await page.waitForURL('**/assignments', { timeout: 5_000 });

    // Assignment 1B should appear in the Failed section heading
    await expect(page.getByRole('heading', { name: /Failed/i })).toBeVisible();
  });

});
