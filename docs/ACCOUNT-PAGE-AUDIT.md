# ممیزی مرجع و نقشه‌ی پیاده‌سازی — برگه‌ی «حساب کاربری» (موج چهاردهم)

**مرجع:** `cinora/account.html` (۲۹٬۰۵۲ بایت) + `cinora/assets/js/account.js` (۱۱٬۷۲۰ بایت) + قواعد مربوط در `cinora/assets/css/style.css` (۲۱۷ قاعده‌ی حاوی کلاس‌های حساب).

**وضعیت محصول پیش از این موج:** **برگه‌ی حساب کاربری وجود ندارد.** زیرساخت داده‌اش اما هست و باید بازاستفاده شود، نه دوباره‌نویسی:

| قابلیت موجود | جای آن | آنچه می‌دهد |
| --- | --- | --- |
| لیست تماشا | `manacore-core/includes/class-watchlist.php` | متای کاربر `manacore_watchlist` و `manacore_watched` + `get()/toggle()/has()` + شورت‌کد |
| دکمه‌ی «افزودن به لیست» | `class-templates.php` (قلم تک‌برگه) | `data-manacore-watchlist="<id>"` روی جزئیات اثر |
| REST لیست تماشا | `class-rest-api.php` → `/watchlist` | تغییر وضعیت با nonce |
| وضعیت اشتراک | `manacore-subscriptions/includes/class-account.php` → `status_card()` | کارت وضعیت اشتراک از سطح دسترسی واقعی کاربر (`user_level()`/`expiry()`/`source()`) |
| پیشرفت تماشا | `assets/js/front.js` → کلید `manacore-progress` | **فقط در localStorage** (شبیه مرجع) — این موج باید آن را به سرور هم ببرد |

## ۱) فهرست کامل مرجع

```
main.page-container.account-page
├── .breadcrumb                          (سینورا ‹ حساب کاربری)
├── .account-greeting                    eyebrow «YOUR OWN UNIVERSE» + h1 + p.muted + button.button.primary «ساخت حساب»
└── .account-layout                      grid: 230px + 1fr، gap 30px
    ├── aside.account-sidebar            (padding 18، panel، شعاع ۱۱)
    │   ├── .account-profile             .large-avatar ۷۲px دایره + h3 نام + p امضا + .membership-pill (با .live-dot)
    │   ├── nav > button[data-tab] ×۶     دنیای من / لیست تماشا (+شمار) / تاریخچه تماشا / سلیقه من (+ کوچک «برای تو») /
    │   │                                  اشتراک و صورت‌حساب / تنظیمات حساب
    │   ├── button.account-logout.hidden  «خروج از حساب»
    │   └── a.account-upgrade             «داستان‌ها را بیشتر زندگی کن.» + «کشف سینورا پلاس ‹»
    └── .account-content
        ├── .account-loading.hidden       «در حال آماده کردن دنیای تو...»
        ├── section[data-panel="overview"]
        │   ├── .stat-grid (۴ ستون، gap ۱۶) → .stat-card ×۴: نشان گوشه (۳۰×۳۰) + strong (۲۶px) + p (۹px)
        │   │      «داستان در لیست تماشا» ▣ · «داستان تماشاشده» ◉ · «دقیقه تماشای نمونه» ◷ · «ژانر موردعلاقه» ♥ (مقدار کوچک ۱۳px)
        │   ├── .account-welcome-banner    eyebrow + h2 + p + button.text-link + آیکن ✦
        │   └── .account-section ×۲        «منتظر تماشای تو» / «برای شروع، این‌ها را ببین» + .media-grid ۴ ستون
        ├── section[data-panel="watchlist"].hidden
        │   ├── .section-heading            عنوان «داستان‌هایی که برای بعد نگه داشتی» + «۰ انتخاب، به سلیقه خودت»
        │   ├── .section-tabs ×۳            همه / فیلم‌ها / سریال‌ها
        │   └── .media-grid
        ├── section[data-panel="history"].hidden
        │   ├── .section-heading            «داستان‌هایی که با تو همراه شدند» + «پیشرفت تماشای نمونه‌های ویدئویی تو»
        │   ├── button.text-link.danger     «پاک کردن تاریخچه»
        │   └── .history-list               .history-item: img ۶۷×۹۵ + h3 ۱۳px + p ۹px + small ۸px + دکمه‌ی ادامه
        ├── section[data-panel="analytics"].hidden
        │   ├── .section-heading            «سلیقه‌ات، به زبان داستان‌ها» + «تحلیل بر اساس لیست تماشا، پیشرفت و امتیازهای تو»
        │   ├── .analytics-grid             grid 1.2fr 1fr، gap ۲۰
        │   │   ├── .analytics-card.genre-chart-card     .genre-chart-body (gap ۲۴) + .donut-chart ۱۴۹px (حلقه + مرکز) + .chart-legend
        │   │   ├── .analytics-card.format-chart-card    .format-stat + .format-progress (و نسخه‌ی .series)
        │   │   ├── .analytics-card.activity-chart-card  .bar-chart (میله‌های روزهای هفته)
        │   │   └── .analytics-card.country-chart-card   .country-bars
        │   ├── .account-section            «پیشنهاد برای اولین قدم» + .media-grid
        │   └── p.analytics-privacy         «◈ تحلیل‌ها فقط از فعالیت خودت ساخته می‌شوند...»
        └── section[data-panel="subscription"].hidden و section[data-panel="settings"].hidden
            ├── .subscription-status-card   ♛ + eyebrow + h2 + متن + دکمه
            ├── .invoice-table/.invoice-row/.invoice-status
            ├── .settings-avatar + .settings-fields + .settings-toggle-row ×۳
            ├── .settings-card ×۲ (رمز عبور، خروجی گرفتن) + .auth-logo
            └── .settings-card.settings-export
```

