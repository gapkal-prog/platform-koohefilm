# ممیزی برگه‌های باقی‌مانده‌ی مرجع Cinora و نقشه‌ی نگاشت به معماری وردپرس

**دامنه:** پنج برگه‌ی مرجعی که هنوز هم‌ارز اختصاصی ندارند: `live.html`، `cast.html`، `magazine.html`،
`help.html`، `privacy.html` (به‌همراه بازبینی `article.html`).
**روش:** هر برگه‌ی مرجع جزء‌به‌جزء خوانده شد (HTML، CSS با متن پرس‌وجوهای رسانه‌ای، و JS)، سپس هر جزء به یک
سازه‌ی وردپرس نگاشت شد. قابلیت‌ها و وابستگی‌های موجود پروژه در نگاشت **حفظ** شده‌اند؛ هیچ‌جا سازه‌ی موازی
ساخته نمی‌شود. پیاده‌سازی مرحله‌ای و هر مرحله با آزمون واقعی پذیرفته می‌شود.

---

## ۱) `live.html` — «پخش زنده»

### ۱.۱ ساختار مرجع
```
main.page-container.live-page
├── nav.breadcrumb                      (سینورا › پخش زنده)
├── div.page-title-row
│   ├── div → p.eyebrow (WATCH TOGETHER) · h1 + span.accent · p.muted
│   └── span.live-clock → svg + b#live-clock («--:--») + small (تهران)
├── div.live-layout                     (grid: minmax(0,1fr) 305px / 1100px→255px / 980px→228px)
│   ├── section.live-player
│   │   ├── div.live-video-frame → video#live-video[poster,autoplay,loop,muted,controls,playsinline]
│   │   │                          span.on-air(>span)+«LIVE DEMO»
│   │   │                          div.video-error#video-error.hidden → p + button#retry-video
│   │   ├── div.live-now-info → span.live-channel-logo (◉) · small · h2#live-title · p#live-subtitle
│   │   │                       div.live-player-actions → button#mute-video · button#fullscreen-video
│   │   ├── div.demo-notice → span(ⓘ) + p
│   │   └── section.live-program → header.section-heading>h2 («قاب‌های امروز»)
│   │                              div.program-grid → ۳ قاب (small · h3 · span◷ زمان)، یکی `.active`
│   └── aside.live-channels
│       ├── div.section-heading → h2 (◉ کانال‌ها) + span («۳ کانال آزمایشی»)
│       ├── div#channel-list          ← با JS از آرایه‌ی سه‌تایی `live.js` ساخته می‌شود
│       └── div.live-sidebar-note → h3 · p (با <br>) · a («کشف فیلم‌ها ‹»)
```
**CSS مرجع:** `.live-layout`، `.live-player`، `.live-video-frame`، `.on-air` (+`animation:pulse`)،
`.live-now-info`، `.live-channel-logo`، `.live-player-actions`، `.channel-card(.active)`، `.channel-icon`،
`.channel-on-air`، `.live-sidebar-note`، `.live-program`، `.program-grid(>div.active)`، `.live-clock`.
**JS مرجع (`assets/js/live.js`، ۲٫۳KB):** سه کانال سخت‌کدشده در JS (`id/name/title/subtitle/poster/quality`)،
`render()` بازسازی کامل فهرست/عنوان/پوستر/ویدئو، ساعت تهران با `Intl.DateTimeFormat('fa-IR',{timeZone:'Asia/Tehran'})`
هر ۳۰ ثانیه، کلید صدا، تمام‌صفحه، `onerror` → نمایش `.video-error` و `#retry-video`.

