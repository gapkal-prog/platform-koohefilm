#!/usr/bin/env bash
#
# ساخت محیط آزمون وردپرس برای آزمون‌های مرورگری.
#
# چرا این پرونده هست: محیط QA در `/home/user/.cache/` زندگی می‌کند و بخشی
# از اسنپ‌شات پروژه نیست؛ پس با هر بازنشانی سندباکس پاک می‌شود. پیش از این
# همه‌ی مراحل دستی اجرا شده بود و تکرارشان به حافظه‌ی اجراکننده وابسته بود.
# این اسکریپت همان مراحل را بازتولیدپذیر می‌کند تا هر ادعای «آزموده‌شده» در
# گزارش با یک فرمان قابل بازسازی باشد.
#
# استفاده:
#   bash wp/tests/qa-env/provision.sh            # ساخت/تکمیل محیط
#   bash wp/tests/qa-env/provision.sh --force    # نصب تازه‌ی وردپرس
#
# پس از آن، سرورها را جداگانه بالا بیاورید (بخش «سرورها» در پایان خروجی).
#
# @package ManaCore\QA
set -uo pipefail

PROJECT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../../.." && pwd )"
WPCORE="${WPCORE:-/home/user/.cache/wp}"
QADIR="${QADIR:-/home/user/.cache/qa}"
BROWSERS="${PLAYWRIGHT_BROWSERS_PATH:-/home/user/.cache/pw-browsers}"

WP_PORT="${WP_PORT:-8099}"
REF_PORT="${REF_PORT:-8098}"

DB_NAME="wpqa"
DB_USER="wpqa"
DB_PASS="wpqa"

FORCE=0
[ "${1:-}" = "--force" ] && FORCE=1

step() { printf '\n== %s\n' "$1"; }
ok()   { printf '   ✓ %s\n' "$1"; }
warn() { printf '   ! %s\n' "$1"; }

# ---------------------------------------------------------------------------
# ۱) پیش‌نیازهای سیستمی
# ---------------------------------------------------------------------------
step "پیش‌نیازهای سیستمی (PHP، MariaDB، wp-cli)"

if ! command -v php >/dev/null 2>&1; then
	export DEBIAN_FRONTEND=noninteractive
	sudo apt-get update -qq >/dev/null 2>&1
	sudo apt-get install -y -qq php-cli php-mysql php-mbstring php-xml php-curl \
		php-zip php-gd php-intl mariadb-server mariadb-client zip unzip >/dev/null 2>&1
fi
ok "PHP $(php -r 'echo PHP_VERSION;')"

# `mariadbd` در `/usr/sbin` است و برای کاربر غیرریشه در PATH نیست؛ پس فقط با
# `command -v` نبودنش ثابت نمی‌شود و اسکریپت بی‌دلیل بسته را دوباره نصب می‌کرد.
if ! command -v mariadbd >/dev/null 2>&1 && ! command -v mysqld >/dev/null 2>&1 \
	&& [ ! -x /usr/sbin/mariadbd ] && [ ! -x /usr/sbin/mysqld ]; then
	export DEBIAN_FRONTEND=noninteractive
	sudo apt-get install -y -qq mariadb-server mariadb-client >/dev/null 2>&1
fi

sudo service mariadb start >/dev/null 2>&1 || sudo service mysql start >/dev/null 2>&1 || true
for i in $( seq 1 20 ); do
	sudo mariadb -e 'SELECT 1' >/dev/null 2>&1 && break
	sleep 1
done

sudo mariadb -e "
	CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
	CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
	CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
	GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
	GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
	FLUSH PRIVILEGES;" >/dev/null 2>&1 && ok "پایگاه‌داده ${DB_NAME} آماده است" || warn "ساخت پایگاه‌داده ناموفق بود"

if ! command -v wp >/dev/null 2>&1; then
	curl -sSLo /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
		&& sudo mv /tmp/wp-cli.phar /usr/local/bin/wp \
		&& sudo chmod +x /usr/local/bin/wp
fi
ok "wp-cli $(wp --version --allow-root 2>/dev/null | head -1)"

# ---------------------------------------------------------------------------
# ۲) وردپرس و اتصال مخزن
# ---------------------------------------------------------------------------
step "نصب وردپرس در ${WPCORE}"

if [ "$FORCE" -eq 1 ]; then
	rm -rf "$WPCORE"
fi

if [ ! -f "$WPCORE/wp-admin/index.php" ]; then
	mkdir -p "$WPCORE"
	wp core download --path="$WPCORE" --locale=fa_IR --allow-root >/dev/null 2>&1 || \
		wp core download --path="$WPCORE" --allow-root >/dev/null 2>&1
	ok "هسته‌ی وردپرس دانلود شد"
fi

if [ ! -f "$WPCORE/wp-config.php" ]; then
	wp config create --path="$WPCORE" --dbname="$DB_NAME" --dbuser="$DB_USER" \
		--dbpass="$DB_PASS" --dbhost=127.0.0.1 --locale=fa_IR --allow-root >/dev/null 2>&1
	ok "wp-config.php ساخته شد"
fi

if ! wp core is-installed --path="$WPCORE" --allow-root >/dev/null 2>&1; then
	wp core install --path="$WPCORE" --url="http://localhost:${WP_PORT}" \
		--title="Koohe Film QA" --admin_user=admin --admin_password=admin \
		--admin_email=qa@koohe.test --skip-email --allow-root >/dev/null 2>&1
	ok "وردپرس نصب شد"
fi

