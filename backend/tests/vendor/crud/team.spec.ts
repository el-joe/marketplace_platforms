/**
 * Vendor Phase 2 – Team (الفريق)
 *
 * Environment guard: APP_ENV=local, local dev DB.
 * Test vendor: khalid@techzone.com (owner, id 019ee664-6e47-73a4-818a-e46105cd16cb)
 * Seeded team: owner + staff-techzone@vendor.com (staff, active)
 *
 * PWTEST_ members created here are deleted at the end of each test (idempotent).
 *
 * Owner-protection rule (enforced server-side):
 *   - toggleActive on an owner member → HTTP 422, "لا يمكن تعطيل حساب المالك"
 *   - destroy on an owner member       → HTTP 422, "لا يمكن حذف حساب المالك"
 *
 * Note: the invite endpoint (POST /team) sends an email via Mail::send().
 * On local dev the mail driver is typically 'log' or 'null'; the test does
 * not assert email delivery — only that the member row appears in the UI.
 */
import { test, expect, type Page } from '@playwright/test';

const BASE = '/team';
const OWNER_EMAIL = 'khalid@techzone.com';
const TAG = 'PWTEST_TEAM_';

function testEmail(suffix: string) {
  return `pwtest.team.${suffix}@vendor-test.example`;
}

async function goToTeam(page: Page) {
  await page.goto(BASE);
  await expect(page).toHaveURL(new RegExp(BASE));
  await page.waitForLoadState('domcontentloaded');
}

/** Open the invite modal via the "دعوة عضو" button. */
async function openInviteModal(page: Page) {
  await page.locator('button', { hasText: 'دعوة عضو' }).click();
  // Alpine x-show="showInviteModal" — wait for the modal div to become visible
  await expect(page.locator('div[x-show="showInviteModal"]')).toBeVisible({ timeout: 5000 });
}

/** Invite a member and return the email used. */
async function inviteMember(
  page: Page,
  suffix: string,
  role: 'manager' | 'staff' = 'staff',
): Promise<string> {
  const email = testEmail(suffix);
  const name = `${TAG}${suffix}`;
  await openInviteModal(page);
  // Form uses name="name", name="email", name="role" (no "invite_" prefix)
  await page.locator('#form-invite input[name="name"]').fill(name);
  await page.locator('#form-invite input[name="email"]').fill(email);
  await page.locator('#form-invite select[name="role"]').selectOption(role);
  await page.locator('#btn-send-invite').click();
  // Row should appear in the table
  await expect(page.locator('#team-tbody tr', { hasText: email })).toBeVisible({ timeout: 10000 });
  // Ensure the modal is fully closed before returning — its backdrop would intercept
  // subsequent table clicks if still open (JS _x_dataStack close is async and fragile)
  const modal = page.locator('div[x-show="showInviteModal"]');
  if (await modal.isVisible()) {
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
  }
  return email;
}

/** Safety net: press Escape to close the invite modal if it is still covering the page. */
async function closeInviteModalIfOpen(page: Page) {
  try {
    const modal = page.locator('div[x-show="showInviteModal"]');
    if (await modal.isVisible({ timeout: 500 })) {
      await page.keyboard.press('Escape');
      await page.waitForTimeout(300);
    }
  } catch {
    // Page may already be in cleanup; ignore
  }
}

/** Delete a member by email via the delete button. */
async function deleteMember(page: Page, email: string) {
  // Ensure the invite modal is closed before we can interact with table rows
  await closeInviteModalIfOpen(page);
  const row = page.locator('#team-tbody tr', { hasText: email });
  // Register dialog handler BEFORE clicking — confirm() fires synchronously
  page.once('dialog', (dialog) => dialog.accept());
  await row.locator('.btn-delete-member').click();
  await expect(row).toHaveCount(0, { timeout: 8000 });
}

// ── tests ─────────────────────────────────────────────────────────────────────

