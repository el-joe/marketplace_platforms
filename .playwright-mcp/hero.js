async (page) => {
  await page.goto('http://127.0.0.1:3100/uae-en', { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(4000);
  return await page.evaluate(() =>
    [...document.querySelectorAll('.swiper-slide picture img')].map((i) => {
      const r = i.getBoundingClientRect();
      const s = i.closest('.swiper-slide').getBoundingClientRect();
      return {
        fit: getComputedStyle(i).objectFit,
        img: Math.round(r.width) + 'x' + Math.round(r.height),
        slide: Math.round(s.width) + 'x' + Math.round(s.height),
        natural: i.naturalWidth + 'x' + i.naturalHeight,
        visibleOfImage: i.naturalWidth ? Math.round(100 * Math.min(1, (r.width / r.height) / (i.naturalWidth / i.naturalHeight), (r.height / r.width) / (i.naturalHeight / i.naturalWidth)) ) + '%' : null,
      };
    }),
  );
}
