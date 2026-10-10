<div dir="rtl">

# یکپارچه‌سازی پیوسته (CI) — راه‌اندازی در چند دقیقه

ادعای «بدون خطا» فقط با اندازه‌گیری معنا دارد. `wp/tests/run.sh` روی ماشین
توسعه‌دهنده همین کار را می‌کند؛ این سند همان مجموعه را روی GitHub Actions
می‌برد تا هر تغییر روی سه نسخه‌ی PHP و یک نسخه‌ی Node سنجیده شود و بسته‌ی
نصبی ZIP پس از سبز شدن آزمون‌ها به‌عنوان artifact ساخته شود.

## چرا فایل workflow در مخزن نیست؟

توکن این مخزن اجازه‌ی نوشتن فایل‌های `.github/workflows/*` را ندارد
(خطای GitHub: `refusing to allow a GitHub App to create or update workflow
... without workflows permission`). برای همین دستور آماده اینجا نگه داشته
شده است؛ **فایل زیر را عیناً در مسیر `.github/workflows/ci.yml` بسازید** و
کامیت کنید (از رابط وب GitHub هم می‌شود). اگر بعدها دسترسی `workflows` به
اپلیکیشن داده شود، همین فایل مستقیم قابل push می‌شود.

## محتوای `.github/workflows/ci.yml`

```yaml
name: CI

#
# یکپارچه‌سازی پیوسته (CI) برای «کوهه فیلم».
#
# چرا این فایل هست؟ ادعای «بدون خطا» فقط با اندازه‌گیری معنا دارد:
# هر تغییر روی سه نسخه‌ی PHP آزمون می‌شود (سازگاری ۸٫۱ تا ۸٫۳)،
# همان چیزی که `wp/tests/run.sh` روی ماشین توسعه‌دهنده اجرا می‌کند.
# بسته‌ی نصبی هم پس از سبز شدن آزمون‌ها ساخته و به‌عنوان artifact
# نگه داشته می‌شود تا تحویل به خریدار از یک منبع تکرارپذیر بیاید.
#

on:
  push:
    branches: [ main, 'arena/**' ]
  pull_request:
  workflow_dispatch:

concurrency:
  group: ci-${{ github.ref }}
  cancel-in-progress: true

permissions:
  contents: read

jobs:
  tests:
    name: آزمون‌ها — PHP ${{ matrix.php }}
    runs-on: ubuntu-latest

    strategy:
      fail-fast: false
      matrix:
        # افزونه روی PHP 7.4+ کار می‌کند؛ CI بازه‌ی پشتیبانی‌شده‌ی امروز
        # (۸٫۱ تا ۸٫۳) را می‌سنجد تا هر ناسازگاری زودتر دیده شود.
        php: [ '8.1', '8.2', '8.3' ]

    steps:
      - name: دریافت کد
        uses: actions/checkout@v4

      - name: نصب PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, json
          coverage: none

      - name: نصب Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: بررسی نحو PHP و JS + اجرای همه‌ی سوئیت‌ها
        run: bash wp/tests/run.sh

  package:
    name: بسته‌ی نصبی (ZIP)
    runs-on: ubuntu-latest
    needs: tests
    if: github.event_name == 'push'

    steps:
      - name: دریافت کد
        uses: actions/checkout@v4

      - name: نصب PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: none

      - name: ساخت بسته‌ها (افزونه‌ها + قالب + بسته‌ی کامل)
        run: bash wp/build.sh

      - name: بارگذاری بسته‌ها
        uses: actions/upload-artifact@v4
        with:
          name: koohe-film-packages
          path: wp/dist/*.zip
          if-no-files-found: error
          retention-days: 30
```

## این جریان چه می‌کند؟

| مرحله | کار |
|---|---|
| `tests` (PHP ۸٫۱ / ۸٫۲ / ۸٫۳ + Node 20) | `bash wp/tests/run.sh` — بررسی نحو PHP با `token_get_all`، بررسی نحو JS با `node --check`، ساخت fixture‌ها با `bootstrap.php`، ۱۱ سوئیت PHP و ۱۰ سوئیت Node |
| `package` (پس از سبز شدن tests) | `bash wp/build.sh` — سه ZIP افزونه، ZIP قالب و بسته‌ی کامل؛ بارگذاری به‌عنوان artifact با نگه‌داری ۳۰ روزه |

اجرای دوباره روی یک شاخه با `concurrency` لغو می‌شود و دسترسی توکن به
`contents: read` بسنده می‌کند.

## سوئیت‌های مرورگری

`wp/tests/browser/*.cjs` نیازمند Playwright/Chromium است و در این جریان
اجرا نمی‌شود تا runner سبک بماند؛ اجرای محلی‌اش:

```bash
npm run wp:test:browser   # یا: bash wp/tests/browser/run-suites.sh
```

</div>
