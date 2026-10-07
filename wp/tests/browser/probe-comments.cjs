const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const dump = async (page, tag) => {
    const m = await page.evaluate(() => {
      const sec = document.querySelector('.comments-section');
      if (!sec) return { missing: true };
      const form = sec.querySelector('form');
      return {
        secClass: sec.className,
        formId: form ? form.id : null,
        formClass: form ? form.className : null,
        fields: form ? [...form.querySelectorAll('input, textarea, select, button[type=submit]')].map(e => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.name ? '[' + e.name + ']' : '') + (e.type ? ':' + e.type : '')) : [],
        hasSpoiler: !!sec.querySelector('#comment-spoiler'),
        spoilerLabel: (sec.querySelector('label[for=comment-spoiler]') || {}).textContent,
        listItems: sec.querySelectorAll('.comment-item, li.comment, .comment-list > li').length,
        sortSelect: !!sec.querySelector('#comment-sort'),
        emptyText: (sec.querySelector('.comment-empty') || {}).textContent,
      };
    });
    console.log(tag, JSON.stringify(m, null, 1));
  };
  let ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fa-IR' });
  let p = await ctx.newPage();
  await p.goto('http://localhost:8099/dune-world/', { waitUntil: 'load', timeout: 60000 });
  await p.waitForTimeout(400);
  await dump(p, 'WP guest');
  await p.goto('http://localhost:8098/article.html', { waitUntil: 'load', timeout: 60000 });
  await p.waitForTimeout(400);
  await dump(p, 'REF');
  await ctx.close();

  ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fa-IR', storageState: undefined });
  p = await ctx.newPage();
  await p.goto('http://localhost:8099/wp-login.php', { waitUntil: 'load', timeout: 60000 });
  await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'admin');
  await Promise.all([p.waitForNavigation({ timeout: 60000 }), p.click('#wp-submit')]);
  await p.goto('http://localhost:8099/dune-world/', { waitUntil: 'load', timeout: 60000 });
  await p.waitForTimeout(400);
  await dump(p, 'WP logged-in');
  await ctx.close(); await b.close();
})();
