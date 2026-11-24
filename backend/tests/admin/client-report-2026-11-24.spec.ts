/**
 * Client test report (24 Nov 2026) — regression E2E for the fixed points.
 *
 * Runs fully in parallel. Auth: the `admin` project's storage state, or E2E_ADMIN_STATE
 * (a storage-state JSON for an Arabic-locale super admin) when set.
 * Data: only the TechZone seller warehouse is edited, and its coordinates are cleared again
 * at the end of that test.
 */
import { test, expect, type Page } from "@playwright/test";

test.describe.configure({ mode: "parallel" });

if (process.env.E2E_ADMIN_STATE) {
    test.use({ storageState: process.env.E2E_ADMIN_STATE });
}
// e.g. E2E_BROWSER_CHANNEL=chrome to run on the installed Google Chrome.
if (process.env.E2E_BROWSER_CHANNEL) {
    test.use({ channel: process.env.E2E_BROWSER_CHANNEL, video: "off" });
}

const SELLER_WAREHOUSE_ID =
    process.env.E2E_SELLER_WAREHOUSE_ID ??
    "01a11201-4533-72d1-84c6-b12e3edc292e";
const VENDOR_ID =
    process.env.E2E_VENDOR_ID ?? "01a111ff-38aa-728a-ad29-60a0ffc9c463";

async function gotoOk(page: Page, path: string) {
    const response = await page.goto(path);
    expect(response?.status(), `${path} status`).toBeLessThan(400);
}

test("#1 warehouse edit keeps the vendor owner and saves GPS coordinates", async ({
    page,
}) => {
    await gotoOk(page, `/warehouses/${SELLER_WAREHOUSE_ID}/edit`);

    const owner = page.locator('select[name="owner_vendor_id"]');
    await expect(owner).not.toHaveValue("");

    await page.locator('input[name="latitude"]').fill("29.3759");
    await page.locator('input[name="longitude"]').fill("47.9774");
    await page
        .locator(
            `form[action$="/warehouses/${SELLER_WAREHOUSE_ID}"] button[type="submit"]`,
        )
        .first()
        .click();

    await expect(page).toHaveURL(
        new RegExp(`/warehouses/${SELLER_WAREHOUSE_ID}$`),
    );
    await expect(page.locator("text=/fix the errors|يرجى تصحيح/i")).toHaveCount(
        0,
    );

    // Reopen: the saved values come back, then restore the original (empty) coordinates.
    await gotoOk(page, `/warehouses/${SELLER_WAREHOUSE_ID}/edit`);
    await expect(page.locator('input[name="latitude"]')).toHaveValue(/29\.37/);
    await page.locator('input[name="latitude"]').fill("");
    await page.locator('input[name="longitude"]').fill("");
    await page
        .locator(
            `form[action$="/warehouses/${SELLER_WAREHOUSE_ID}"] button[type="submit"]`,
        )
        .first()
        .click();
    await expect(page).toHaveURL(
        new RegExp(`/warehouses/${SELLER_WAREHOUSE_ID}$`),
    );
});

for (const { path, table } of [
    { path: "/packaging/requests", table: "packaging-requests-table" },
    { path: "/orders", table: "orders-table" },
]) {
    test(`#13 date filter opens a calendar on ${path}`, async ({ page }) => {
        await gotoOk(page, path);
        await page.locator(`#${table}-filter-toggle`).click();

        const from = page.locator(`#${table}-filter-date_from`);
        await from.click();
        const calendar = page.locator(".flatpickr-calendar.open");
        await expect(calendar).toBeVisible();

        await calendar
            .locator(".flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)")
            .first()
            .click();
        await expect(from).toHaveValue(/^\d{4}-\d{2}-\d{2}$/);
    });
}

test("#17 super admin sees the full vendor profile with the acquisition-agent card", async ({
    page,
}) => {
    await gotoOk(page, `/vendors/${VENDOR_ID}`);
    await expect(page.getByText("أنت تعرض ملف تاجر مقيد")).toHaveCount(0);
    await expect(page.getByText("موظف الاستقطاب").first()).toBeVisible();
});

