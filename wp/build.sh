#!/usr/bin/env bash
#
# ساخت بسته‌های نصبی (ZIP) برای افزونه‌ها و قالب.
# استفاده: bash wp/build.sh
#
set -euo pipefail

WP_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DIST="${WP_DIR}/dist"

# الگوهای حذفی مشترک (فایل‌های توسعه که نباید در بسته‌ی نصبی باشند).
EXCLUDES=(
	"*/.git/*" "*/.git" ".git/*"
	"*/.DS_Store" "*.DS_Store"
	"*/node_modules/*" "*/node_modules"
	"*/.wrangler/*"
	"*.map"
	"*/tests/*"
	"*.log"
	"*/.editorconfig"
	"*/phpcs.xml*"
	"*/composer.lock"
)

build_excludes() {
	local out=()
	for pattern in "${EXCLUDES[@]}"; do
		out+=( -x "$pattern" )
	done
	printf '%s\n' "${out[@]}"
}

package() {
	local base_dir="$1"   # plugins | themes
	local slug="$2"
	local src="${WP_DIR}/${base_dir}/${slug}"

	if [ ! -d "$src" ]; then
		echo "  ! پوشه یافت نشد: ${src}" >&2
		return 1
	fi

	# نسخه فقط از هدر فایل اصلی خوانده می‌شود (نه از کل درخت) تا با
	# اعدادی مثل api_version اشتباه گرفته نشود.
	local header
	if [ "themes" = "$base_dir" ]; then
		header="${src}/style.css"
	else
		header="${src}/${slug}.php"
	fi

	local version="1.0.0"
	if [ -f "$header" ]; then
		version="$( grep -m1 -oE '^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*[0-9][0-9A-Za-z.\-]*' "$header" \
			| grep -oE '[0-9][0-9A-Za-z.\-]*$' || echo '1.0.0' )"
	fi

	local zip_file="${DIST}/${slug}-${version}.zip"
	rm -f "$zip_file"

	# از پوشه‌ی والد zip می‌گیریم تا آرشیو یک پوشه‌ی ریشه به نام اسلاگ داشته باشد.
	( cd "${WP_DIR}/${base_dir}" && zip -rq "$zip_file" "$slug" $(build_excludes | tr '\n' ' ') )

	local size
	size="$( du -h "$zip_file" | cut -f1 )"
	local files
	files="$( unzip -l "$zip_file" | tail -1 | awk '{print $2}' )"
	printf '  ✓ %-34s %-8s %6s  (%s فایل)\n' "$( basename "$zip_file" )" "v${version}" "$size" "$files"
}

echo "ساخت بسته‌های نصبی ManaCore / Koohe Film"
echo "----------------------------------------------------------"

rm -rf "$DIST"
mkdir -p "$DIST"

# فایل‌های ترجمه پیش از بسته‌بندی بازتولید می‌شوند تا همیشه با کد هم‌گام باشند.
if command -v php >/dev/null 2>&1 && [ -f "${WP_DIR}/makepot.php" ]; then
	php "${WP_DIR}/makepot.php" >/dev/null 2>&1 \
		&& echo "  ✓ فایل‌های ترجمه (.pot) بازتولید شد" \
		|| echo "  ! بازتولید فایل‌های ترجمه ناموفق بود (ادامه می‌دهیم)" >&2
fi

echo "افزونه‌ها:"
for slug in manacore-core manacore-sources manacore-subscriptions; do
	package plugins "$slug"
done

echo "قالب:"
package themes koohe-film

echo "----------------------------------------------------------"
echo "بسته‌ی کامل (همه با هم):"
BUNDLE="${DIST}/koohe-film-complete.zip"
rm -f "$BUNDLE"
( cd "$DIST" && zip -q "$BUNDLE" ./*.zip )
printf '  ✓ %-34s %6s\n' "$( basename "$BUNDLE" )" "$( du -h "$BUNDLE" | cut -f1 )"

echo "----------------------------------------------------------"
echo "خروجی در: ${DIST}"