### ۱.۲ نگاشت به وردپرس
| جزء مرجع | سازه‌ی وردپرس |
|---|---|
| کانال‌ها (آرایه‌ی JS) | **نوع محتوای تازه `channel`** (`class-post-types.php`: برچسب «کانال/کانال‌ها»، `rewrite: slug_channel`، `rest_base: channels`، بدون آرشیو) — داده‌ی واقعی و کامل از پیشخوان |
| کیفیت/زیرعنوان/ویدئو/پوستر/نشانه | **فراداده‌ی ویرایش‌پذیر** در تب تازه‌ی «کانال» (`class-meta.php`) با همان فهرست آیکون‌های پروژه (`heading_icon_paths()`) |
| `main.live-page` + breadcrumb + `page-title-row` | **بلوک مشترک تازه `manacore/page-intro`** (چیدمان split/centered/magazine، ریزسطر، عنوان، واژه‌ی تأکیدی، توضیح، آیکون، ساعت زنده) — همین بلوک در سه برگه‌ی دیگر هم به‌کار می‌رود تا تکرار نسازیم |
| `section.live-player` | **بلوک `manacore/live-player`** (کانال انتخابی، برچسب «پخش»، یادداشت، و شبکه‌ی برنامه با کوئری واقعی) |
| `div#channel-list` (JS) | **بلوک `manacore/live-channels`**: فهرست از کوئری `channel`؛ هر کارت یک **پیوند واقعی** `?channel=slug` (کار می‌کند بدون JS) و JS آن را به جابه‌جایی درجا ارتقا می‌دهد |
| `live-sidebar-note` | ویژگی‌های همان بلوک (عنوان، متن، برچسب/مقصد پیوند) |
| «قاب‌های امروز» | **کوئری واقعی از زمان‌بندی موجود پروژه**: فراداده‌ی `manacore_air_day` = امروز + `manacore_air_time`، مرتب‌شده بر حسب ساعت؛ قابِ «در حال پخش» از نزدیک‌ترین زمان گذشته به اکنون محاسبه می‌شود (هیچ داده‌ی نمایشی سخت‌کد نمی‌شود) |
| ساعت تهران | همان `live-clock`، اما با `clockTimezone`/`clockLabel` قابل تنظیم در ویرایشگر |
| رفتار `live.js` | **ماژول تازه در `front.js`** + نقطه‌ی REST `manacore/v1/live/<slug>` (پاسخ پاک‌سازی‌شده و کش‌شدنی)؛ بدون JS همان پیوند سرور-محور کار می‌کند |

**قرارداد اجرایی:** `wp/tests/browser/live-parity.cjs` — هندسه‌ی محاسبه‌شده در ۱۴۴۰/۹۸۰/۳۹۰ در برابر
`cinora/live.html`، رفتار (تعویض کانال بدون بازخوانی، تغییر نشانی، کلید صدا، ساعت فارسی)، حالت‌های خالی و
`axe`. **قاعده‌ی امنیتی:** خروجی REST فقط داده‌ی همان کانال منتشرشده را برمی‌گرداند.

**انحراف‌های آگاهانه و مستند:** (۱) نشانه‌ی کانال به‌جای نویسه‌ی یونیکد (◉/✦/♛) از مجموعه‌ی SVG پروژه
می‌آید تا هر کانال از پیشخوان نشانه‌ی خودش را داشته باشد (هندسه‌ی قاب ۴۰×۴۰ یکسان). (۲) داده‌ی کانال از
پایگاه‌داده می‌آید نه از آرایه‌ی JS. (۳) فهرست کانال پیوند واقعی است، نه `button` با `onclick`.

---

## ۲) `cast.html` — «بازیگران و عوامل»

### ۲.۱ ساختار مرجع
```
main.page-container.cast-page
├── nav.breadcrumb
├── div.page-title-row → (eyebrow THE PEOPLE BEHIND THE STORIES · h1 · p.muted) + span[aria-hidden](♙)
├── div.browse-toolbar
│   ├── div.browse-search → ⌕ + input#cast-search (جستجوی زنده)
│   └── div.segmented-control#cast-filters → ۳ دکمه: همه چهره‌ها / بازیگران / کارگردان‌ها
└── div.people-grid#people-grid  ← کارت‌های `.person-card` (JS از `CINORA.people`)
      هر کارت: a.person-card → div[img + span(job) + i(↖)] · h2(نام) · p[dir=ltr](نام انگلیسی) · small(«N اثر در سینورا»)
```
**CSS مرجع:** `.people-grid` (۴ ستون؛ ۹۸۰→نسبی، ۷۶۸→۲ ستون، ۴۸۰→فاصله‌ی تنگ‌تر)، `.person-card` و اجزایش.
**JS مرجع (`cast.js`):** فیلتر هم‌زمان روی `name+english` و `job`، حالت خالی `.empty-state`، و
`renderPerson()` که همان برگه را به «برگه‌ی شخص» تبدیل می‌کند (`person-page`: `.person-hero`، `.person-portrait>span`،
`.person-copy`، `.person-facts`، `.person-english`، `.person-bio`، دکمه‌ی «آثار در سینورا ‹»).
**داده:** `people[]` با `name/english/job/image/bio/films[]/born/country`.

