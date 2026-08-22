/**
 * Shared smoke-test helpers used by both the admin and vendor panel specs.
 * Framework-agnostic: depends only on Playwright's Page type.
 */

import type { Page } from '@playwright/test';

const ERROR_PAGE_MARKERS = [
  'Whoops',
  'Server Error',
  '500 | Server Error',
  '404 | Not Found',
  'Exception',
  'ErrorException',
  'Symfony\\Component\\HttpKernel\\Exception',
];

export async function assertNoErrorPage(page: Page): Promise<void> {
  const bodyText = await page.locator('body').innerText().catch(() => '');
  for (const marker of ERROR_PAGE_MARKERS) {
    if (bodyText.includes(marker)) {
      throw new Error(
        `Laravel error page detected — found marker "${marker}" in body`
      );
    }
  }
  const title = await page.title().catch(() => '');
  if (/whoops|server error|404|500/i.test(title)) {
    throw new Error(`Error indicated in page title: "${title}"`);
  }
}

// Static-asset path patterns — 403s on these are seed-data gaps, not code bugs.
const ASSET_EXTENSIONS = /\.(jpg|jpeg|png|gif|svg|webp|avif|css|js|woff|woff2|ttf|eot|ico)(\?.*)?$/i;
const ASSET_PATHS = /^\/(storage|_vite|build|fonts|images)\//i;

export function isAssetUrl(url: string): boolean {
  try {
    const { pathname } = new URL(url);
    return ASSET_EXTENSIONS.test(pathname) || ASSET_PATHS.test(pathname);
  } catch {
    return false;
  }
}
