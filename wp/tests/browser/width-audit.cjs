const { chromium } = require('playwright');
const PAGES = ['/', '/movie/', '/series/', '/anime/', '/movie/the-godfather/', '/series/chernobyl/season-1/episode-1/', '/collection/best-2024/', '/?s=test', '/subscribe/', '/about/', '/series/shogun/', '/contact/', '/categories-hub/', '/genre/%D8%AC%D9%86%D8%A7%DB%8C%DB%8C/'];
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' });
  const p = await ctx.newPage();
  for (const u of PAGES) {
    const res = await p.goto('http://localhost:8099' + u, { waitUntil: 'load' }).catch(() => null);
    await p.waitForTimeout(400);
    const r = await p.evaluate(() => {
      const main = document.querySelector('main') || document.body;
      const kids = [...main.children].map((el) => Math.round(el.getBoundingClientRect().width));
      const uniq = [...new Set(kids)];
      return { n: kids.length, widths: uniq, mixed: uniq.length > 1, detail: kids.slice(0, 14) };
    });
    console.log((res ? res.status() : 'ERR').toString().padEnd(4), u.padEnd(40), (r.mixed ? 'MIXED ' : 'ok    ') + r.widths.join(','), ' n=' + r.n);
  }
  await b.close();
})();