**رفتارهای `account.js`:** `showTab()` با `?tab=` (مقادیر `overview|watchlist|history|analytics|subscription|settings`)، `bindFavorites()` برای برداشتن/افزودن، «پاک کردن تاریخچه»، محاسبه‌ی `genreStats()`، توزیع فرمت/کشور/فعالیت، دانلود رسید، ذخیره‌ی تنظیمات، خروج. **همه‌ی داده‌اش از `localStorage`** با کلیدهای `cinora-user`, `cinora-watchlist`, `cinora-progress`, `cinora-ratings`, `cinora-subscription`, `cinora-my-data`, `cinora-receipt-*`.

**حالت مهمان:** مرجع برای کاربر وارد‌نشده همه‌ی شمارنده‌ها را صفر می‌گذارد، دکمه‌ی «ساخت حساب» را نشان می‌دهد و `account-logout` را پنهان می‌کند.

## ۲) نقشه‌ی تبدیل به معماری وردپرس

| بخش مرجع | سازه‌ی وردپرس | یادداشت |
| --- | --- | --- |
| برگه‌ی حساب | صفحه‌ی وردپرس با نامک `account` + `templates/page-account.html` (ویرایشگر سایت) | با `koohe_account_url()` به فهرست راهبری/کشو وصل می‌شود |
| `.account-sidebar` | بلوک `manacore/account-nav` | نام کاربری/آواتار از `wp_get_current_user()` و `get_avatar()`؛ شمار لیست تماشا از متای کاربر؛ تب‌ها با `?tab=` (deep-link) |
| `.account-greeting` | بخشی از همان بلوک (یا بلوک `manacore/account-header`) | متن مهمان/کاربر از داده‌ی واقعی؛ دکمه‌ی «ساخت حساب» → `wp_registration_url()` |
| `.stat-grid` | بلوک `manacore/account-stats` | ۴ شمارنده از: لیست تماشا، `manacore_watched`، مجموع دقیقه‌ها (از متای پیشرفت)، ژانر غالب (از ژانرهای همان آثار) |
| `.account-welcome-banner` | بلوک `manacore/account-banner` | متن/دکمه ویرایش‌پذیر در ویرایشگر |
| شبکه‌های «منتظر تماشای تو»/«برای شروع» | `manacore/titles-grid` با منبع تازه `watchlist` و `recommended` | بازاستفاده از رندرکننده‌ی کارت موجود |
| تب‌های «لیست تماشا» | `manacore/titles-grid` با `source:watchlist` + `showTypeTabs` | همان تب‌های نوع موجود |
| «تاریخچه تماشا» | بلوک تازه‌ی `manacore/account-history` | ردیف‌ها از متای «دیده‌شده/پیشرفت»؛ دکمه‌ی پاک‌کردن → REST با nonce |
| «سلیقه من» (تحلیل‌ها) | بلوک تازه‌ی `manacore/account-analytics` | چهار کارت: دوناتِ ژانر (SVG در PHP)، میله‌های فرمت/کشور، ستون‌های فعالیت هفتگی — همه محاسبه‌شده در PHP از داده‌ی واقعی کاربر |
| «اشتراک و صورت‌حساب» | `manacore-subscriptions` → `status_card()` + بلوک `manacore/account-invoices` | کارت وضعیت آماده است؛ ردیف‌های صورت‌حساب از سفارش‌های ووکامرس (اگر فعال باشد) وگرنه از تاریخ‌های تمدید متای اشتراک |
| «تنظیمات حساب» | بلوک `manacore/account-settings` | فرم واقعی `wp_update_user` (نام نمایشی/ایمیل/رمز) با nonce + ردیف‌های کلیدی روی متای کاربر (خبرنامه، اعلان‌ها) |
| پیشرفت تماشا | به‌روزرسانی `class-rest-api.php` + `class-watchlist.php` | ثبت پیشرفت/زمان روی سرور (متای کاربر) و کش خوش‌بینانه در localStorage؛ تاریخچه و دقیقه‌ها از همین داده |
| تحلیل‌ها | `includes/class-account-data.php` (کلاس تازه در افزونه) | یک لایه‌ی داده با کش کوتاه (`get_transient`) تا هر بلوک کوئری تکراری نزند |

