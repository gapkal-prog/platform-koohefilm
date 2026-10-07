# آزمون‌های مرورگری (Browser QA)

این پوشه آزمون‌های پاریتی و QA را نگه می‌دارد که به **مرورگر واقعی**، نصب آزمون وردپرس
(`http://localhost:8099`) و مرجع ایستای سینورا (`http://localhost:8098`) نیاز دارند.
هارنس سریعِ بی‌مرورگر در `wp/tests/run.sh` است (نحو PHP/JS، fixtureها، ریاضیات تاریخ،
قواعد ویرایشگر و رنگ) و در چند ثانیه تمام می‌شود؛ این پوشه چیز دیگری است.

## اجرای همه‌ی سوئیت‌ها (یک فرمان)

```bash
bash wp/tests/browser/run-suites.sh                 # برچسب پیش‌فرض: full
bash wp/tests/browser/run-suites.sh w19 --seed      # اول داده‌ی آزمون را می‌کارد
bash wp/tests/browser/run-suites.sh w19 --only article-parity.cjs,home-parity.cjs
```

لاگ خام هر سوئیت در `/tmp/sweep-raw/<tag>-<suite>.txt` و خلاصه در `/tmp/sweep-<tag>.txt`
می‌نشیند (همان مسیری که در گزارش QA به آن ارجاع داده شده). اگر سرورها بالا نباشند،
اسکریپت با کد ۲ بیرون می‌آید تا نتیجه‌ی «سبز» الکی ساخته نشود.

اجرای یک سوئیت به‌تنهایی:

```bash
cd wp/tests/browser && PLAYWRIGHT_BROWSERS_PATH=/home/user/.cache/pw-browsers node article-parity.cjs
```

## سوئیت‌ها (آزمون‌های ثبت‌شده در گزارش)

`harness-all`، `home-parity`، `browse-parity`، `schedule-parity`، `account-parity`،
`player-parity`، `live-parity`، `download-parity`، `magazine-parity`، `article-parity`،
`info-parity`، `cast-parity`، `plans-parity`، `drawer-parity`، `picker-e2e`،
`editor-blocks-support`، `editor-schedule`، `editor-templates-audit`، `lang-audit`،
`width-audit`، `parity-shots` و سه سوئیت axe (`axe-drawer`، `axe-episode`، `axe-player`).

## کاوشگرها (`probe-*.cjs`، `mega-*.cjs`)

ابزار سنجش یک‌باره‌ی موج‌ها هستند (اندازه‌گیری خام برای یافتن واگرایی) و **سوئیت نیستند**،
پس در `run-suites.sh` اجرا نمی‌شوند. عمداً نگه داشته شده‌اند چون گزارش QA برای شاهدِ بعضی
بندها به آن‌ها ارجاع می‌دهد (مثلاً `probe-sidebar.cjs` برای فاصله‌های ستون کنار،
`probe-comments.cjs` برای نشانه‌گذاری بخش دیدگاه، `mega-measure.cjs` برای ابعاد مگامنو).

## قراردادها

1. **داده‌ی آزمون:** سوئیت‌هایی که به داده‌ی بذرگرفته نیاز دارند **خودشان** می‌کارند و در
   پایان برمی‌گردانند (`seed-*.php` در `wp/tests/qa-env/`). به ترتیب اجرا تکیه نکنید.
2. **اگر آزمونی اجرا نشد، سبز نیست:** سوئیتی که برش/سنجه‌اش را از دست بدهد باید با کد
   غیرصفر بیرون بیاید (نمونه: `parity-shots.cjs` که در نبود هدفش برش نمی‌گیرد و خروج ۱ می‌دهد).
3. **شاخه‌ی مرده ممنوع:** پرچمی که هیچ‌جا مقدار نمی‌گیرد، گزاره‌ها را بی‌صدا از اجرا می‌اندازد؛
   پرچم‌های «نمایش/ناموجود بودن» را از هندسه‌ی واقعی بسازید (`display`، پهنای جعبه، `checkVisibility()`).
4. **کنترل منفی:** هر سناریوی تازه یک بار با خراب‌کردن عمدی باید سرخ شود (`git` را دست‌نخورده نگه دارید و بعد برگردانید).
