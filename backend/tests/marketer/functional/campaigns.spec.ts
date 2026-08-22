/**
 * Marketer Phase 2 – My Campaigns (functional)
 *
 * Environment: APP_ENV=local, local dev DB (marketplace_platform @ 127.0.0.1)
 * Auth: yasmin@marketer.com / password123  (marketer_id 019ee664-79b2-7198-a018-6baa582f770c)
 *
 * VERIFIED: "My Campaigns" is NOT read-only. The index has a "+ New Campaign" button that opens
 * a campaign-REQUEST form (page title: "Request a Campaign", submit: "Submit for Review").
 * Submitted campaigns start as status=draft with auto_approve_at=now+36h.
 *
 * ── BUGS FOUND (flag for Joe) ──────────────────────────────────────────────────────────────────
 *
 * BUG 1 — Create page 500 (column not found):
 *   MarketerPortal/CampaignController@create calls:
 *     Vendor::where('status', 'active')->orderBy('store_name')->get(...)
 *   But the vendors table has no `status` column — the correct column is `global_status`.
 *   This causes SQLSTATE[42S22]: Column not found: 1054 Unknown column 'status' in 'where clause'.
 *   The /campaigns/create page 500s for all marketer users.
 *   Fix: change `where('status', 'active')` → `where('global_status', 'active')` in the controller.
 *
 * BUG 2 — campaign_type options mismatch (will matter after BUG 1 is fixed):
 *   create.blade.php <select name="campaign_type"> renders option values:
 *     referral_link | discount_code | product_specific | brand_deal
 *   The controller validates:
 *     in:product_promotion,store_promotion,category_promotion,flash_sale,general
 *   These sets are disjoint — any form submission would always fail validation on campaign_type.
 *   Fix: align blade option values with the controller's allowed list.
 *
 * Tests that depend on a working create page are skipped with a clear note referencing BUG 1.
 * Tests that would depend on a working campaign detail are skipped because no campaigns are seeded.
 */

import { test, expect, type Page } from '@playwright/test';

const INDEX = '/campaigns';
const CREATE = '/campaigns/create';

async function goToIndex(page: Page) {
  await page.goto(INDEX, { waitUntil: 'domcontentloaded' });
  await expect(page).toHaveURL(new RegExp('/campaigns'));
}

/** Returns true when the current page is a server error (500 / exception dump). */
async function isErrorPage(page: Page): Promise<boolean> {
  const body = await page.locator('body').innerText();
  return (
    body.includes('Whoops') ||
    body.includes('Stack trace') ||
    body.includes('SQLSTATE') ||
    body.includes('Column not found') ||
    body.includes('QueryException') ||
    body.includes('Server Error')
  );
}

// ── Index ─────────────────────────────────────────────────────────────────────

test.describe('My Campaigns – index', () => {
  test('page loads with "My Campaigns" heading and "+ New Campaign" button', async ({ page }) => {
    await goToIndex(page);
    // The page-title section renders in both the topbar and the main content h2.
    // Use .first() to avoid strict-mode violation on duplicate h2 elements.
    await expect(page.getByRole('heading', { name: 'My Campaigns' }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: /new campaign/i }).first()).toBeVisible();
  });

  test('shows campaign grid cards OR the empty-state panel — not a blank page', async ({
    page,
  }) => {
    await goToIndex(page);

    const emptyState = page.getByText('No campaigns yet');
    const campaignCards = page.locator('.bg-white.rounded-2xl.border.border-gray-100.p-5');

    const emptyVisible = await emptyState.isVisible();
    const cardsCount = await campaignCards.count();

    expect(
      emptyVisible || cardsCount > 0,
      'Expected either campaign cards or empty-state message — got neither'
    ).toBe(true);

    if (emptyVisible) {
      // Empty-state "Create Campaign" CTA should also be present
      await expect(page.getByRole('link', { name: /create campaign/i })).toBeVisible();
    }
  });

  test('no Laravel error and no raw blade syntax on index', async ({ page }) => {
    await goToIndex(page);
    const body = await page.locator('body').innerText();
    expect(body).not.toContain('Whoops');
    expect(body).not.toContain('{{');
    expect(body).not.toContain('Stack trace');
  });

  test('index page DOM contains no admin_share_pct or product_value_cents', async ({ page }) => {
    await goToIndex(page);
    const content = await page.content();
    expect(content, 'SECURITY: admin_share_pct must not appear in campaigns index DOM').not.toContain('admin_share_pct');
    expect(content, 'SECURITY: product_value_cents must not appear in campaigns index DOM').not.toContain('product_value_cents');
  });
});

