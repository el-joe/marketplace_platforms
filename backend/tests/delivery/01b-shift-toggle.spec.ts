/**
 * Online/Offline availability toggle smoke test.
 *
 * The toggle on the dashboard calls PUT /availability (route: delivery.availability.update).
 * There is no /shift/start endpoint — the API is PUT /availability with body
 * { is_available: true|false }.
 *
 * Alpine.js renders the label via x-text:
 *   isAvailable ? '🟢 You\'re Online' : '⚫ You\'re Offline'
 *
 * Cleanup: the test ends with the agent in Offline state (is_available = false)
 * so a lingering "online" agent doesn't interfere with dispatch in shared envs.
 */
import { test, expect } from '@playwright/test';

test('availability toggle: offline → online → offline, calls PUT /availability', async ({ page }) => {
  await page.goto('/');

  // ── Determine starting state ─────────────────────────────────────────────
  const offlineLabel = page.getByText("You're Offline");
  const onlineLabel  = page.getByText("You're Online");

  await expect(offlineLabel.or(onlineLabel)).toBeVisible({ timeout: 10000 });

  const startedOnline = await onlineLabel.isVisible();

  // ── Helper: click toggle and assert the PUT /availability request ────────
  const clickToggleAndAssertRequest = async (expectAvailable: boolean) => {
    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('/availability') && r.request().method() === 'PUT',
        { timeout: 10000 }
      ),
      page.locator('.toggle-track').click(),
    ]);

    expect(response.status(), `PUT /availability returned ${response.status()}`).toBeLessThan(300);

    const json = await response.json();
    expect(json.success).toBe(true);
    expect(json.is_available).toBe(expectAvailable);
  };

  // ── If agent started online, go offline first so test always starts from Offline ──
  if (startedOnline) {
    await clickToggleAndAssertRequest(false);
    await expect(offlineLabel).toBeVisible({ timeout: 5000 });
  }

  // ── Assert initial Offline state ─────────────────────────────────────────
  await expect(offlineLabel).toBeVisible();

  // ── Toggle ON ─────────────────────────────────────────────────────────────
  await clickToggleAndAssertRequest(true);
  await expect(onlineLabel).toBeVisible({ timeout: 5000 });

  // ── Toggle OFF (cleanup — leave agent Offline) ────────────────────────────
  await clickToggleAndAssertRequest(false);
  await expect(offlineLabel).toBeVisible({ timeout: 5000 });
});