#
# بسته‌ی زبان فارسی — هر بار بررسی می‌شود، نه فقط در نصب نخست.
#
# چرا مهم است: وردپرس جهت متن را از فایل ترجمه می‌خواند
# (`_x( 'ltr', 'text direction' )` در `WP_Locale`)؛ بدون آن `is_rtl()`
# نادرست می‌شود و کل سایت — از جمله سربرگ و چیدمان — چپ‌به‌راست رندر
# می‌شود، در حالی که مرجع راست‌به‌چپ است. این ایراد در همین موج
# سنجیده و بسته شد.
#
wp language core install fa_IR --activate --path="$WPCORE" --allow-root >/dev/null 2>&1

if [ "$( wp eval 'echo is_rtl() ? "yes" : "no";' --path="$WPCORE" --allow-root 2>/dev/null )" = "yes" ]; then
	ok "زبان سایت fa_IR و راست‌به‌چپ (is_rtl=true)"
else
	warn "is_rtl() نادرست است؛ ترجمه‌ی fa_IR را بررسی کنید (اندازه‌گیری‌های هم‌سانی بی‌اعتبار می‌شوند)"
fi

# قالب و افزونه‌های مخزن با پیوند نمادین به نصب وصل می‌شوند تا هر تغییر
# کد بی‌درنگ در سایت آزمون دیده شود.
mkdir -p "$WPCORE/wp-content/themes" "$WPCORE/wp-content/plugins"

link_into() {
	local source="$1" target_dir="$2"
	local name
	name="$( basename "$source" )"
	if [ -e "$target_dir/$name" ] && [ ! -L "$target_dir/$name" ]; then
		rm -rf "$target_dir/$name"
	fi
	ln -sfn "$source" "$target_dir/$name"
}

for theme in "$PROJECT"/wp/themes/*/; do
	link_into "${theme%/}" "$WPCORE/wp-content/themes"
done
for plugin in "$PROJECT"/wp/plugins/*/; do
	link_into "${plugin%/}" "$WPCORE/wp-content/plugins"
done
ok "قالب و افزونه‌ها پیوند شدند"

# ---------------------------------------------------------------------------
# ۳) راه‌اندازی و محتوای آزمون
# ---------------------------------------------------------------------------
step "راه‌اندازی قالب/افزونه و محتوای آزمون"

wp option update permalink_structure '/%postname%/' --path="$WPCORE" --allow-root >/dev/null 2>&1
wp rewrite flush --path="$WPCORE" --allow-root >/dev/null 2>&1

wp theme activate koohe-film --path="$WPCORE" --allow-root >/dev/null 2>&1 \
	&& ok "قالب koohe-film فعال شد (فهرست راهبری با after_switch_theme ساخته می‌شود)" \
	|| warn "فعال‌سازی قالب ناموفق بود"

for plugin in manacore-core manacore-sources manacore-subscriptions; do
	wp plugin activate "$plugin" --path="$WPCORE" --allow-root >/dev/null 2>&1 \
		&& ok "افزونه‌ی ${plugin} فعال شد" || warn "فعال‌سازی ${plugin} ناموفق بود"
done

wp eval-file "$PROJECT/wp/tests/qa-env/seed.php" --path="$WPCORE" --allow-root 2>&1 | tail -2
wp rewrite flush --path="$WPCORE" --allow-root >/dev/null 2>&1
ok "محتوای آزمون کاشته شد"

# ---------------------------------------------------------------------------
# ۴) ابزارهای مرورگری
# ---------------------------------------------------------------------------
step "ابزارهای مرورگری (playwright + axe-core)"

mkdir -p "$QADIR"
if [ ! -d "$QADIR/node_modules/playwright" ]; then
	( cd "$QADIR" && [ -f package.json ] || npm init -y >/dev/null 2>&1
	  npm i --silent playwright axe-core >/dev/null 2>&1 )
fi

if [ ! -d "$BROWSERS" ] || [ -z "$( ls -A "$BROWSERS" 2>/dev/null )" ]; then
	( cd "$QADIR" && PLAYWRIGHT_BROWSERS_PATH="$BROWSERS" npx --yes playwright install chromium >/dev/null 2>&1 )
fi

# کتابخانه‌های سیستمی مرورگر. بدون این‌ها اجرا با
# «error while loading shared libraries: libnspr4.so» شکست می‌خورد
# (یک‌بار در همین محیط روی داد و وقت گرفت).
if ! ldconfig -p 2>/dev/null | grep -q 'libnspr4\.so'; then
	( cd "$QADIR" && sudo npx --yes playwright install-deps chromium >/dev/null 2>&1 )
fi
ok "playwright/axe-core نصب شدند — مرورگر در ${BROWSERS}"

# اسکریپت‌های داخل مخزن به node_modules نیاز دارند؛ پیوند (نه کپی) تا با
# اسنپ‌شات پروژه قاطی نشود.
ln -sfn "$QADIR/node_modules" "$PROJECT/wp/tests/browser/node_modules"
ok "پیوند node_modules در wp/tests/browser"

# ---------------------------------------------------------------------------
# ۵) آماده
# ---------------------------------------------------------------------------
step "آماده است"
cat <<EOF

سرورها (هر کدام در یک ترمینال جدا یا با ابزار process):

  cinora (مرجع):  php -S 0.0.0.0:${REF_PORT} -t ${PROJECT}/cinora
  وردپرس (QA):     WP_ROOT=${WPCORE} php -S 0.0.0.0:${WP_PORT} -t ${WPCORE} \
                     ${PROJECT}/wp/tests/qa-env/router.php

سپس آزمون‌ها:

  bash ${PROJECT}/wp/tests/run.sh
  cd ${PROJECT}/wp/tests/browser && \\
    PLAYWRIGHT_BROWSERS_PATH=${BROWSERS} node harness-all.cjs

نشانی‌های کلیدی: /movie/the-godfather/ · /series/chernobyl/season-1/episode-1/ ·
/subscribe/ · /?s=test · ورود مدیر: admin / admin
EOF
