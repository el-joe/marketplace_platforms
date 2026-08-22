/**
 * Carrier Phase 2 — Agents functional tests
 *
 * Prerequisites (idempotent seeders — safe to re-run):
 *   php artisan db:seed --class=ShippingCompanySeeder
 *   php artisan db:seed --class=DeliveryAgentSeeder
 *
 * Test account: tariq@aramex.com / password123  (Aramex Gulf)
 *
 * Negative-control fixtures seeded by DeliveryAgentSeeder:
 *   Platform agents (shipping_company_id IS NULL):
 *     mahmoud@delivery.com, khalifa@delivery.com,
 *     inactive@delivery.com, suspended@delivery.com
 *   Other-company agent (Local Express Oman):
 *     localexpress-rider1@delivery.com
 *
 * CRITICAL ISOLATION RULE (CARR-02): none of the above must ever
 * appear in this supervisor's agents list.  Every list assertion
 * below checks for their absence explicitly.
 *
 * VERIFIED from AgentController::store:
 *   New agents are created with status='active' immediately —
 *   there is no 'pending' state for carrier-created agents.
 *   (Status is forced to 'active' in the store() method.)
 *
 * VERIFIED from routes/carrier.php + views:
 *   There is no edit route for agents (no carrier.agents.edit/update).
 *   The only per-agent mutations are suspend and activate (PATCH).
 *   This test covers suspend/activate as the available "edit" action.
 *
 * VERIFIED from assignments/index.blade.php:
 *   Reassignment is on the Orders page, NOT the Agents page.
 *   No reassignment UI exists here.
 */

import { test, expect, Page } from '@playwright/test';

// ── Known negative-control agent names (must NEVER appear) ──────────────────

const PLATFORM_AGENT_NAMES  = ['Mahmoud Rider', 'Khalifa Driver', 'Inactive Agent', 'Suspended Rider'];
const OTHER_COMPANY_AGENTS  = ['Local Express Rider'];
const ALL_FORBIDDEN_AGENTS  = [...PLATFORM_AGENT_NAMES, ...OTHER_COMPANY_AGENTS];

// Known Local Express agent ID — used for direct-URL cross-company test.
// Sourced from DeliveryAgentSeeder; stable as long as seeder is idempotent.
const LOCAL_EXPRESS_AGENT_ID = '019ee664-82cf-722d-8360-aa855f76245e';

const LARAVEL_ERROR_RE = /whoops|something went wrong|exception|stack trace/i;

async function assertNoLaravelError(page: Page): Promise<void> {
  const body = await page.locator('body').innerText();
  expect(body, 'Laravel error page detected').not.toMatch(LARAVEL_ERROR_RE);
}

/**
 * Standing isolation guard: asserts that no platform agent and no
 * other-company agent name appears anywhere on the current page.
 * Call this after every navigation to /agents.
 */
