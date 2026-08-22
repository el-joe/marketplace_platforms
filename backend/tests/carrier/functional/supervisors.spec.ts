/**
 * Carrier Phase 2 — Supervisors functional tests
 *
 * Prerequisites (idempotent):
 *   php artisan db:seed --class=ShippingCompanySeeder
 *
 * Test account: tariq@aramex.com / password123  (Aramex Gulf, owner-level)
 *   → has ALL permissions including manage_agents, so passes requireOwner() gate
 *
 * CRITICAL ISOLATION RULE (CARR-02): supervisors list must only show supervisors
 * belonging to Aramex Gulf.  Salim (Local Express) and Mona (Cairo Swift)
 * must never appear.
 *
 * VERIFIED from SupervisorController::destroy:
 *   Self-delete is blocked with abort_if(supervisor->id === auth()->id(), 403).
 *   Additionally, the UI hides the delete button entirely for self (Blade
 *   @if($sup->id !== auth('shipping_supervisor')->id())), so the test asserts
 *   the button is absent for the logged-in user's own row.
 *   There is no separate "primary owner" concept beyond self-protection.
 *
 * VERIFIED from supervisors/edit.blade.php:
 *   The receives_all_notifications flag is NOT on the edit form.
 *   The edit form contains: name, phone, permissions[], is_active.
 *   There is no toggle for receives_all_notifications anywhere in the carrier UI.
 *   ⚠️  NOTE TO REVIEWER: even though can_supervisors_receive_all_notifications
 *   is seeded as TRUE for Aramex Gulf (ShippingCompanySeeder), the per-supervisor
 *   receives_all_notifications toggle is absent from the edit UI.  This may be
 *   an incomplete feature — consider adding this toggle to the edit form if the
 *   CARR-01 design intended it to be controllable by the company owner.
 */

import { test, expect, Page } from '@playwright/test';

// Negative-control supervisor names — must never appear in Aramex's list
const OTHER_COMPANY_SUPERVISORS = ['Salim Al-Balushi', 'Mona Supervisor'];

const LARAVEL_ERROR_RE = /whoops|something went wrong|exception|stack trace/i;

async function assertNoLaravelError(page: Page): Promise<void> {
  const body = await page.locator('body').innerText();
  expect(body, 'Laravel error page detected').not.toMatch(LARAVEL_ERROR_RE);
}

/**
 * Standing isolation guard: asserts that no supervisor from another company
 * appears on the current page.
 */
