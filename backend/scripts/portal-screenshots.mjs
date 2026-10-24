#!/usr/bin/env node
/**
 * Portal section screenshots.
 *
 * Usage (normally via `php artisan portal:screenshots`, see docs/PORTAL_SCREENSHOTS.md):
 *   node scripts/portal-screenshots.mjs --config=/path/to/config.json [--page=home] [--out=public/images/portal-sections]
 *
 * config.json: { base, host?, country, params: {collection, article, category, slug}, sections: <config/portal_sections.php as JSON> }
 *
 * For every page + block in `sections` and both locales it loads the portal page at 1440px,
 * screenshots the block's `selector`, downsizes to <= MAX_W px wide (and <= MAX_H tall) and writes
 * {out}/{page}/{block}.{en|ar}.png. Anything that cannot be captured gets a labelled placeholder PNG.
 * Prints a JSON summary { captured, placeholders, failures[] } on the last line.
 */
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const arg = (k, d = null) => (process.argv.find(a => a.startsWith(`--${k}=`)) || '').split('=').slice(1).join('=') || d;
const cfgPath = arg('config');
if (!cfgPath) { console.error('--config is required'); process.exit(2); }
const cfg = JSON.parse(fs.readFileSync(cfgPath, 'utf8'));
const only = arg('page');
const OUT = path.resolve(arg('out', 'public/images/portal-sections'));
const EXE = process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium';
const MAX_W = 900, MAX_H = 1400, MAX_BYTES = 120 * 1024;
const LOCALES = ['en', 'ar'];

const fill = (route, pageKey) => route
  .replace('{country}', cfg.country)
  .replace('{collection}', cfg.params?.collection ?? '')
  .replace('{article}', (pageKey === 'helpcenter' ? cfg.params?.help_article : cfg.params?.article) ?? '')
  .replace('{category}', cfg.params?.category ?? '')
  .replace('{slug}', cfg.params?.slug ?? '');

const browser = await chromium.launch({
  executablePath: fs.existsSync(EXE) ? EXE : undefined,
  args: ['--no-sandbox', '--host-resolver-rules=MAP portal.localhost 127.0.0.1'],
});

// A tiny page used to resize PNGs through <canvas> (no extra npm packages needed).
const toolPage = await (await browser.newContext()).newPage();
await toolPage.setContent('<html><body></body></html>');
const phPage = await (await browser.newContext()).newPage();

async function shrink(buf) {
  const b64 = buf.toString('base64');
  for (const w of [MAX_W, 760, 640, 520]) {
    const out = await toolPage.evaluate(async ({ b64, w, MAX_H }) => {
      const img = new Image(); img.src = 'data:image/png;base64,' + b64; await img.decode();
      const s = Math.min(1, w / img.width);
      let W = Math.round(img.width * s), H = Math.round(img.height * s);
      const cropH = Math.min(H, MAX_H);
      const c = document.createElement('canvas'); c.width = W; c.height = cropH;
      const g = c.getContext('2d'); g.imageSmoothingQuality = 'high';
      g.drawImage(img, 0, 0, W, H);
      return c.toDataURL('image/png').split(',')[1];
    }, { b64, w, MAX_H });
    const o = Buffer.from(out, 'base64');
    if (o.length <= MAX_BYTES || w === 520) return o;
  }
}

async function placeholder(label_en, label_ar, locale, page) {
  const ar = locale === 'ar';
  const html = `<html><body style="margin:0"><div id=p style="width:900px;height:280px;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;background:#f3f4f6;border:2px dashed #9ca3af;font-family:system-ui,sans-serif;color:#374151;direction:${ar ? 'rtl' : 'ltr'}">
  <div style="font-size:15px;color:#6b7280;letter-spacing:.05em">${page}</div>
  <div style="font-size:34px;font-weight:700">${ar ? label_ar : label_en}</div>
  <div style="font-size:20px;color:#6b7280">${ar ? label_en : label_ar}</div>
  <div style="font-size:13px;color:#9ca3af">${ar ? 'لا توجد لقطة شاشة — يمكن استبدال هذه الصورة' : 'No screenshot available — replace this image'}</div></div></body></html>`;
  await phPage.setViewportSize({ width: 900, height: 280 });
  await phPage.setContent(html);
  return phPage.locator('#p').screenshot({ type: 'png' });
}

