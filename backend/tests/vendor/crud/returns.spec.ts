/**
 * Vendor Phase 2 – Returns (المرتجعات)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  BLOCKER — vendor-facing returns module does not exist                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Investigation findings:
 *
 * 1. routes/partner.php has NO return-related routes. The partner panel
 *    exposes: orders, listings, inventory, payouts, bank-accounts, flash-sales,
 *    performance, profile, documents, team, support/tickets, disputes,
 *    fulfillment, subscription, marketer-campaigns, marketer-samples, wallet,
 *    ai, claims, warehouses, packaging-supplies.
 *    There is no /returns, /return-requests, or any vendor-side approve/reject
 *    route anywhere in that file.
 *
 * 2. return_requests table EXISTS in the database with a full schema:
 *    id, return_number, order_id, sub_order_id, customer_id, vendor_id,
 *    reason, status (enum: requested/approved/rejected/...), rejection_reason, etc.
 *    So the data model exists but the vendor UI for it does not.
 *
 * 3. The ReturnRequest model is referenced in TWO partner controllers:
 *    - PerformanceController: reads ReturnRequest counts for KPI stats (read-only)
 *    - OrderController: references return-related status strings ('return_requested',
 *      'returned') as non-cancellable order states — but does not expose a
 *      vendor approve/reject action.
 *
 * 4. There are no Blade views under resources/views/partner/ for returns.
 *
 * CONCLUSION: The vendor "respond to return request" flow (approve/reject)
 * has not been implemented in the partner panel yet. The underlying data model
 * and DB schema are ready, but there is no route, controller action, or view.
 *
 * QUESTIONS FOR YOU:
 *   Q1. Should vendor return-approval live at a dedicated /returns route, or
 *       be surfaced inside the existing /orders/{subOrderNumber} detail page
 *       as an inline action when the order status is 'return_requested'?
 *
 *   Q2. How should test data be seeded for return_requests?
 *       Options:
 *       (a) Add a DatabaseSeeder/ReturnRequestSeeder that creates a
 *           PWTEST_-tagged return_request linked to the test vendor's orders.
 *       (b) Use a Laravel Artisan command / API fixture endpoint to create
 *           a return_request in the test setup.
 *       (c) Use a raw DB INSERT in a Playwright beforeAll hook (acceptable
 *           only if APP_ENV=local is confirmed, which it is here).
 *
 *   Q3. The return_requests.status ENUM includes 'approved' and 'rejected'
 *       but not an explicit 'vendor_approved' vs 'admin_approved' distinction.
 *       Does vendor approval immediately set status='approved', or is there
 *       an intermediate state pending admin confirmation?
 *
 * Until these questions are resolved and the vendor returns route is
 * implemented, this spec file cannot contain runnable tests.
 * All tests below are marked skip — they document the INTENDED test shape
 * so they are ready to activate once the feature lands.
 */
import { test, expect } from '@playwright/test';

test.describe('Returns (المرتجعات) — BLOCKED: vendor route not implemented', () => {

  test.skip('BLOCKED: list page loads with return requests for this vendor', async ({ page }) => {
    // Precondition: a return_request fixture with vendor_id matching the test vendor
    // and status='requested' must exist.
    await page.goto('/returns');
    await expect(page).toHaveURL(/\/returns/);
    await expect(page.locator('table, [id*="returns"]')).toBeVisible({ timeout: 8000 });
  });

  test.skip('BLOCKED: vendor can approve a return request', async ({ page }) => {
    // Precondition: seeded return_request with status='requested'.
    // Navigate to the return detail or order detail page.
    // Click the "Approve" button.
    // Assert status badge changes to 'approved' (or equivalent Arabic label).
  });

  test.skip('BLOCKED: vendor can reject a return request with a reason', async ({ page }) => {
    // Precondition: seeded return_request with status='requested'.
    // Click "Reject", fill in rejection_reason, submit.
    // Assert status badge changes to 'rejected'.
    // Assert rejection_reason is displayed on the detail page.
  });

  test.skip('BLOCKED: vendor cannot act on an already-processed return', async ({ page }) => {
    // Precondition: seeded return_request with status='approved' or 'rejected'.
    // Assert approve/reject buttons are absent or disabled.
  });

});