### ۲.۲ نگاشت به وردپرس
| جزء مرجع | سازه‌ی وردپرس |
|---|---|
| شبکه‌ی افراد | **نوع محتوای موجود `person`** + `titles-grid` با منبع `person_works` که از موج‌های پیشین هست (هیچ سازه‌ی تازه‌ای لازم نیست) |
| نقش («بازیگر»/«کارگردان») | **تاکسونومی تازه `person_role`** روی `person` (ویرایش‌پذیر، سازگار با همان لایه‌ی تاکسونومی پروژه) — فیلتر دکمه‌ای از همین ترم‌ها ساخته می‌شود |
| نام انگلیسی / زادروز / کشور | فراداده‌ی تازه در تب «عوامل» (`manacore_person_english`، `manacore_person_born`، `manacore_country`) |
| `browse-search` + `segmented-control` | همان بلوک موجود **`manacore/browse-toolbar`** در حالت تازه‌ی `people` (بدون مرتب‌سازی/نمایش؛ فقط جستجو + دکمه‌های نقش) — به‌جای ساخت نوار موازی |
| `people-grid` + `.person-card` | **حالت تازه‌ی `cardStyle: "person"`** در `manacore/titles-grid` (یا بلوک `manacore/people-grid` اگر تفاوت‌های کارت بیش از یک حالت بود — تصمیم در شروع پیاده‌سازی با معیار «کمترین کد تکراری») |
| «N اثر در سینورا» | شمارش واقعی با همان سازوکار `person_works` (با کش ترنزینت موجود) |
| برگه‌ی شخص | الگوی موجود `single-person.html` ارتقا می‌یابد تا `.person-hero`/`.person-facts`/`.person-portrait` مرجع را دقیق بسازد (به‌جای ساخت الگوی دوم) |
| جستجوی زنده و فیلتر بدون بازخوانی | `front.js` (الگوی موجود `initBrowseToolbar`) + نقطه‌ی REST موجود `/titles` با پارامترهای تازه‌ی `role`/`q` برای نوع `person` |

**قرارداد اجرایی:** `cast-parity.cjs` — هندسه‌ی کارت و شبکه در سه پله، رفتار جستجو/فیلتر، حالت خالی،
شمار «N اثر» در برابر داده‌ی واقعی، `axe`، و ممیزی ویرایشگر (تاکسونومی و فراداده در پیشخوان).

---

## ۳) `magazine.html` — «سینورامگ»

### ۳.۱ ساختار مرجع
```
main.page-container.magazine-page
├── nav.breadcrumb
├── div.magazine-intro → p.eyebrow (CINORA MAGAZINE) · h1 + span · p.muted
├── div.magazine-feature-grid#magazine-features   ← ۱ مقاله‌ی سربزرگ (.magazine-feature) + دو مقاله‌ی کنار
├── section.home-section
│   ├── div.section-heading → h2 («داستان‌های تازه») + div.section-tabs#magazine-tabs (۴ دکمه: همه/نقد و بررسی/پیشنهاد تماشا/دنیای سینما)
│   └── div.articles-grid#articles-grid → a.article-card[.article-image>img+span(دسته) | .article-info(small «◷ N دقیقه مطالعه · تاریخ» · h3 · p · span «ادامه داستان ‹»)]
```
**CSS مرجع:** `.magazine-intro`، `.magazine-feature-grid` (1.7fr/1fr؛ ۹۸۰→1.45fr/1fr؛ ۷۶۸→۱ ستون)،
`.magazine-feature` (ارتفاع ۴۳۲px)، `.magazine-side-features`، `.articles-grid` (۳ ستون؛ ۷۶۸→۱ ستون)،
`.article-card`، `.section-tabs`.
**داده:** `articles[]` با `title/category/image/minutes/date/description`؛ تب‌ها با `category` فیلتر می‌کنند.

