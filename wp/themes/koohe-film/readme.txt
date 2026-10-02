=== Koohe Film ===

Contributors: manacore
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: blog, entertainment, full-site-editing, block-patterns, block-styles, style-variations, dark-mode, rtl-language-support, translation-ready, custom-colors, custom-logo, custom-menu, featured-images, threaded-comments, wide-blocks

Koohe Film — قالب بلوکی مینیمال و کامل (FSE) برای وب‌سایت‌های فیلم، سریال و انیمه.
طراحی و توسعه: ManaCore

== Description ==

«کوه فیلم» (Koohe Film) یک قالب بلوکی تمام‌ویرایش‌پذیر (Full Site Editing) با طراحی
مینیمال است که برای وب‌سایت‌های آرشیو فیلم، سریال و انیمه ساخته شده است.

= ویژگی‌های کلیدی =

* **تمام‌ویرایش‌پذیر (FSE)** — ۲۱ قالب (template)، ۶ بخش قالب (template part)
  و ۱۰ الگوی بلوک (pattern) که همه از داخل «ویرایشگر سایت» قابل تغییرند.
* **حالت تیره و روشن** — کلید تغییر حالت در سربرگ، ذخیره‌ی انتخاب کاربر در
  مرورگر، پشتیبانی از حالت خودکار (بر پایه‌ی تنظیم سیستم عامل) و جلوگیری از
  پرش رنگ (FOUC) با اسکریپت درون‌خطی.
* **۴ تنوع ظاهری (Style Variations)** — Dark، Light، Midnight و Cinema.
* **۱۴ سبک بلوک اختصاصی** — کارت، شیشه‌ای، پوستر، ردیف افقی، قرصی و…
* **راست‌به‌چپ کامل** — تمام CSS با خصوصیات منطقی (logical properties) نوشته
  شده است؛ نیازی به فایل RTL جداگانه نیست.
* **بهینه و سبک** — بدون jQuery، بدون فریم‌ورک؛ فقط یک فایل CSS و یک فایل JS.
* **سازگاری گسترده** — ووکامرس، اشتراک‌های ووکامرس، افزونه‌های سئو
  (Yoast / Rank Math / SEOPress / AIOSEO)، افزونه‌های کش
  (WP Rocket / LiteSpeed / Perfmatters)، Elementor، Beaver Builder،
  Polylang و AMP.
* **دسترس‌پذیری** — پیوند پرش به محتوا، حلقه‌ی فوکوس یکنواخت،
  احترام به `prefers-reduced-motion`، برچسب‌های ARIA.

= افزونه‌های همراه (اختیاری اما پیشنهادی) =

قالب به‌تنهایی کار می‌کند، اما برای بهره‌گیری کامل، افزونه‌های مجموعه‌ی
ManaCore پیشنهاد می‌شوند:

1. **ManaCore Core** — انواع محتوای فیلم/سریال/انیمه/قسمت/عوامل/مجموعه،
   فیلدهای متاباکس کامل، بخش لینک‌های دانلود (کیفیت/فصل/قسمت)، امتیازدهی،
   لیست تماشا، پخش‌کننده، بلوک‌های نمایشی و JSON-LD.
2. **ManaCore Sources** — دریافت خودکار اطلاعات؛ هم در حالت **بدون کلید API**
   (Wikidata، TVMaze، Jikan) و هم با **کلید TMDB** (در صورت ثبت کلید،
   اولویت به آن داده می‌شود).
3. **ManaCore Subscriptions** — سطوح اشتراک و محدودسازی محتوا بر پایه‌ی
   WooCommerce Subscriptions.

در نبود این افزونه‌ها، قالب بدون خطا کار می‌کند و تنها بلوک‌های مرتبط
نمایش داده نمی‌شوند (همه‌ی فراخوانی‌ها با `function_exists` محافظت شده‌اند).

== Installation ==

1. از مسیر «نمایش ← پوسته‌ها ← افزودن پوسته ← بارگذاری پوسته» فایل زیپ را
   بارگذاری و پوسته را فعال کنید.
2. افزونه‌های ManaCore را (در صورت تمایل) نصب و فعال کنید.
3. به «نمایش ← ویرایشگر» بروید و قالب‌ها، بخش‌ها و الگوها را سفارشی کنید.
4. برای انتخاب حالت پیش‌فرض رنگ، سربرگ چسبان، دکمه‌ی بازگشت به بالا و متن
   حق‌نشر، به «نمایش ← سفارشی‌سازی ← تنظیمات کوه فیلم» بروید.
