const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  for (const w of [1440, 980]) {
    const ctx = await b.newContext({ viewport: { width: w, height: 900 }, locale: 'fa-IR' });
    const p = await ctx.newPage();
    for (const [tag, url] of [['ref', 'http://localhost:8098/article.html'], ['wp', 'http://localhost:8099/dune-world/']]) {
      await p.goto(url, { waitUntil: 'load', timeout: 60000 });
      await p.waitForTimeout(400);
      const m = await p.evaluate(() => {
        const sb = document.querySelector('.article-sidebar');
        if (!sb) return null;
        const s = getComputedStyle(sb);
        const r = sb.getBoundingClientRect();
        const kids = [...sb.children].map(el => {
          const k = el.getBoundingClientRect();
          const ks = getComputedStyle(el);
          return { tag: el.tagName + '.' + el.className.split(' ')[0], w: Math.round(k.width), h: Math.round(k.height), y: Math.round(k.top), x: Math.round(k.left), mt: ks.marginTop, mb: ks.marginBottom, gap: ks.gap };
        });
        return { display: s.display, flexDir: s.flexDirection, gap: s.gap, w: Math.round(r.width), h: Math.round(r.height), x: Math.round(r.left), y: Math.round(r.top), pos: s.position, top: s.top, kids };
      });
      console.log(w, tag, JSON.stringify(m));
    }
    await ctx.close();
  }
  await b.close();
})();
