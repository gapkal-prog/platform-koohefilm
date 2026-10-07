const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 400 }, locale: 'fa-IR' });
  const p = await ctx.newPage();
  await p.goto('http://localhost:8099/help/', { waitUntil: 'load', timeout: 60000 });
  await p.waitForTimeout(300);
  const el = await p.$('.pricing-crown');
  await el.screenshot({ path: '/tmp/crown-wp.png' });
  await p.goto('http://localhost:8098/help.html', { waitUntil: 'load', timeout: 60000 });
  await p.waitForTimeout(300);
  const el2 = await p.$('.pricing-crown');
  await el2.screenshot({ path: '/tmp/crown-ref.png' });
  await ctx.close(); await b.close();
})();
