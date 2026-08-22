import { test, expect } from '@playwright/test';

test('delivery home screen renders after auth', async ({ page }) => {
  await page.goto('/');

  // ── Availability toggle ───────────────────────────────────────────────────
  // Alpine.js renders one of these two strings depending on agent's is_available state
  await expect(
    page.getByText("You're Offline").or(page.getByText("You're Online"))
  ).toBeVisible();

  // ── Today stat cards ──────────────────────────────────────────────────────
  await expect(page.getByText('Total')).toBeVisible();
  await expect(page.getByText('Completed')).toBeVisible();
  await expect(page.getByText('Earnings')).toBeVisible();

  // ── Assignments area ──────────────────────────────────────────────────────
  // Either the empty-state panel OR an active assignment card is shown
  const noAssignments = page.getByText('No pending assignments');
  const pendingActions = page.getByText('Pending Actions');
  await expect(noAssignments.or(pendingActions)).toBeVisible();

  // ── Bottom tab bar ────────────────────────────────────────────────────────
  await expect(page.getByText('Home')).toBeVisible();
  await expect(page.getByText('Orders')).toBeVisible();
  await expect(page.getByText('Map')).toBeVisible();
  // 'Earnings' also appears in the stat card — scope to the nav area to avoid
  // ambiguity; the tab bar is inside a <nav> element in the layout
  const nav = page.locator('nav');
  await expect(nav.getByText('Earnings')).toBeVisible();
  await expect(nav.getByText('Profile')).toBeVisible();
});