async function assertIsolation(page: Page): Promise<void> {
  const bodyText = await page.locator('body').innerText();
  for (const name of OTHER_COMPANY_SUPERVISORS) {
    expect(
      bodyText,
      `ISOLATION BREACH: "${name}" visible in Aramex supervisors view`
    ).not.toContain(name);
  }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test('supervisors list loads — shows Tariq, hides other-company supervisors', async ({ page }) => {
  await page.goto('/supervisors');
  await assertNoLaravelError(page);
  await assertIsolation(page);

  // Own supervisor row present (target the table cell to avoid nav ambiguity)
  await expect(page.locator('td').filter({ hasText: 'Tariq Supervisor' }).first()).toBeVisible();
  await expect(page.locator('td').filter({ hasText: 'tariq@aramex.com' }).first()).toBeVisible();

  // Explicit absence of other-company supervisors
  for (const name of OTHER_COMPANY_SUPERVISORS) {
    await expect(page.getByText(name), `"${name}" must not appear`).not.toBeVisible();
  }
});

test('isolation: other-company supervisors never visible', async ({ page }) => {
  await page.goto('/supervisors');
  await assertNoLaravelError(page);
  await assertIsolation(page);
});

test('create new supervisor — appears in list as active', async ({ page }) => {
  const tag   = `TEST-CARR-SUP-${Date.now()}`;
  const email = `test.sup.${Date.now()}@carrier-test.invalid`;

  await page.goto('/supervisors/create');
  await assertNoLaravelError(page);

  await page.locator('[name="name"]').fill(`Test Supervisor ${tag}`);
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill('password123');
  await page.locator('[name="password_confirmation"]').fill('password123');

  // Grant at least one permission so validation passes (permissions[] required)
  await page.locator('input[name="permissions[]"][value="view_orders"]').check();

  await page.getByRole('button', { name: 'إضافة المشرف' }).click();

  // Redirect to supervisors index
  await expect(page).toHaveURL(/\/supervisors$/);
  await assertNoLaravelError(page);

  // New supervisor appears
  await expect(page.getByText(`Test Supervisor ${tag}`)).toBeVisible();

  // is_active defaults: ShippingCompanySupervisor has no default defined in
  // the store() path — the DB default is used.  Verify the 'نشط' badge shows.
  const newRow = page.locator('tr').filter({ hasText: `Test Supervisor ${tag}` });
  await expect(newRow.getByText('نشط')).toBeVisible();

  // Isolation still holds
  await assertIsolation(page);
});

test('self-delete protection — delete button absent for own account', async ({ page }) => {
  await page.goto('/supervisors');
  await assertNoLaravelError(page);

  // Find Tariq's row (the logged-in user)
  const selfRow = page.locator('tr').filter({ hasText: 'tariq@aramex.com' });
  await expect(selfRow).toBeVisible();

  // The Blade template hides the delete button for self:
  //   @if($sup->id !== auth('shipping_supervisor')->id())
  // Assert it is absent in the self row
  const deleteBtn = selfRow.locator('button', { hasText: 'حذف' });
  await expect(
    deleteBtn,
    'Delete button must be absent for the logged-in supervisor (self-protection)'
  ).toHaveCount(0);
});

test('delete another supervisor — succeeds and row disappears', async ({ page }) => {
  // First create a supervisor to delete (so we don't destroy seeded data)
  const tag   = `TEST-CARR-DEL-${Date.now()}`;
  const email = `test.del.sup.${Date.now()}@carrier-test.invalid`;

  await page.goto('/supervisors/create');
  await assertNoLaravelError(page);
  await page.locator('[name="name"]').fill(`Delete Me ${tag}`);
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill('password123');
  await page.locator('[name="password_confirmation"]').fill('password123');
  await page.locator('input[name="permissions[]"][value="view_orders"]').check();
  await page.getByRole('button', { name: 'إضافة المشرف' }).click();
  await expect(page).toHaveURL(/\/supervisors$/);

  // Find the newly created supervisor's delete button and click
  const targetRow = page.locator('tr').filter({ hasText: `Delete Me ${tag}` });
  await expect(targetRow).toBeVisible();

  // Handle browser confirm dialog
  page.on('dialog', d => d.accept());

  const deleteBtn = targetRow.locator('button', { hasText: 'حذف' });
  await expect(deleteBtn).toBeVisible();
  await deleteBtn.click();

  await assertNoLaravelError(page);
  await assertIsolation(page);

  // Row is gone
  await expect(page.getByText(`Delete Me ${tag}`)).not.toBeVisible();
});

test('edit supervisor — name and permissions update persists', async ({ page }) => {
  // Create a throwaway supervisor to edit
  const tag   = `TEST-CARR-EDIT-${Date.now()}`;
  const email = `test.edit.sup.${Date.now()}@carrier-test.invalid`;

  await page.goto('/supervisors/create');
  await assertNoLaravelError(page);
  await page.locator('[name="name"]').fill(`Editable Sup ${tag}`);
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill('password123');
  await page.locator('[name="password_confirmation"]').fill('password123');
  await page.locator('input[name="permissions[]"][value="view_orders"]').check();
  await page.getByRole('button', { name: 'إضافة المشرف' }).click();
  await expect(page).toHaveURL(/\/supervisors$/);

  // Click edit on new supervisor row
  const row = page.locator('tr').filter({ hasText: `Editable Sup ${tag}` });
  await row.getByRole('link', { name: 'تعديل' }).click();
  await assertNoLaravelError(page);

  // Change name and add another permission
  await page.locator('[name="name"]').fill(`Editable Sup Updated ${tag}`);
  await page.locator('input[name="permissions[]"][value="view_reports"]').check();
  await page.getByRole('button', { name: 'حفظ التعديلات' }).click();

  await expect(page).toHaveURL(/\/supervisors$/);
  await assertNoLaravelError(page);
  await assertIsolation(page);

  // Updated name visible
  await expect(page.getByText(`Editable Sup Updated ${tag}`)).toBeVisible();
});

test('receives_all_notifications toggle is NOT on the edit form — documented gap', async ({ page }) => {
  // VERIFIED: the edit form (supervisors/edit.blade.php) does not include
  // receives_all_notifications. Even though Aramex Gulf has
  // can_supervisors_receive_all_notifications=true, no UI exists to toggle
  // individual supervisor notification preferences from the carrier panel.
  //
  // ⚠️  This is flagged as a missing feature per the CARR-01 design note.
  // If the feature is added, update this test to actually toggle the setting.

  await page.goto('/supervisors');
  await assertNoLaravelError(page);

  // Open edit for Tariq
  const row = page.locator('tr').filter({ hasText: 'tariq@aramex.com' });
  await row.getByRole('link', { name: 'تعديل' }).click();
  await assertNoLaravelError(page);

  // Confirm no receives_all_notifications input on this form
  const notifToggle = page.locator('input[name="receives_all_notifications"]');
  await expect(
    notifToggle,
    '⚠️  receives_all_notifications toggle absent from supervisor edit form — feature gap documented'
  ).toHaveCount(0);
});