test("#18 payment gateways page is Arabic and searchable", async ({ page }) => {
    await gotoOk(page, "/payment-gateways");
    const body = page.locator("main, body").first();
    for (const english of [
        "gateway(s) configured",
        "No credentials",
        "✓ Configured",
        "Sandbox",
    ]) {
        await expect(body).not.toContainText(english);
    }

    const search = page.locator("#payment-gateway-search");
    await expect(search).toBeVisible();
    await search.fill("zzzz-no-match");
    await expect(page.locator("#payment-gateway-search-empty")).toBeVisible();
    await expect(
        page.locator("[data-gateway-search-card]:visible"),
    ).toHaveCount(0);
    await search.fill("");
    await expect(page.locator("#payment-gateway-search-empty")).toBeHidden();
});

test("#18 customers list action buttons are Arabic", async ({ page }) => {
    await gotoOk(page, "/customers");
    const firstRowActions = page.locator("table tbody tr").first();
    await expect(firstRowActions).toBeVisible();
    await expect(
        page.locator("table tbody a.btn-secondary").first(),
    ).toHaveText("عرض");
    await expect(page.locator("table tbody")).not.toContainText(
        /\b(View|Suspend|Ban)\b/,
    );
});

test("#18 #20 admins list shows Arabic role names, dates and a local-time tooltip", async ({
    page,
}) => {
    await gotoOk(page, "/admins");
    const tbody = page.locator("table tbody");
    await expect(tbody.locator("tr").first()).toBeVisible();
    await expect(tbody).toContainText("المشرف العام");
    await expect(tbody).not.toContainText("super_admin");
    await expect(tbody).not.toContainText(
        /\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d/,
    );

    const tooltip = tbody
        .locator("span[title]")
        .filter({ hasText: "منذ" })
        .first();
    if (await tooltip.count()) {
        await expect(tooltip).not.toHaveAttribute("title", /T\d{2}:\d{2}.*Z$/);
    }
});

for (const { path, target } of [
    { path: "/blog/categories", target: "#category-tree" },
    { path: "/portal-content", target: "#portal-content-pages" },
    { path: "/classifieds/categories", target: "#category-tree" },
]) {
    test(`#18 client-side search box filters ${path}`, async ({ page }) => {
        await gotoOk(page, path);
        const search = page.locator(`[data-client-search="${target}"]`);
        await expect(search).toBeVisible();
        await search.fill("zzzz-no-match");
        await expect(
            page.locator(
                `${target} > tr:not([data-client-search-ignore]):visible`,
            ),
        ).toHaveCount(0);
        await search.fill("");
    });
}

test("#19 role editor offers the new standalone section permissions", async ({
    page,
}) => {
    await gotoOk(page, "/roles/create");
    const body = page.locator("body");
    for (const group of [
        "المحتوى — المدونة",
        "المحتوى — مركز المساعدة",
        "النظام — المدن",
        "النظام — العملات",
        "التسويق — النشرة البريدية",
        "المحتوى — إعدادات التذييل",
        "المحتوى — البث المباشر",
    ]) {
        await expect(body).toContainText(group);
    }
});

test("#23 classified categories explain where Travel & Tourism comes from", async ({
    page,
}) => {
    await gotoOk(page, "/classifieds/categories");
    await expect(
        page.getByText("«السفر والسياحة» ليست فئة إعلانات مبوبة"),
    ).toBeVisible();
    await expect(
        page.getByRole("link", { name: "إدارة وكالات السفر" }),
    ).toBeVisible();
});

test.describe("portal in Arabic", () => {
    test.use({ locale: "ar" });

    test("#24 Arabic text never gets letter-spacing (portal fulfillment)", async ({
        page,
    }) => {
        await page.goto("http://portal.noon.loc/fulfillment");
        await expect(page.locator("html")).toHaveAttribute("lang", "ar");
        const tracked = page.locator('[class*="tracking-"]').first();
        await expect(tracked).toBeAttached();
        const spacing = await tracked.evaluate(
            (el) => getComputedStyle(el).letterSpacing,
        );
        expect(spacing).toBe("normal");
    });
});