5. برای صفحه‌ی اشتراک، یک برگه با نامک `subscribe` بسازید و قالب سفارشی
   «برگه — اشتراک» را برای آن انتخاب کنید.

== Frequently Asked Questions ==

= آیا قالب بدون افزونه هم کار می‌کند؟ =

بله. تمام قابلیت‌های وابسته به افزونه با `function_exists()` / `class_exists()`
محافظت شده‌اند و در نبود افزونه نادیده گرفته می‌شوند.

= کلید حالت تیره چگونه کار می‌کند؟ =

دکمه‌ی سربرگ دارای ویژگی `data-manacore-theme-toggle` است. اگر افزونه‌ی
ManaCore Core فعال باشد، منطق آن اجرا می‌شود؛ در غیر این صورت `theme.js`
خودِ قالب منطق جایگزین را فراهم می‌کند. انتخاب کاربر در `localStorage`
با کلید `manacore-color-mode` ذخیره می‌شود.

= چگونه رنگ‌ها را تغییر دهم؟ =

از «ویرایشگر سایت ← سبک‌ها» یا با ویرایش `theme.json`. نامک‌های پالت
(`surface`، `surface-2`، `surface-3`، `foreground`، `muted`، `accent`،
`accent-contrast`، `border`، `success`، `danger`) باید حفظ شوند، زیرا
افزونه‌ی ManaCore Core نیز از همان متغیرها استفاده می‌کند.

= آیا فونت از CDN بارگذاری می‌شود؟ =

به‌صورت پیش‌فرض فونت وزیرمتن از jsDelivr بارگذاری می‌شود. برای فونت محلی،
فایل `assets/fonts/vazirmatn.css` را بسازید (به‌طور خودکار جایگزین می‌شود) یا
با فیلتر `koohe_load_remote_font` بارگذاری از CDN را غیرفعال کنید:

`add_filter( 'koohe_load_remote_font', '__return_false' );`

== Filters ==

* `koohe_load_remote_font` (bool) — بارگذاری فونت از CDN. پیش‌فرض: true
* `koohe_remove_global_styles_svg` (bool) — حذف فیلترهای SVG هسته. پیش‌فرض: false
* `manacore_enable_schema` (bool) — قالب در صورت شناسایی افزونه‌ی سئو،
  JSON-LD افزونه‌ی هسته را غیرفعال می‌کند.

== Template Files ==

= قالب‌ها (templates/) =
index، front-page، home، single، single-movie، single-series، single-anime،
single-episode، single-person، single-collection، archive، archive-movie،
archive-series، archive-anime، taxonomy، search، 404، page، page-wide،
page-no-title، page-subscribe

= بخش‌های قالب (parts/) =
header، header-minimal، footer، sidebar، title-header، post-meta

= الگوها (patterns/) =
subscribe-cta، hero-featured، latest-movies، latest-series، trending-row،
top-rated، genre-cloud، single-title-body، plans-grid، hidden-no-results

== Copyright ==

Koohe Film WordPress Theme, (C) 2025 ManaCore
Koohe Film is distributed under the terms of the GNU GPL v2 or later.

این پوسته شامل منابع زیر است:

Vazirmatn font
License: SIL Open Font License 1.1
Source: https://github.com/rastikerdar/vazirmatn

آیکون‌های SVG درون‌خطی و تصویر `assets/img/poster-placeholder.svg`
اثر اصیل ManaCore و تحت همان مجوز GPL منتشر می‌شوند.

تصویر `screenshot.png` به‌صورت برنامه‌نویسی‌شده تولید شده است
(اسکریپت: `.build/screenshot.py`) و هیچ محتوای دارای حق تکثیر ثالث ندارد.

== Changelog ==

= 1.0.0 =
* انتشار نخست.
* ۲۱ قالب، ۶ بخش قالب، ۱۰ الگوی بلوک.
* ۴ تنوع ظاهری (Dark، Light، Midnight، Cinema).
* ۱۴ سبک بلوک اختصاصی و ۲ بلوک قالب (کلید حالت رنگ، کارت حساب).
* حالت تیره/روشن با جلوگیری از FOUC.
* لایه‌ی سازگاری با ووکامرس، افزونه‌های سئو، افزونه‌های کش،
  صفحه‌سازها، Polylang و AMP.