test.describe('Team', () => {

  // ── list ──────────────────────────────────────────────────────────────────

  test('team page loads with member table and owner row', async ({ page }) => {
    await goToTeam(page);
    await expect(page.locator('#team-tbody')).toBeVisible();
    // Owner row must be present
    await expect(page.locator('#team-tbody tr', { hasText: OWNER_EMAIL })).toBeVisible();
    // Owner role badge
    await expect(page.locator('span', { hasText: 'مالك' }).first()).toBeVisible();
    // "دعوة عضو" button is visible (we are logged in as owner)
    await expect(page.locator('button', { hasText: 'دعوة عضو' })).toBeVisible();
  });

  // ── invite (create) ────────────────────────────────────────────────────────

  test('invite member – valid invite adds a new row to the table', async ({ page }) => {
    await goToTeam(page);
    const email = await inviteMember(page, Date.now().toString());
    try {
      await expect(page.locator('#team-tbody tr', { hasText: email })).toBeVisible();
    } finally {
      await deleteMember(page, email);
    }
  });

  test('invite member – validation: missing email keeps modal open', async ({ page }) => {
    await goToTeam(page);
    await openInviteModal(page);
    await page.locator('#form-invite input[name="name"]').fill(`${TAG}NoEmail`);
    // Leave email blank; form has novalidate so server validation fires
    await page.locator('#form-invite select[name="role"]').selectOption('staff');
    await page.locator('#btn-send-invite').click();
    // Server returns 422; JS shows error in #invite-error and modal stays open
    await expect(page.locator('#invite-error')).not.toHaveClass(/hidden/, { timeout: 6000 });
    await expect(page.locator('div[x-show="showInviteModal"]')).toBeVisible({ timeout: 3000 });
  });

  test('invite member – duplicate email shows server error', async ({ page }) => {
    await goToTeam(page);
    const suffix = `dup_${Date.now()}`;
    const email = await inviteMember(page, suffix);

    // Try to invite the same email again (modal is already closed from inviteMember)
    await openInviteModal(page);
    await page.locator('#form-invite input[name="name"]').fill(`${TAG}DUP`);
    await page.locator('#form-invite input[name="email"]').fill(email);
    await page.locator('#form-invite select[name="role"]').selectOption('staff');
    await page.locator('#btn-send-invite').click();
    // Server returns 422 (unique:vendor_admins,email); JS shows error in #invite-error
    await expect(page.locator('#invite-error')).not.toHaveClass(/hidden/, { timeout: 6000 });

    // Close the error modal, then clean up the created member
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    await deleteMember(page, email);
  });

  // ── deactivate / reactivate ───────────────────────────────────────────────

  test('deactivate – toggling a staff member changes status badge', async ({ page }) => {
    await goToTeam(page);
    const email = await inviteMember(page, `deact_${Date.now()}`);
    try {
      const row = page.locator('#team-tbody tr', { hasText: email });
      const toggleBtn = row.locator('.btn-toggle-active');

      // Member should start as active
      await expect(row.locator('.member-status-badge', { hasText: 'نشط' })).toBeVisible();

      // Deactivate
      await toggleBtn.click();
      await expect(row.locator('.member-status-badge', { hasText: 'معطَّل' })).toBeVisible({ timeout: 6000 });

      // Reactivate
      await toggleBtn.click();
      await expect(row.locator('.member-status-badge', { hasText: 'نشط' })).toBeVisible({ timeout: 6000 });
    } finally {
      await deleteMember(page, email);
    }
  });

  // ── owner-protection rule ─────────────────────────────────────────────────

  test('OWNER PROTECTION: toggle-active on owner row returns 422', async ({ page }) => {
    await goToTeam(page);
    const ownerRow = page.locator('#team-tbody tr', { hasText: OWNER_EMAIL });

    // Owner row must NOT have a .btn-toggle-active button in the UI
    // (the template conditionally renders action buttons only for non-owners)
    const toggleBtn = ownerRow.locator('.btn-toggle-active');
    await expect(toggleBtn).toHaveCount(0);

    // Belt-and-suspenders: call the API directly and assert 422
    // Get the owner member ID from data attribute on the row
    const ownerId = await ownerRow.getAttribute('id'); // id="member-row-{uuid}"
    if (ownerId) {
      const memberId = ownerId.replace('member-row-', '');
      const response = await page.request.post(`/team/${memberId}/toggle-active`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '') },
      });
      expect(response.status()).toBe(422);
      const body = await response.json();
      expect(body.message).toContain('المالك');
    }
  });

  test('OWNER PROTECTION: delete on owner row is blocked in UI and returns 422 via API', async ({ page }) => {
    await goToTeam(page);
    const ownerRow = page.locator('#team-tbody tr', { hasText: OWNER_EMAIL });

    // UI must not show a delete button for the owner
    await expect(ownerRow.locator('.btn-delete-member')).toHaveCount(0);

    // API-level check
    const ownerId = await ownerRow.getAttribute('id');
    if (ownerId) {
      const memberId = ownerId.replace('member-row-', '');
      const csrfToken = await page.evaluate(
        () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
      );
      const response = await page.request.delete(`/team/${memberId}`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      });
      expect(response.status()).toBe(422);
      const body = await response.json();
      expect(body.message).toContain('المالك');
    }
  });

  // ── delete ────────────────────────────────────────────────────────────────

  test('delete member – row is removed from the table', async ({ page }) => {
    await goToTeam(page);
    const email = await inviteMember(page, `del_${Date.now()}`);
    await deleteMember(page, email);
    await expect(page.locator('#team-tbody tr', { hasText: email })).toHaveCount(0);
  });

});