## ۳) نقشه‌ی فازها (هر فاز جداگانه آزمون‌پذیر)

| فاز | کار | آزمون |
| --- | --- | --- |
| P1 | برگه‌ی `account` + `page-account.html` + `koohe_account_url()` + بلوک `manacore/account-nav` (نمایه، تب‌ها، خروج، ارتقا) | `account-parity.cjs` بخش ساختار/هندسه + `harness-all` (برگه‌ی تازه) + axe |
| P2 | `manacore/account-stats` + `source:watchlist`/`recommended` روی `titles-grid` + حالت مهمان | سنجش شمارنده‌ها با کاربر واقعی و با کاربر مهمان (دو نشست) |
| P3 | `manacore/account-history` + ماندگاری پیشرفت تماشا روی سرور (REST) + دکمه‌ی پاک‌کردن | آزمون رفتاری: پخش → ثبت پیشرفت → دیده‌شدن در تاریخچه → پاک‌کردن |
| P4 | `manacore/account-analytics` (دونات/میله/ستون) + `account-invoices` | سنجش هندسه با مرجع + صحت محاسبه روی داده‌ی ساختگی |
| P5 | `manacore/account-settings` (فرم‌های واقعی) + نردبان واکنش‌گرا + a11y | ثبت واقعی فرم + `axe` + نردبان ۱۴۴۰/۹۸۰/۷۶۸/۴۸۰/۳۹۰ |

## ۳.۵) یک نکته‌ی دقیق درباره‌ی نشانی حساب

`wp/themes/koohe-film/inc/template-tags.php:149` امروز برای کاربر واردشدهٔ بدون ووکامرس به `admin_url('profile.php')` می‌افتد. در P1 این تابع به **برگه‌ی واقعی `account`** (اگر وجود داشته باشد) وصل می‌شود و `$args` می‌گیرد تا توکن‌های موجود `account:<tab>` (که بلوک کارت ترویجی همین حالا از آن‌ها استفاده می‌کند) به تب درست برگردند. ترتیب اولویت: برگه‌ی خودمان → حساب ووکامرس → پیشخوان کاربر → ورود.

## ۴) تصمیم‌های معماری (قیدهای رعایت‌شده)

1. **بدون mock و بدون localStorage به‌عنوان منبع حقیقت:** همه‌ی شمارنده‌ها/تحلیل‌ها در PHP از داده‌ی کاربر محاسبه می‌شوند؛ localStorage فقط کشِ خوش‌بینانه‌ی پیشرفت است (مثل مرجع) و سرور مرجع نهایی است.
2. **بدون تکرار:** کارت‌ها با `titles-grid` موجود، کارت اشتراک با `status_card()` موجود، لیست تماشا با `Watchlist` موجود.
3. **همه‌چیز ویرایش‌پذیر:** برچسب‌ها، متن‌ها، مقصدها و آستانه‌ها ویژگی بلوک‌اند (پنل بازرس)، هیچ متن ثابتی در قالب نیست.
4. **دسترس‌پذیری:** تب‌ها با `role="tablist"/"tab"/"tabpanel"` و `aria-selected` مثل نوار روزهای برنامه؛ دکمه‌های خطر با متن روشن.
5. **امنیت:** همه‌ی تغییرها از REST با `permission_callback` (کاربر واردشده) و `X-WP-Nonce`؛ فرم‌های ویژه با `check_admin_referer`/`wp_nonce_field`.

## ۵) محدودیت‌های شناخته‌شده‌ی این موج (برای بخش ۱۱ گزارش)

- صورت‌حساب واقعی به ووکامرس گره می‌خورد؛ روی این نصب آزمون، ووکامرس نصب نیست، پس بلوک باید **حالت خالی صادقانه** داشته باشد (به‌جای ردیف‌های ساختگی) و در حضور ووکامرس ردیف‌های واقعی بسازد.
- «امتیازهای تو» در مرجع از localStorage می‌آید؛ در محصول امتیاز کاربر (اگر افزونه‌ی امتیازدهی نصب باشد) یا خالی می‌ماند — تصمیم در P4 مستند می‌شود.
- تحلیل‌های «فعالیت هفتگی» به تعداد رویدادهای ثبت‌شده گره دارد؛ روی نصب تازه خالی است و باید حالت خالی مرجع را نشان دهد.
