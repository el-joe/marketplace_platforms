async (page) => {
  await page.waitForLoadState('networkidle').catch(() => {});
  const h = await page.evaluate(() => document.body.scrollHeight);
  for (let y = 0; y < h + 1200; y += 500) {
    await page.evaluate((y) => window.scrollTo(0, y), y);
    await page.waitForTimeout(200);
  }
  await page.waitForTimeout(1500);
  return await page.evaluate(() => {
    const out = [];
    const sectionOf = (el) => {
      let s = el.closest('section') || el.closest('.swiper')?.parentElement;
      if (!s) return '';
      const hd = s.querySelector('h1,h2,h3');
      return (hd ? hd.textContent.trim() : (s.className || '').toString()).slice(0, 40);
    };
    document.querySelectorAll('img').forEach((img, i) => {
      const r = img.getBoundingClientRect();
      const cs = getComputedStyle(img);
      if (cs.display === 'none' || cs.visibility === 'hidden') return;
      const slide = img.closest('.swiper-slide');
      const nat = img.naturalWidth / (img.naturalHeight || 1);
      const ren = r.width / (r.height || 1);
      const issues = [];
      if (img.complete && img.naturalWidth === 0) issues.push('BROKEN');
      if (r.width < 2 || r.height < 2) issues.push('ZERO');
      if (cs.objectFit === 'fill' && img.naturalWidth > 1 && r.height > 2 && Math.abs(nat - ren) / nat > 0.1) issues.push('DISTORT');
      if (slide) {
        const sr = slide.getBoundingClientRect();
        if (r.width > 2 && sr.width > 0 && r.width < sr.width * 0.5) issues.push('NARROW-IN-SLIDE');
        if (r.bottom > sr.bottom + 2 || r.right > sr.right + 2) issues.push('OVERFLOW-SLIDE');
      }
      if (img.naturalWidth > 1 && r.width > img.naturalWidth * 1.6) issues.push('UPSCALED');
      if (issues.length)
        out.push({ i, issues: issues.join(','), sec: sectionOf(img), w: Math.round(r.width), h: Math.round(r.height), nw: img.naturalWidth, nh: img.naturalHeight, fit: cs.objectFit, slideWH: slide ? Math.round(slide.getBoundingClientRect().width) + 'x' + Math.round(slide.getBoundingClientRect().height) : null, cls: (img.className || '').toString().slice(0, 70), src: (img.currentSrc || img.src).slice(-70) });
    });
    // slides with varying heights within the same swiper
    const swipers = [];
    document.querySelectorAll('.swiper').forEach((sw, k) => {
      const hs = [...sw.querySelectorAll(':scope > .swiper-wrapper > .swiper-slide')].map((s) => Math.round(s.getBoundingClientRect().height));
      const ws = [...sw.querySelectorAll(':scope > .swiper-wrapper > .swiper-slide')].map((s) => Math.round(s.getBoundingClientRect().width));
      swipers.push({ k, sec: sectionOf(sw), n: hs.length, hMin: Math.min(...hs), hMax: Math.max(...hs), wMin: Math.min(...ws), wMax: Math.max(...ws), swH: Math.round(sw.getBoundingClientRect().height) });
    });
    return { url: location.pathname, imgs: document.images.length, issues: out, swipers };
  }).then(r => { const c = {}; r.issues.forEach(x => { if (x.issues === 'NARROW-IN-SLIDE') return; const k = x.issues + ' | ' + x.sec; c[k] = (c[k]||0)+1; }); return c; }).then(async c => ({ counts: c, w3840: await page.evaluate(() => [...document.images].filter(i => /[?&]w=3840/.test(i.currentSrc)).length) }));
}