// ── Create / Request form ─────────────────────────────────────────────────────

test.describe('Campaign request form', () => {
  /**
   * BUG 1 regression test: /campaigns/create must not 500.
   * Currently it does because the controller queries `status` instead of `global_status`.
   * This test FAILS while BUG 1 is open and PASSES once it is fixed.
   */
  test('BUG 1: /campaigns/create must not return a 500 error (column status vs global_status)', async ({
    page,
  }) => {
    const responses: { url: string; status: number }[] = [];
    page.on('response', (r) => {
      if (!r.url().match(/\.(css|js|png|jpg|svg|ico|woff2?)(\?|$)/)) {
        responses.push({ url: r.url(), status: r.status() });
      }
    });

    await page.goto(CREATE, { waitUntil: 'domcontentloaded' });

    const mainDoc = responses.find((r) => r.url.includes('/campaigns/create'));
    const status = mainDoc?.status ?? (await page.evaluate(() => 0));

    // Check for Whoops/500 page body text as a fallback
    const bodyText = await page.locator('body').innerText();
    const is500 =
      bodyText.includes('Whoops') ||
      bodyText.includes('Stack trace') ||
      bodyText.includes('SQLSTATE') ||
      bodyText.includes('Column not found');

    expect(
      is500,
      'BUG 1: Create page is returning a 500 error due to `status` column not found on vendors table. ' +
        'Fix: change Vendor::where("status", ...) to Vendor::where("global_status", ...) in MarketerPortal/CampaignController@create'
    ).toBe(false);
  });

  test('create form has required fields when page loads without error', async ({ page }) => {
    await page.goto(CREATE, { waitUntil: 'domcontentloaded' });

    if (await isErrorPage(page)) {
      test.skip(true, 'Skipped: create page is 500ing due to BUG 1 (status vs global_status column)');
      return;
    }

    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('select[name="campaign_type"]')).toBeVisible();
    await expect(page.locator('select[name="vendor_id"]')).toBeVisible();
    await expect(page.locator('input[name="starts_at"]')).toBeVisible();
    await expect(page.locator('input[name="ends_at"]')).toBeVisible();
    await expect(page.getByRole('button', { name: /submit for review/i })).toBeVisible();
  });

  test('vendor select is populated with at least one active vendor (requires BUG 1 fix)', async ({
    page,
  }) => {
    await page.goto(CREATE, { waitUntil: 'domcontentloaded' });

    if (await isErrorPage(page)) {
      test.skip(true, 'Skipped: create page is 500ing due to BUG 1 (status vs global_status column)');
      return;
    }

    const options = page
      .locator('select[name="vendor_id"] option')
      .filter({ hasNotText: /select a vendor/i });
    const count = await options.count();
    expect(count, 'Expected at least one vendor option beyond the placeholder').toBeGreaterThan(0);
  });

  /**
   * BUG 2: campaign_type mismatch between blade and controller validation.
   * Only testable after BUG 1 is fixed.
   * Expected: submitting with a blade-provided option value fails server-side validation.
   */
  test('BUG 2: submitting blade campaign_type value (referral_link) returns validation error — disjoint from backend allowed list', async ({
    page,
  }) => {
    await page.goto(CREATE, { waitUntil: 'domcontentloaded' });

    if (await isErrorPage(page)) {
      test.skip(true, 'Skipped: create page is 500ing due to BUG 1; fix that first, then this test will surface BUG 2');
      return;
    }

    await page.locator('input[name="name"]').fill('Test Campaign Bug 2 Check');
    // Select the first non-placeholder option — a blade-provided value like "referral_link"
    await page.locator('select[name="campaign_type"]').selectOption({ index: 1 });
    await page.locator('select[name="vendor_id"]').selectOption({ index: 1 });
    await page.locator('input[name="starts_at"]').fill('2026-07-01');
    await page.locator('input[name="ends_at"]').fill('2026-07-31');

    await page.getByRole('button', { name: /submit for review/i }).click();
    await page.waitForURL('**', { waitUntil: 'domcontentloaded' });

    const afterBody = await page.locator('body').innerText();
    const url = page.url();

    const isRedirectedBack =
      url.includes('/campaigns/create') ||
      afterBody.toLowerCase().includes('campaign type') ||
      afterBody.toLowerCase().includes('invalid') ||
      afterBody.toLowerCase().includes('selected');

    expect(
      isRedirectedBack,
      'BUG 2: Expected validation error on campaign_type — blade option values ' +
        '(referral_link, discount_code, etc.) are not in the backend allowed list ' +
        '(product_promotion, store_promotion, etc.)'
    ).toBe(true);
  });

  test.skip('END-TO-END: campaign request flow — fill form, submit, assert draft status shown', async ({
    page: _page,
  }) => {
    // Blocked by BUG 1 (create page 500s) and BUG 2 (campaign_type mismatch).
    // Once both are fixed:
    //   1. Navigate to /campaigns/create.
    //   2. Fill name, select a valid campaign_type (e.g. "product_promotion"),
    //      select a vendor, set starts_at and ends_at to future dates.
    //   3. Click "Submit for Review".
    //   4. Assert redirect to /campaigns/{id}.
    //   5. Assert status badge shows "Draft".
    //   6. Assert flash: "Campaign submitted for approval."
    //   7. Clean up: delete campaign from DB (no delete route in marketer panel).
  });
});

