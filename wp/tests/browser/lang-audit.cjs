const { chromium } = require('playwright');
const PAGES = ['/', '/movie/', '/movie/the-godfather/', '/series/', '/collection/best-2024/', '/?s=test', '/subscribe/', '/about/', '/series/shogun/', '/contact/', '/categories-hub/', '/no-such-page-xyz/', '/anime/', '/schedule/'];
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' });
  const p = await ctx.newPage();
  const seen = new Map();
  for (const u of PAGES) {
    await p.goto('http://localhost:8099' + u, { waitUntil: 'load' });
    await p.waitForTimeout(350);
    const hits = await p.evaluate(() => {
      const chrome = [];
      const content = [];
      const skip = /^(SCRIPT|STYLE|NOSCRIPT|CODE|PRE|KBD)$/;
      const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
      let n;
      while ((n = walker.nextNode())) {
        if (skip.test(n.parentElement.tagName)) continue;
        const t = n.nodeValue.trim();
        if (!t) continue;
        // متن لاتین با حداقل ۳ حرف پشت‌سرهم (نشان‌های تجاری و واحدهای فنی مستثنا می‌شوند)
        const m = t.match(/[A-Za-z]{2,}(?:\s+[A-Za-z]{2,})+/g);
        if (!m) continue;
        const hit = m.join(' | ').slice(0, 90);
        /*
         * «پوسته» در برابر «محتوا».
         *
         * این آزمون دنبال متن لاتینِ جامانده در رابط فارسی است، نه نام
         * آثار. دو جایی که متن لاتین عامدانه است و در مرجع هم هست:
         *   • عنوان اصلی اثر با `dir="ltr"` (مرجع: `<p dir="ltr">` در کارت و هیرو)
         *   • اطلاعیه‌ی نسخه‌ی نمایشی که نام اثر نمونه («Big Buck Bunny»)
         *     را می‌آورد (مرجع: همان جمله در `detail.js`)
         * هر متن لاتین دیگری «پوسته» شمرده می‌شود و باید توضیح داشته باشد.
         */
        ( n.parentElement.closest('[dir="ltr"], .demo-notice' ) ? content : chrome ).push( hit );
      }
      return { chrome: [ ...new Set( chrome ) ], content: [ ...new Set( content ) ] };
    });
    if (hits.chrome.length || hits.content.length) seen.set(u, hits);
  }

  /*
   * فهرست سفید — متن لاتینی که بودنش در رابط فارسی عامدانه است:
   * نام سایت این نصب آزمون، نشان‌های تجاری مرجع و واحدهای فنی.
   * هدف این است که «۱۴ صفحه متن لاتین دارد» به عدد گویا تبدیل شود
   * (چند مورد عامدانه، چند مورد ناخواسته).
   */
  const ALLOWED = [
    /^Koohe Film QA$/,
    /^MADE FOR YOU$/,
    /*
     * ریزسطر لاتینِ برگه‌ی «برنامه پخش» — عیناً از `cinora/schedule.html`
     * (همان‌جا هم `class="eyebrow">SAVE A DATE WITH YOUR STORY`).
     */
    /^(SAVE A )?DATE WITH YOUR STORY$/,
    /^KOOHE PLUS$/,
    /^Full ?HD$/i,
    /^IMDb$/,
    /^4K$/,
    /^ULTRA HD$/,
    /^(\d+(\.\d+)? )?(MB|GB|KB)$/,
    /^\d+(\.\d+)? (MB|GB|KB)$/,
  ];
  const allowed = ( hit ) => ALLOWED.some( ( re ) => re.test( hit ) );

  let unexpected = 0;
  let content = 0;

  for (const [u, hits] of seen) {
    const rows = [
      ...hits.chrome.map( ( h ) => ( allowed( h ) ? '   ✓ (عامدانه، پوسته) ' : '   ✗ (ناخواسته) ' ) + h ),
      ...hits.content.map( ( h ) => '   ✓ (عامدانه، محتوا) ' + h ),
    ];
    unexpected += hits.chrome.filter( ( h ) => ! allowed( h ) ).length;
    content += hits.content.length;
    console.log(u, '\n' + rows.join('\n'));
  }
  console.log('\npages with latin UI text:', seen.size, '/', PAGES.length,
    '| unexpected:', unexpected, '| intentional content:', content);
  await b.close();
})();