### ۳.۲ نگاشت به وردپرس
| جزء مرجع | سازه‌ی وردپرس |
|---|---|
| مقاله‌ها | **نوع محتوای موجود `post`** — نه نوع تازه؛ همان چیزی که مدیر می‌شناسد |
| دسته‌ها (نقد/پیشنهاد/دنیای سینما) | **تاکسونومی موجود `category`**؛ تب‌ها از ترم‌های واقعی ساخته می‌شوند |
| `magazine-intro` | همان بلوک مشترک `manacore/page-intro` با چیدمان `magazine` |
| `magazine-feature-grid` | **بلوک تازه `manacore/magazine-hero`**: یک مقاله‌ی شاخص + N مقاله‌ی کنار، از کوئری واقعی (برچسب/برجسته یا تازه‌ترین‌ها)، همه‌ی متن‌ها از داده‌ی خودِ نوشته |
| `articles-grid` + تب‌ها | **حالت تازه‌ی `article` در بلوک موجود `manacore/titles-grid`** (`showCategoryTabs` + کارت مقاله) — تب‌ها از دسته‌های واقعی ساخته می‌شوند و فیلتر سمت کاربر با `data-category` انجام می‌شود |
| «N دقیقه مطالعه» | فراداده‌ی تازه‌ی `manacore_reading_time` با مقدار پیش‌فرض **محاسبه‌شده از شمار واژه‌ها** (داده‌ی واقعی، قابل بازنویسی دستی) |
| برگه‌ی مقاله | الگوی موجود نوشته (تک‌برگه) + عناصر `article-page` مرجع در همان‌جا؛ `article.html` جداگانه ساخته **نمی‌شود** تا الگوی دوم و موازی نسازیم |

**قرارداد اجرایی:** `magazine-parity.cjs` — هندسه‌ی سه جزء در سه پله، رفتار تب‌ها، حالت خالی، «N دقیقه» با
داده‌ی واقعی، `axe`، و بازرسی ویرایشگر.

---

## ۴) `help.html` — «راهنمای سینورا»

### ۴.۱ ساختار مرجع
```
main.page-container.info-page
├── nav.breadcrumb
├── section.info-intro → span.pricing-crown(svg) · p.eyebrow · h1 · p
└── div.faq-list.info-faq#help-faq  ← آیتم‌های آکاردئونی از آرایه‌ی ۸تایی در `help.js`
      هر آیتم: div.faq-item > button[آکاردئونی] + p[پاسخ] ، `.open` چرخش آیکون و رنگ تأکیدی
```
**CSS مرجع:** `.info-intro`، `.pricing-crown`، `.faq-section/.faq-list/.faq-item(.open)`.

### ۴.۲ نگاشت به وردپرس
| جزء مرجع | سازه‌ی وردپرس |
|---|---|
| `info-intro` | بلوک مشترک `manacore/page-intro` (چیدمان centered + آیکون) |
| آیتم‌های پرسش | **بلوک تازه `manacore/faq`** با **آکاردئون بومی `<details>/<summary>`** و اقلام قابل افزودن/حذف در ویرایشگر (InnerBlocks؟ نه — اقلام از یک **قلم تکراری** در پنل بلوک)، و در صورت خالی بودن از **پرسش‌های پیشنهادی افزونه** پر می‌شود |
| رفتار آکاردئون | بدون JS کار می‌کند (`<details>`) و JS فقط `animation`/`aria-expanded` و بستن هم‌زمان (اختیاری، قابل خاموش‌کردن) را اضافه می‌کند |
| ساختار سئو | پاسخ‌ها با `FAQPage` JSON-LD نشانه‌گذاری می‌شوند (افزوده‌ی حرفه‌ای، با کلید خاموش‌کردن در بلوک) |

**چرا قلم تکراری در بلوک و نه نوع محتوای `faq`؟** پرسش‌های راهنما محتوای برگه‌اند نه محتوای فهرست‌شدنی؛
مرجع هم آن‌ها را درون برگه نگه می‌دارد. اگر مدیر بخواهد، می‌تواند همان متن‌ها را در بلوک ویرایش کند.
**قرارداد اجرایی:** `help-parity.cjs` — هندسه، رفتار باز/بسته و کلید‌پذیری (`Enter`/`Space`)، حالت خالی، و `axe`.

---

## ۵) `privacy.html` — «قوانین و حریم خصوصی»

### ۵.۱ ساختار مرجع
```
main.page-container.info-page
├── nav.breadcrumb
├── section.info-intro → pricing-crown · eyebrow · h1 · p
└── div.legal-content#privacy-content → N × section(h2 + p) با خط جداکننده
```
**CSS مرجع:** `.legal-content` (۹۰۰px، `section` با `border-bottom`، `h2` ۲۱px، `p` ۱۳px/۲.۸).

### ۵.۲ نگاشت به وردپرس
| جزء مرجع | سازه‌ی وردپرس |
|---|---|
| متن حقوقی | **محتوای خودِ برگه** (`post-content`) — یعنی مدیر متن را در ویرایشگر می‌نویسد؛ بخش‌ها همان `h2`+`p` هستند |
| قالب ظاهری | **الگوی برگه‌ی تازه `page-legal.html`** (Site Editor) با `manacore/page-intro` + گروه `.legal-content` — بدون هیچ متن سخت‌کد |
| `article.html` | با ممیزی همین گام تأیید شد: ساختارش «هدر مقاله + متن + بخش‌های مرتبط» است که الگوی موجود نوشته پوشش می‌دهد؛ فقط اجزای ظاهری (`article-page`) در همان الگو کامل می‌شود |

