const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 3, locale: 'fa-IR' });
  const p = await ctx.newPage();
  const dump = async (url, sel, tag) => {
    await p.goto(url, { waitUntil: 'load', timeout: 60000 });
    await p.waitForTimeout(300);
    const info = await p.evaluate((sel) => [...document.querySelectorAll(sel)].map(el => {
      const svg = el.tagName.toLowerCase() === 'svg' ? el : el.querySelector('svg');
      const h = el.closest('h2,h3') || el;
      const title = h.textContent.trim().slice(0, 22);
      if (!svg) return { title, none: true };
      const s = getComputedStyle(svg);
      const r = svg.getBoundingClientRect();
      const path = svg.querySelector('path');
      const ps = getComputedStyle(path);
      return { title, w: +r.width.toFixed(1), stroke: s.stroke, sw: s.strokeWidth, fill: s.fill, pathFill: ps.fill, d: path.getAttribute('d').slice(0, 30) };
    }), sel);
    console.log('===' + tag); info.forEach(i => console.log(' ', JSON.stringify(i)));
    for (let i = 0; i < Math.min(5, info.length); i++) {
      const els = await p.$$(sel);
      if (els[i]) { try { fs.writeFileSync('/tmp/ic-' + tag + i + '.png', await els[i].screenshot()); } catch (e) {} }
    }
  };
  await dump('http://localhost:8098/index.html', '.section-heading', 'ref');
  await dump('http://localhost:8099/', '.manacore-block-head', 'wp');
  await ctx.close(); await b.close();
})();