// ── Campaign detail page ──────────────────────────────────────────────────────

test.describe('Campaign detail – security and field assertions', () => {
  /**
   * No campaigns are seeded for this marketer (0 rows in marketer_campaigns as of 2026-06-22).
   * All detail tests skip gracefully when no campaign is available.
   * To enable: fix BUG 1 + BUG 2 and create a campaign via the UI, or seed one directly in the DB.
   */

  async function getFirstCampaignId(page: Page): Promise<string | null> {
    await page.goto(INDEX, { waitUntil: 'domcontentloaded' });
    const link = page
      .locator('a[href*="/campaigns/"]')
      .filter({ hasNotText: /new campaign|create campaign/i })
      .first();
    if (!(await link.isVisible())) return null;
    const href = await link.getAttribute('href');
    const match = href?.match(/\/campaigns\/([a-z0-9-]+)/);
    return match ? match[1] : null;
  }

  test('campaign detail shows status, commission rate, click/conversion stats — NO sensitive fields', async ({
    page,
  }) => {
    const id = await getFirstCampaignId(page);
    if (!id) {
      test.skip(true, 'No campaigns in DB for this marketer — fix BUG 1 & 2 and create one to enable');
      return;
    }

    await page.goto(`/campaigns/${id}`, { waitUntil: 'domcontentloaded' });

    await expect(page.getByText('Commission Rate')).toBeVisible();
    await expect(page.getByText('Clicks')).toBeVisible();
    await expect(page.getByText('Conversions')).toBeVisible();

    // ── Security: check full page source including hidden elements ──────────
    const content = await page.content();

    expect(content, 'SECURITY: admin_share_pct must not appear in campaign detail DOM').not.toContain('admin_share_pct');
    expect(content, 'SECURITY: product_value_cents must not appear in campaign detail DOM').not.toContain('product_value_cents');
    expect(content, 'SECURITY: marketer_secret_promotions must not appear in campaign detail DOM').not.toContain('marketer_secret_promotions');
  });

  test('WhatsApp link generation: skipped until active+wa-enabled campaign is seeded', async ({
    page: _page,
  }) => {
    test.skip(
      true,
      'WhatsApp panel only renders when status=active AND whatsapp_sharing_enabled=true. ' +
        'No seeded campaign meets both criteria. Once a suitable campaign exists:\n' +
        '  1. Navigate to /campaigns/{id}.\n' +
        '  2. Click "Generate" in the WhatsApp Links section.\n' +
        '  3. Assert result panel shows coupon_code and tracking_url inputs.\n' +
        '  4. Assert "Share on WhatsApp" link has href starting with "https://wa.me/".\n' +
        '  5. Do NOT assert exact QR/coupon content — just that something rendered.'
    );
  });
});
