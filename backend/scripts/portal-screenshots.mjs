#!/usr/bin/env node
/**
 * Portal band screenshots + band map generator.
 *
 * Usage (normally via `php artisan portal:screenshots`, see docs/PORTAL_SCREENSHOTS.md):
 *   node scripts/portal-screenshots.mjs --config=/path/config.json --bands-out=/path/bands.json
 *        [--page=home] [--out=public/images/portal-sections]
 *
 * config.json: { base, country, params:{collection,article,help_article,category,slug},
 *                sections: <config/portal_sections.php as JSON>, pairs: [[page_key, block_key], ...] }
 *
 * What it does, for every admin page in `sections` and both locales (en, ar/RTL) at a 1700px viewport:
 *   1. renders the portal route locally (the live portal is not reachable from the sandbox),
 *   2. splits the rendered page into full-width visual "bands": header, the top-level children of the main
 *      container (wrapper elements are opened up), footers; bands shorter than ~120px are merged into a neighbour,
 *      hidden / zero-height elements are skipped,
 *   3. maps every block (selector from portal_sections.php) to the band that contains it. Blocks stored under
 *      another page key but rendered on this page (route override) become cross-key pairs [page_key, block_key].
 *      Blocks without a selector / not found attach to the preceding band (config order) or to 00-page-settings,
 *   4. screenshots each band, resizes to <= 1200px wide and quantises the PNG (ImageMagick `convert`) to ~200 KB,
 *      writes {out}/{page}/{NN-slug}.{en|ar}.png,
 *   5. writes the band map JSON (--bands-out) that the PHP command turns into config/portal_bands.php.
 * The last stdout line is a JSON summary.
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const arg = (k, d = null) => (process.argv.find(a => a.startsWith(`--${k}=`)) || '').split('=').slice(1).join('=') || d;
const cfgPath = arg('config');
if (!cfgPath) { console.error('--config is required'); process.exit(2); }
const cfg = JSON.parse(fs.readFileSync(cfgPath, 'utf8'));
const only = arg('page');
const OUT = path.resolve(arg('out', 'public/images/portal-sections'));
const BANDS_OUT = arg('bands-out');
const EXE = process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium';
const VIEW_W = 1700, MAX_W = 1200, MAX_BYTES = 200 * 1024, MIN_BAND_H = 120;
const LOCALES = ['en', 'ar'];
const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'portal-bands-'));

const fill = (route, pageKey) => route
  .replace('{country}', cfg.country)
  .replace('{collection}', cfg.params?.collection ?? '')
  .replace('{article}', (pageKey === 'helpcenter' ? cfg.params?.help_article : cfg.params?.article) ?? '')
  .replace('{category}', cfg.params?.category ?? '')
  .replace('{slug}', cfg.params?.slug ?? '');
const unresolved = r => /\{[a-z_]+\}|\/\/|\/undefined/.test(r.replace(/^https?:\/\//, ''));
const slug = s => String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'band';

// ───────────────────────────── ownership: which admin page edits which block ─────────────────────────────
const pages = Object.entries(cfg.sections);
const pageRoute = (pk) => fill(cfg.sections[pk].route, pk);
const owned = {}; // pageKey -> [{pk, block, def, route}]
for (const [pk] of pages) owned[pk] = [];
const warnings = [];

for (const [pk, def] of pages) {
  for (const s of def.sections) {
    let owner = pk;
    const eff = fill(s.route || def.route, pk);
    if (s.route && def.scope === 'page' && eff !== pageRoute(pk)) {
      const hit = pages.find(([ok, od]) => ok !== pk && od.scope === 'page' && pageRoute(ok) === eff);
      if (hit) owner = hit[0];
    }
    owned[owner].push({ pk, block: s.block, def: s, route: eff, pulled: owner !== pk });
  }
}
// seeded pairs unknown to portal_sections.php -> 00-page-settings of their page
const known = new Set(pages.flatMap(([pk, d]) => d.sections.map(s => `${pk}|${s.block}`)));
const orphans = {};
for (const [pk, b] of cfg.pairs || []) if (!known.has(`${pk}|${b}`)) { (orphans[pk] ||= []).push(b); warnings.push(`seeded block ${pk}/${b} missing from portal_sections.php -> 00-page-settings`); }

// routes to render: every distinct effective route -> selectors needed
const routes = new Map(); // route -> Set(selectors)
for (const [pk, list] of Object.entries(owned)) {
  if (only && pk !== only) continue;
  for (const o of list) {
    if (!o.def.selector) continue;
    if (!routes.has(o.route)) routes.set(o.route, new Set());
    routes.get(o.route).add(o.def.selector);
  }
  if (!routes.has(pageRoute(pk)) && list.length) routes.set(pageRoute(pk), new Set());
}

// ───────────────────────────── image helpers ─────────────────────────────
function optimise(rawPng, dest) {
  const src = path.join(TMP, `raw-${process.pid}-${Math.random().toString(36).slice(2)}.png`);
  fs.writeFileSync(src, rawPng);
  let best = null;
  for (const colors of [256, 128, 64, 32, 16]) {
    execFileSync('convert', [src, '-resize', `${MAX_W}x>`, '-strip', '-dither', 'None', '-colors', String(colors), '-define', 'png:compression-level=9', `PNG8:${dest}`]);
    best = fs.statSync(dest).size;
    if (best <= MAX_BYTES) break;
  }
  fs.unlinkSync(src);
  return best;
}

// ───────────────────────────── in-page code ─────────────────────────────
// Runs inside the browser: detects bands, resolves selectors to bands, returns plain data.
const DETECT = ({ selectors, MIN_BAND_H }) => {
  const SKIP = new Set(['SCRIPT', 'STYLE', 'LINK', 'TEMPLATE', 'NOSCRIPT', 'META']);
  const abs = el => { const r = el.getBoundingClientRect(); return { top: r.top + scrollY, bottom: r.bottom + scrollY, h: r.height, w: r.width }; };
  const visible = el => {
    if (SKIP.has(el.tagName)) return false;
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') return false;
    const r = abs(el);
    return r.h > 2 && r.w > 2;
  };
  const kids = el => [...el.children].filter(visible);
  const LOCK = new Set(['HEADER', 'FOOTER', 'NAV']);
  const plainWrapper = el => {
    const cs = getComputedStyle(el);
    return cs.backgroundImage === 'none' && /rgba\(0, 0, 0, 0\)|transparent/.test(cs.backgroundColor) && parseFloat(cs.paddingTop) === 0 && parseFloat(cs.paddingBottom) === 0;
  };
  const flat = (el) => {
    const k = kids(el);
    const tag = el.tagName;
    if (el.dataset.portalSection || LOCK.has(tag) || tag === 'SECTION' || tag === 'ASIDE') return [el];
    if (tag === 'MAIN' || tag === 'ARTICLE') return k.length ? k.flatMap(flat) : [el];
    if (tag === 'DIV' || tag === 'FORM') {
      if (k.length >= 2 && abs(el).h > 700) return k.flatMap(flat);
      if (k.length === 1 && plainWrapper(el)) return flat(k[0]);
    }
    return [el];
  };
  // fixed/sticky chrome (floating buttons, banners) is hidden by the caller; the header was made static.
  const items = kids(document.body).flatMap(flat).filter(visible);
  let bands = items.map(e => { const r = abs(e); return { els: [e], top: r.top, bottom: r.bottom, locked: LOCK.has(e.tagName) }; });
  bands.sort((a, b) => a.top - b.top || a.bottom - b.bottom);
  // merge short bands into a neighbour (prefer the following one)
  for (let guard = 0; guard < 200; guard++) {
    const i = bands.findIndex(b => !b.locked && b.bottom - b.top < MIN_BAND_H);
    if (i < 0) break;
    let j = -1;
    if (i + 1 < bands.length && !bands[i + 1].locked) j = i + 1;
    else if (i > 0 && !bands[i - 1].locked) j = i - 1;
    else if (i + 1 < bands.length) j = i + 1;
    else if (i > 0) j = i - 1;
    if (j < 0) break;
    const a = bands[i], b = bands[j];
    const m = { els: [...b.els, ...a.els], top: Math.min(a.top, b.top), bottom: Math.max(a.bottom, b.bottom), locked: b.locked && b.els.length === 1 && a.els.length === 0 };
    m.locked = false;
    bands.splice(Math.min(i, j), 2, m);
  }
  const bandOf = el => {
    for (let i = 0; i < bands.length; i++) if (bands[i].els.some(b => b === el || b.contains(el))) return i;
    for (let i = 0; i < bands.length; i++) if (bands[i].els.some(b => el.contains(b))) return i;
    return -1;
  };
  const resolved = {};
  const found = [];
  for (const s of selectors) {
    let el = null;
    try { el = document.querySelector(s); } catch { el = null; }
    if (!el || !visible(el)) { resolved[s] = null; continue; }
    const bi = bandOf(el);
    if (bi < 0) { resolved[s] = null; continue; }
    resolved[s] = { band: bi, rank: 0 }; found.push([s, el]);
  }
  found.sort((a, b) => (a[1] === b[1] ? 0 : (a[1].compareDocumentPosition(b[1]) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1)));
  found.forEach(([s], i) => { resolved[s].rank = i; });
  const heading = (b) => {
    for (const e of b.els) {
      const hs = [...(e.matches('h1,h2,h3') ? [e] : []), ...e.querySelectorAll('h1,h2,h3')];
      for (const h of hs) { let t = (h.innerText || h.textContent).replace(/\s+/g, ' ').trim(); const half = t.length / 2; if (t.length % 2 === 1 && t.slice(0, Math.floor(half)) === t.slice(Math.ceil(half))) t = t.slice(0, Math.floor(half)); if (t.length > 2 && visible(h)) return t; }
    }
    return '';
  };
  return {
    height: Math.max(document.documentElement.scrollHeight, document.body.scrollHeight),
    bands: bands.map(b => ({ top: Math.floor(b.top), height: Math.ceil(b.bottom) - Math.floor(b.top), heading: heading(b), tag: b.els.map(e => e.tagName.toLowerCase()).join('+') })),
    resolved,
  };
};


// ───────────────────────────── remote assets (CDNs are unreachable in the sandbox) ─────────────────────────────
// A remote image is served from a local file with the same basename when one exists (public/images, storage,
// frontend/public, resources); otherwise a neutral on-brand placeholder is generated so no broken-image icon is
// rendered. Remote images without a local equivalent are reported in the JSON summary (`remoteImagesMissing`).
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const LOCAL_DIRS = ['public/images', 'public/storage', 'storage/app/public', 'resources/images', '../frontend/public']
  .map(d => path.resolve(ROOT, d)).filter(d => fs.existsSync(d));
const localIndex = new Map(); // basename -> absolute path
(function scan(dirs, depth = 0) {
  for (const d of dirs) {
    if (depth > 6) continue;
    let ents = []; try { ents = fs.readdirSync(d, { withFileTypes: true }); } catch { continue; }
    for (const e of ents) {
      const full = path.join(d, e.name);
      if (e.isDirectory()) { if (!/^(portal-sections|qr|qrcodes|node_modules)$/.test(e.name)) scan([full], depth + 1); }
      else if (/\.(png|jpe?g|svg|webp|gif|avif)$/i.test(e.name) && !localIndex.has(e.name.toLowerCase())) localIndex.set(e.name.toLowerCase(), full);
    }
  }
})(LOCAL_DIRS);
const MIME = { png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', svg: 'image/svg+xml', webp: 'image/webp', gif: 'image/gif', avif: 'image/avif' };
const remoteServed = new Map();   // url -> local path
const remoteMissing = new Set();  // urls shown as generated placeholders
const esc = t => String(t).replace(/[<>&"]/g, '');
function placeholderSvg(url) {
  const name = esc(decodeURIComponent(url.split('?')[0].split('/').pop() || 'image'));
  const flag = url.match(/\/flags\/([a-z]{2})\.svg/i);
  if (flag) return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 18" preserveAspectRatio="none"><rect width="24" height="18" fill="#e5e7eb"/><text x="12" y="12" font-family="system-ui,sans-serif" font-size="7" font-weight="700" text-anchor="middle" fill="#6b7280">${esc(flag[1].toUpperCase())}</text></svg>`;
  if (/\.svg$/i.test(name) && /icon|play|rewind|forward|logo/i.test(name)) return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="#cbd5e1"/><path d="M19 15l14 9-14 9z" fill="#fff"/></svg>`;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" preserveAspectRatio="xMidYMid slice"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e2e8f0"/><stop offset="1" stop-color="#cbd5e1"/></linearGradient></defs><rect width="800" height="600" fill="url(#g)"/><g fill="none" stroke="#94a3b8" stroke-width="10" stroke-linejoin="round"><rect x="300" y="215" width="200" height="150" rx="14"/><circle cx="355" cy="262" r="17"/><path d="M300 345l60-55 45 40 35-30 60 50"/></g></svg>`;
}
async function serveRemote(route) {
  const req = route.request();
  const url = req.url();
  const ext = (url.split('?')[0].match(/\.([a-z0-9]+)$/i) || [])[1]?.toLowerCase();
  if (req.resourceType() !== 'image' && !MIME[ext]) return route.abort();
  const local = localIndex.get(decodeURIComponent(url.split('?')[0].split('/').pop() || '').toLowerCase());
  if (local) { remoteServed.set(url, local); return route.fulfill({ status: 200, contentType: MIME[path.extname(local).slice(1).toLowerCase()] || 'image/png', body: fs.readFileSync(local) }); }
  remoteMissing.add(url);
  return route.fulfill({ status: 200, contentType: 'image/svg+xml', body: placeholderSvg(url) });
}

// ───────────────────────────── render ─────────────────────────────
const browser = await chromium.launch({
  executablePath: fs.existsSync(EXE) ? EXE : undefined,
  args: ['--no-sandbox', '--host-resolver-rules=MAP portal.localhost 127.0.0.1'],
});
const base = cfg.base.replace(/\/$/, '');
const renders = { en: new Map(), ar: new Map() }; // locale -> route -> {status, bands:[{heading, file}], resolved}
const failures = [];

async function prepare(page) {
  await page.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 4000 }).catch(() => {});
  await page.evaluate(() => {
    // chrome that would overlay content: header becomes static (in flow), other fixed/sticky bits are hidden
    const st = document.createElement('style');
    st.textContent = '*{animation:none!important;transition:none!important;scroll-behavior:auto!important}';
    document.head.appendChild(st);
    for (const el of document.querySelectorAll('body *')) {
      const cs = getComputedStyle(el);
      if (cs.position === 'fixed' || cs.position === 'sticky') {
        if (el.tagName === 'HEADER' || el.tagName === 'NAV' || el.querySelector(':scope > nav, :scope > div > nav') && el.tagName === 'HEADER') el.style.setProperty('position', 'static', 'important');
        else if (cs.position === 'fixed') el.style.setProperty('display', 'none', 'important');
        else el.style.setProperty('position', 'static', 'important');
      }
    }
    document.querySelectorAll('img[loading="lazy"]').forEach(i => { i.loading = 'eager'; });
  }).catch(() => {});
  // trigger lazy images / reveal-on-scroll observers
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 70)); }
    window.scrollTo(0, 0);
  });
  await page.evaluate(() => {
    document.querySelectorAll('[class*="opacity-0"],[class*="translate-y"]').forEach(e => { e.style.setProperty('opacity', '1', 'important'); e.style.setProperty('transform', 'none', 'important'); });
  }).catch(() => {});
  await page.waitForTimeout(400);
}

for (const locale of LOCALES) {
  const ctx = await browser.newContext({ viewport: { width: VIEW_W, height: 1000 }, deviceScaleFactor: 1, locale: locale === 'ar' ? 'ar-EG' : 'en-US' });
  const page = await ctx.newPage();
  page.setDefaultTimeout(10000);
  // external CDNs are unreachable in the sandbox: fail fast instead of hanging
  await page.route(/^https?:\/\/(?!(portal\.localhost|127\.0\.0\.1|localhost))/, serveRemote);
  await page.goto(`${base}/language/${locale}`, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.goto(`${base}/country/${cfg.country}`, { waitUntil: 'domcontentloaded' }).catch(() => {});

  for (const [route, selSet] of routes) {
    const rec = { status: 'ok', bands: [], resolved: {} };
    renders[locale].set(route, rec);
    try {
      if (unresolved(route)) throw new Error('unresolved route params');
      const resp = await page.goto(base + route, { waitUntil: 'load', timeout: 25000 });
      if (!resp || resp.status() >= 400) throw new Error(`HTTP ${resp ? resp.status() : 'n/a'}`);
      await prepare(page);
      const info = await page.evaluate(DETECT, { selectors: [...selSet], MIN_BAND_H });
      rec.resolved = info.resolved;
      const used = new Set(Object.values(info.resolved).filter(Boolean).map(r => r.band));
      for (let i = 0; i < info.bands.length; i++) {
        const b = info.bands[i];
        const entry = { heading: b.heading, tag: b.tag, file: null };
        if (used.has(i)) {
          try {
            const raw = await page.screenshot({ type: 'png', fullPage: true, clip: { x: 0, y: b.top, width: VIEW_W, height: Math.max(1, b.height) }, timeout: 20000 });
            const f = path.join(TMP, `${locale}-${slug(route)}-${i}.png`);
            optimise(raw, f);
            entry.file = f;
          } catch (e) { failures.push(`${locale} ${route} band ${i}: ${String(e.message).split('\n')[0]}`); }
        }
        rec.bands.push(entry);
      }
    } catch (e) {
      rec.status = 'failed';
      failures.push(`${locale} ${route}: ${String(e.message || e).split('\n')[0]}`);
    }
  }
  await ctx.close();
}

// placeholder PNG for a band that exists in one locale only
const phPage = await (await browser.newContext()).newPage();
async function placeholder(label_en, label_ar, locale, dest) {
  const ar = locale === 'ar';
  await phPage.setViewportSize({ width: 1200, height: 320 });
  await phPage.setContent(`<html><body style="margin:0"><div id=p style="width:1200px;height:320px;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;background:#f3f4f6;border:2px dashed #9ca3af;font-family:system-ui,sans-serif;color:#374151;direction:${ar ? 'rtl' : 'ltr'}"><div style="font-size:38px;font-weight:700">${ar ? label_ar : label_en}</div><div style="font-size:22px;color:#6b7280">${ar ? label_en : label_ar}</div><div style="font-size:15px;color:#9ca3af">${ar ? 'لا توجد لقطة شاشة — يمكن استبدال هذه الصورة' : 'No screenshot available — replace this image'}</div></div></body></html>`);
  optimise(await phPage.locator('#p').screenshot({ type: 'png' }), dest);
}

// ───────────────────────────── assemble band map ─────────────────────────────
const trunc = (t, n = 60) => (t.length > n ? t.slice(0, n - 1).trimEnd() + '…' : t);
const result = {};
const stats = { captured: 0, placeholders: 0, perPage: {}, settingsBlocks: {} };

for (const [pk, def] of pages) {
  if (only && pk !== only) continue;
  const list = owned[pk];
  if (!list.length && !(orphans[pk] || []).length) continue;
  const pr = pageRoute(pk);
  // order: primary-route blocks first (config order), then other routes in first-seen order
  const routeOrder = [pr, ...new Set(list.map(o => o.route).filter(r => r !== pr))];
  const settings = [];            // blocks that fall into 00-page-settings
  const bandMap = new Map();      // `${route}#${enIndex}` -> {route, idx, blocks:[{o, rank}]}
  const unresolvedQueue = [];
  let lastKey = null;             // band of the preceding block (config order)

  const ordered = [...list].sort((a, b) => (a.pulled - b.pulled)); // own blocks first, stable
  for (const o of ordered) {
    const rec = renders.en.get(o.route);
    const r = o.def.selector && rec && rec.status === 'ok' ? rec.resolved[o.def.selector] : null;
    if (r && rec.bands[r.band]) {
      const k = `${o.route}#${r.band}`;
      if (!bandMap.has(k)) bandMap.set(k, { route: o.route, idx: r.band, blocks: [] });
      bandMap.get(k).blocks.push({ o, rank: r.rank });
      lastKey = k;
    } else if (lastKey) {
      bandMap.get(lastKey).blocks.push({ o, rank: 1e6 + bandMap.get(lastKey).blocks.length });
      if (o.def.selector) failures.push(`${pk}/${o.block}: selector not rendered (${o.def.selector} @ ${o.route}) -> attached to preceding band`);
    } else {
      settings.push(o);
      if (o.def.selector) failures.push(`${pk}/${o.block}: selector not rendered (${o.def.selector} @ ${o.route}) -> 00-page-settings`);
    }
  }
  for (const b of orphans[pk] || []) settings.push({ pk, block: b, def: { label_en: b, label_ar: b }, route: pr });

  // order bands: by route order, then DOM position
  const keys = [...bandMap.keys()].sort((a, b) => {
    const A = bandMap.get(a), B = bandMap.get(b);
    return routeOrder.indexOf(A.route) - routeOrder.indexOf(B.route) || A.idx - B.idx;
  });

  const bands = [];
  if (settings.length) {
    bands.push({ key: '00-page-settings', label_en: 'Page settings & SEO', label_ar: 'إعدادات الصفحة والـ SEO', image: null, blocks: settings.map(o => [o.pk, o.block]) });
    stats.settingsBlocks[pk] = settings.map(o => `${o.pk}/${o.block}`);
  }
  let n = 1;
  for (const k of keys) {
    const bm = bandMap.get(k);
    bm.blocks.sort((a, b) => a.rank - b.rank);
    const first = bm.blocks[0].o;
    const nn = String(n++).padStart(2, '0');
    const bkey = `${nn}-${slug(first.block)}`;
    const enRec = renders.en.get(bm.route);
    const enBand = enRec.bands[bm.idx];
    // matching band in the Arabic render: band of the first block that resolved there
    const arRec = renders.ar.get(bm.route);
    let arIdx = null;
    if (arRec && arRec.status === 'ok') {
      for (const { o } of bm.blocks) { const rr = o.def.selector && arRec.resolved[o.def.selector]; if (rr) { arIdx = rr.band; break; } }
      if (arIdx === null && arRec.bands.length === enRec.bands.length) arIdx = bm.idx;
    }
    const arBand = arIdx !== null ? arRec.bands[arIdx] : null;
    const lbl = (bd, loc) => (bd && bd.heading ? trunc(bd.heading) : first.def[`label_${loc}`]);
    const routeSuffix = bm.route !== pr ? ` (${bm.route})` : '';
    const image = `${pk}/${bkey}`;
    fs.mkdirSync(path.join(OUT, pk), { recursive: true });
    for (const [loc, bd] of [['en', enBand], ['ar', arBand]]) {
      const dest = path.join(OUT, `${image}.${loc}.png`);
      if (bd && bd.file) { fs.copyFileSync(bd.file, dest); stats.captured++; }
      else { await placeholder(first.def.label_en, first.def.label_ar, loc, dest); stats.placeholders++; failures.push(`${loc} ${pk}/${bkey}: no render -> placeholder`); }
    }
    bands.push({
      key: bkey,
      label_en: lbl(enBand, 'en') + routeSuffix,
      label_ar: lbl(arBand, 'ar') + routeSuffix,
      image,
      blocks: bm.blocks.map(({ o }) => [o.pk, o.block]),
    });
  }
  result[pk] = { label_en: def.label_en, label_ar: def.label_ar, route: def.route, scope: def.scope, bands };
  stats.perPage[pk] = bands.length;
}

// generic fallbacks used by the admin view when a band has no PNG at all
const generic = (ar) => `<svg xmlns="http://www.w3.org/2000/svg" width="900" height="280" viewBox="0 0 900 280"><rect width="900" height="280" fill="#f3f4f6" stroke="#9ca3af" stroke-width="2" stroke-dasharray="8 6"/><g font-family="system-ui,sans-serif" text-anchor="middle" fill="#6b7280"><text x="450" y="130" font-size="30" font-weight="700" fill="#374151">${ar ? 'لا توجد لقطة شاشة' : 'No screenshot yet'}</text><text x="450" y="170" font-size="16">${ar ? 'شغّل php artisan portal:screenshots أو استبدل هذه الصورة' : 'Run php artisan portal:screenshots or drop an image in public/images/portal-sections/'}</text></g></svg>`;
fs.mkdirSync(OUT, { recursive: true });
for (const l of LOCALES) fs.writeFileSync(path.join(OUT, `_placeholder.${l}.svg`), generic(l === 'ar'));

if (BANDS_OUT) fs.writeFileSync(BANDS_OUT, JSON.stringify(result, null, 2));
await browser.close();
fs.rmSync(TMP, { recursive: true, force: true });
console.log(JSON.stringify({ captured: stats.captured, placeholders: stats.placeholders, bandsPerPage: stats.perPage, settingsBlocks: stats.settingsBlocks, warnings, failures, remoteImagesServedLocally: [...remoteServed.keys()], remoteImagesMissing: [...remoteMissing].filter(u => !/\/flags\//.test(u)).sort(), remoteFlagsPlaceholder: [...remoteMissing].filter(u => /\/flags\//.test(u)).length }));