**قرارداد اجرایی:** `legal-parity.cjs` — هندسه‌ی `.info-intro`/`.legal-content`، شمار بخش‌ها در برابر محتوای
واقعی برگه، و `axe`.

---

## ۶) ترتیب اجرا (هر مرحله مستقل و قابل آزمون)

| مرحله | کار | آزمون پذیرش |
|---|---|---|
| **۱۵/۱** | زیرساخت مشترک: بلوک `manacore/page-intro` + CSS آن + گزینه‌های ویرایشگر | `page-intro` در سه چیدمان با هندسه‌ی مرجع؛ ممیزی ویرایشگر |
| **۱۵/۲** | برگه‌ی «پخش زنده»: CPT `channel` + فراداده + بلوک‌های `live-player`/`live-channels` + REST + JS + قالب | `live-parity.cjs` |
| **۱۵/۳** | «بازیگران و عوامل»: تاکسونومی `person_role` + فراداده‌ی عوامل + نوار جستجو/فیلتر + کارت شخص + ارتقای الگوی شخص | `cast-parity.cjs` |
| **۱۵/۴** | «سینورامگ»: `magazine-hero` + حالت مقاله در `titles-grid` + زمان مطالعه | `magazine-parity.cjs` |
| **۱۵/۵** | «راهنما» و «حریم خصوصی»: `manacore/faq` + قالب حقوقی | `help-parity.cjs` · `legal-parity.cjs` |

**قاعده‌ی پذیرش هر مرحله:** هندسه با اندازه‌گیری محاسبه‌شده‌ی مرورگر در برابر مرجع، رفتار با آزمون واقعی،
حالت‌های خالی/خطا پوشش‌داده‌شده، `axe` صفر تخلف، و بازاجرای کل مجموعه‌آزمون‌ها پیش از اعلام پایان مرحله.

---

## ۷) گزارش مرحله‌ها

| مرحله | وضعیت | شاهد |
|---|---|---|
| ۱۵/۱ زیرساخت مشترک (`manacore/page-intro`) | ✅ انجام و سنجیده شد | سه چیدمان با هندسه‌ی مرجع؛ ممیزی ویرایشگر سبز |
| ۱۵/۲ برگه‌ی «پخش زنده» | ✅ انجام و سنجیده شد | `live-parity.cjs` **۱۲۸/۰**؛ شاهد چشمی `docs/shots/{wp,ref}-live-{1440,980,390}.png` |
| ۱۵/۳ «بازیگران و عوامل» و «چهره» | ✅ انجام و سنجیده شد | `cast-parity.cjs` **۱۵۷/۰**؛ سه قالب `page-cast`/`archive-person`/`single-person` در ممیزی ویرایشگر سایت؛ شاهد چشمی `docs/shots/{wp,ref}-cast-{1440,390}.png` و `{wp,ref}-person-{1440,390}.png`؛ داده‌ی آزمون در `wp/tests/qa-env/seed-cast.php` (برگشت‌پذیر با آرگومان `restore`) |
| ۱۵/۴ «سینورامگ» | ⏳ باقی‌مانده | `magazine-parity.cjs` هنوز نوشته نشده |
| ۱۵/۵ «راهنما» و «حریم خصوصی» | ⏳ باقی‌مانده | `help-parity.cjs` / `legal-parity.cjs` هنوز نوشته نشده |

**سازه‌های تحویل‌شده در ۱۵/۳** (هم‌خوان با نگاشت بخش ۲): تاکسونومی `person_role` با دو ترم پیش‌فرض
(بازیگر/کارگردان) · بلوک `manacore/people-grid` (دو گونه‌ی `cards`/`related`) · بلوک
`manacore/person-meta` (گونه‌های `identity`/`role`؛ نام لاتین + ردیف دانستنی‌ها با ارقام فارسی) ·
بلوک `manacore/cta-link` (دکمه‌های پوسته با نشانه‌ی فلش) · نوار `browse-toolbar` با حالت `people`
(جست‌وجو + دکمه‌های نقش از ترم‌های واقعی) · فراداده‌های عوامل روی عنوان‌ها
(`manacore_cast` تکراری، `manacore_director`، `manacore_writer`) و کوئری واقعی `person_works_args`.