async function assertIsolation(page: Page): Promise<void> {
  const bodyText = await page.locator('body').innerText();
  for (const name of ALL_FORBIDDEN_AGENTS) {
    expect(bodyText, `ISOLATION BREACH: "${name}" visible in Aramex supervisor's agents view`).not.toContain(name);
  }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test('agents list loads with exactly 2 Aramex agents and dashboard stat matches', async ({ page }) => {
  // Check dashboard stat first
  await page.goto('/');
  await assertNoLaravelError(page);

  const dashboardText = await page.locator('body').innerText();
  expect(dashboardText, 'Dashboard المناديب الكليون stat should show 2').toMatch(/المناديب الكليون[\s\S]*?2|2[\s\S]*?المناديب الكليون/);

  // Navigate to agents list
  await page.goto('/agents');
  await assertNoLaravelError(page);
  await assertIsolation(page);

  // Both Aramex agents present
  await expect(page.getByText('Aramex Rider 1')).toBeVisible();
  await expect(page.getByText('Aramex Rider 2')).toBeVisible();

  // Exactly 2 data rows (tbody tr)
  const rows = page.locator('table tbody tr');
  await expect(rows).toHaveCount(2);
});

test('isolation: platform agents and other-company agents are absent from agents list', async ({ page }) => {
  await page.goto('/agents');
  await assertNoLaravelError(page);
  await assertIsolation(page);

  // Explicit per-name assertions for traceability
  for (const name of PLATFORM_AGENT_NAMES) {
    await expect(page.getByText(name), `Platform agent "${name}" must not appear`).not.toBeVisible();
  }
  for (const name of OTHER_COMPANY_AGENTS) {
    await expect(page.getByText(name), `Other-company agent "${name}" must not appear`).not.toBeVisible();
  }
});

test('create new agent — succeeds, status active immediately, dashboard total increments to 3', async ({ page }) => {
  const tag     = `TEST-CARR-AG-${Date.now()}`;
  const email   = `test.agent.${Date.now()}@carrier-test.invalid`;
  const phone   = `+9665${Math.floor(10000000 + Math.random() * 90000000)}`;

  await page.goto('/agents/create');
  await assertNoLaravelError(page);

  await page.locator('[name="name"]').fill(`Test Agent ${tag}`);
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="phone"]').fill(phone);
  await page.locator('[name="vehicle_type"]').selectOption('motorcycle');
  await page.locator('[name="password"]').fill('password123');
  await page.locator('[name="password_confirmation"]').fill('password123');
  await page.getByRole('button', { name: 'إضافة المندوب' }).click();

  // Should redirect to agents index with success flash
  await expect(page).toHaveURL(/\/agents$/);
  await assertNoLaravelError(page);

  // New agent appears in list
  await expect(page.getByText(`Test Agent ${tag}`)).toBeVisible();

  // VERIFIED: status is 'active' immediately (no 'pending' step)
  // The new agent row should show the active badge
  const agentRow = page.locator('tr').filter({ hasText: `Test Agent ${tag}` });
  await expect(agentRow.getByText('نشط')).toBeVisible();

  // Isolation still holds
  await assertIsolation(page);

  // Dashboard total should now be 3
  await page.goto('/');
  await assertNoLaravelError(page);
  const dashboardText = await page.locator('body').innerText();
  expect(dashboardText, 'Dashboard المناديب الكليون should now show 3').toMatch(/المناديب الكليون[\s\S]*?3|3[\s\S]*?المناديب الكليون/);
});

test('suspend and re-activate an agent — change persists', async ({ page }) => {
  await page.goto('/agents');
  await assertNoLaravelError(page);
  await assertIsolation(page);

  // Find Aramex Rider 1 (status=active → suspend button visible)
  const row = page.locator('tr').filter({ hasText: 'Aramex Rider 1' });
  await expect(row).toBeVisible();

  // Suspend
  const suspendBtn = row.locator('button', { hasText: 'إيقاف' });
  await expect(suspendBtn, 'Suspend button should be visible for active agent').toBeVisible();
  await suspendBtn.click();

  await assertNoLaravelError(page);
  await assertIsolation(page);

  // After suspend: badge becomes موقوف and button flips to تفعيل
  const rowAfterSuspend = page.locator('tr').filter({ hasText: 'Aramex Rider 1' });
  await expect(rowAfterSuspend.getByText('موقوف')).toBeVisible();
  const activateBtn = rowAfterSuspend.locator('button', { hasText: 'تفعيل' });
  await expect(activateBtn).toBeVisible();

  // Re-activate
  await activateBtn.click();
  await assertNoLaravelError(page);
  await assertIsolation(page);

  const rowAfterActivate = page.locator('tr').filter({ hasText: 'Aramex Rider 1' });
  await expect(rowAfterActivate.getByText('نشط')).toBeVisible();
});

test('cross-company agent access by direct URL returns 404, not 403', async ({ page }) => {
  // Attempt to PATCH a suspend action for a Local Express agent while
  // authenticated as the Aramex supervisor.
  // agentForCurrentCompany() scopes by shipping_company_id → firstOrFail()
  // returns 404 on a miss.  A 403 would leak the agent's existence (CARR-02).
  //
  // We need a CSRF token, so we load a page first then use page.evaluate/fetch
  // from within the authenticated browser context.

  await page.goto('/agents');

  const status = await page.evaluate(async (agentId: string) => {
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
    const res = await fetch(`/agents/${agentId}/suspend`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrf,
      },
      body: `_method=PATCH&_token=${encodeURIComponent(csrf)}`,
      redirect: 'manual',
    });
    return res.status;
  }, LOCAL_EXPRESS_AGENT_ID);

  expect(
    status,
    `Expected 404 for cross-company agent suspend, got ${status} — existence must not leak via 403`
  ).toBe(404);
});

test('no reassignment UI on agents page — reassignment lives on orders page', async ({ page }) => {
  await page.goto('/agents');
  await assertNoLaravelError(page);

  // VERIFIED: reassignment is done via POST /assignments/{id}/reassign
  // on the Orders page, not here. Assert there is no reassign form/button.
  const reassignBtn = page.locator('button, a').filter({ hasText: /إعادة تعيين|reassign/i });
  await expect(reassignBtn).toHaveCount(0);
});
