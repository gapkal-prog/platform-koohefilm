const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  for (const w of [1440, 980, 768, 390]) {
    for (const [label, url] of [['help', 'http://localhost:8098/help.html'], ['privacy', 'http://localhost:8098/privacy.html']]) {
      const ctx = await b.newContext({ viewport: { width: w, height: 900 }, locale: 'fa-IR' });
      const p = await ctx.newPage();
      await p.goto(url, { waitUntil: 'load', timeout: 60000 });
      await p.waitForTimeout(400);
      const m = await p.evaluate(() => {
        const box = (el) => { if (!el) return null; const r = el.getBoundingClientRect(); return { w: Math.round(r.width), h: Math.round(r.height), x: Math.round(r.x), y: Math.round(r.y) }; };
        const cs = (el, props) => { if (!el) return null; const s = getComputedStyle(el); const o = {}; props.forEach(k => o[k] = s[k]); return o; };
        const mix = (el, props) => el ? Object.assign(box(el), cs(el, props)) : null;
        const page = document.querySelector('.info-page');
        const intro = document.querySelector('.info-intro');
        const crown = document.querySelector('.pricing-crown');
        const h1 = document.querySelector('.info-intro h1');
        const ip = document.querySelector('.info-intro > p:not(.eyebrow)');
        const eyebrow = document.querySelector('.info-intro > .eyebrow');
        const list = document.querySelector('.faq-list');
        const item = document.querySelector('.faq-item');
        const btn = document.querySelector('.faq-item > button');
        const ans = document.querySelector('.faq-item > p');
        const closedAns = [...document.querySelectorAll('.faq-item')].find(i => !i.classList.contains('open'));
        const legal = document.querySelector('.legal-content');
        const legalSec = document.querySelector('.legal-content > section');
        const legalH2 = document.querySelector('.legal-content h2');
        const legalP = document.querySelector('.legal-content p');
        return {
          page: mix(page, ['paddingTop', 'paddingBottom']),
          breadcrumb: box(document.querySelector('.breadcrumb')),
          intro: mix(intro, ['maxWidth', 'paddingTop', 'textAlign']),
          crown: mix(crown, ['borderTopLeftRadius', 'transform', 'marginBottom']),
          h1: mix(h1, ['fontSize', 'lineHeight', 'marginTop']),
          ip: mix(ip, ['fontSize', 'lineHeight', 'marginTop']),
          eyebrow: cs(eyebrow, ['fontSize']),
          list: mix(list, ['borderTopLeftRadius', 'marginTop']),
          itemCount: document.querySelectorAll('.faq-item').length,
          item: mix(item, ['borderBottomWidth', 'backgroundColor']),
          btn: mix(btn, ['fontSize', 'lineHeight', 'padding', 'justifyContent', 'gap']),
          ans: mix(ans, ['fontSize', 'lineHeight', 'padding', 'color']),
          closedAnsDisplay: closedAns ? getComputedStyle(closedAns.querySelector('p')).display : 'n/a',
          closedAnsH: closedAns ? Math.round(closedAns.querySelector('p').getBoundingClientRect().height) : null,
          openCount: document.querySelectorAll('.faq-item.open').length,
          legal: mix(legal, ['maxWidth', 'marginTop']),
          legalSec: mix(legalSec, ['paddingTop', 'paddingBottom', 'borderBottomWidth']),
          legalH2: cs(legalH2, ['fontSize', 'marginBottom']),
          legalP: mix(legalP, ['fontSize', 'lineHeight']),
        };
      });
      console.log(w, label, JSON.stringify(m));
      await ctx.close();
    }
  }
  await b.close();
})();