async function reveal(el) {
  await el.evaluate((n) => {
    for (let x = n; x && x !== document.body; x = x.parentElement) {
      x.removeAttribute('hidden'); x.removeAttribute('x-cloak');
      const cs = getComputedStyle(x);
      if (cs.display === 'none') x.style.setProperty('display', 'block', 'important');
      if (cs.visibility === 'hidden') x.style.setProperty('visibility', 'visible', 'important');
      if (cs.opacity === '0') x.style.setProperty('opacity', '1', 'important');
    }
  });
}

const summary = { captured: 0, placeholders: 0, failures: [] };
const cache = {}; // `${locale}|${url}` -> ok

for (const locale of LOCALES) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1, locale: locale === 'ar' ? 'ar-EG' : 'en-US' });
  const base = cfg.base.replace(/\/$/, '');
  const page = await ctx.newPage();
  page.setDefaultTimeout(8000);
  // external CDNs are unreachable in the sandbox: fail fast instead of hanging
  await page.route(/^https?:\/\/(?!(portal\.localhost|127\.0\.0\.1|localhost))/, r => r.abort());
  await page.goto(`${base}/language/${locale}`, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.goto(`${base}/country/${cfg.country}`, { waitUntil: 'domcontentloaded' }).catch(() => {});

  let current = null;
  for (const [pageKey, def] of Object.entries(cfg.sections)) {
    if (only && pageKey !== only) continue;
    fs.mkdirSync(path.join(OUT, pageKey), { recursive: true });
    for (const s of def.sections) {
      const file = path.join(OUT, pageKey, `${s.block}.${locale}.png`);
      const route = fill(s.route || def.route, pageKey);
      let buf = null, why = '';
      try {
        if (!s.selector) throw new Error('no DOM selector');
        if (/\{[a-z]+\}|\/\//.test(route.replace(/^https?:\/\//, '')) || route.includes('/undefined')) throw new Error('unresolved route params');
        if (current !== route) {
          const resp = await page.goto(base + route, { waitUntil: 'load', timeout: 20000 }).catch(e => { throw e; });
          if (!resp || resp.status() >= 400) throw new Error(`HTTP ${resp ? resp.status() : 'n/a'} for ${route}`);
          current = route;
          await page.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
          await page.waitForLoadState('networkidle', { timeout: 4000 }).catch(() => {});
          // sticky/fixed chrome would overlap element screenshots
          await page.addStyleTag({ content: '*{animation:none!important;transition:none!important} [class*="sticky"],[class*="fixed"]{position:static!important}' }).catch(() => {});
          // trigger lazy images
          await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); } window.scrollTo(0, 0); });
          await page.waitForTimeout(300);
        }
        const el = page.locator(s.selector).first();
        if (await el.count() === 0) throw new Error(`selector not found: ${s.selector}`);
        if (!(await el.isVisible())) await reveal(el);
        const box = await el.boundingBox();
        if (!box || box.width < 40 || box.height < 20) throw new Error('empty box');
        const raw = await el.screenshot({ type: 'png', timeout: 10000 });
        buf = await shrink(raw);
        summary.captured++;
      } catch (e) {
        why = String(e.message || e).split('\n')[0];
        buf = await placeholder(s.label_en, s.label_ar, locale, `${pageKey} / ${s.block}`);
        summary.placeholders++;
        summary.failures.push(`${locale} ${pageKey}/${s.block}: ${why}`);
        current = current; // keep page state
      }
      fs.writeFileSync(file, buf);
    }
  }
  await ctx.close();
}

// Generic fallbacks used by the admin view when a section has no PNG at all.
const generic = (ar) => `<svg xmlns="http://www.w3.org/2000/svg" width="900" height="280" viewBox="0 0 900 280"><rect width="900" height="280" fill="#f3f4f6" stroke="#9ca3af" stroke-width="2" stroke-dasharray="8 6"/><g font-family="system-ui,sans-serif" text-anchor="middle" fill="#6b7280"><text x="450" y="130" font-size="30" font-weight="700" fill="#374151">${ar ? 'لا توجد لقطة شاشة' : 'No screenshot yet'}</text><text x="450" y="170" font-size="16">${ar ? 'شغّل php artisan portal:screenshots أو استبدل هذه الصورة' : 'Run php artisan portal:screenshots or drop an image in public/images/portal-sections/'}</text></g></svg>`;
for (const l of LOCALES) fs.writeFileSync(path.join(OUT, `_placeholder.${l}.svg`), generic(l === 'ar'));

await browser.close();
console.log(JSON.stringify(summary));
