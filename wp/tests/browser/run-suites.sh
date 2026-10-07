#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# اجراکننده‌ی همه‌ی سوئیت‌های مرورگری پاریتی/QA روی نصب آزمون.
#
# چرا جدا از wp/tests/run.sh؟ آن اسکریپت یک هارنس سریع و بی‌مرورگر است (نحو PHP/JS،
# fixtureها، ریاضیات تاریخ، قواعد ویرایشگر/رنگ) و در چند ثانیه تمام می‌شود. سوئیت‌های
# این پوشه به مرورگر واقعی، به نصب وردپرس روی 8099 و به مرجع ایستا روی 8098 نیاز دارند،
# پس با این اسکریپت اجرا می‌شوند تا «یک فرمان» برای بازآزمایی کامل وجود داشته باشد.
#
# استفاده:
#   bash wp/tests/browser/run-suites.sh                 # برچسب پیش‌فرض: full
#   bash wp/tests/browser/run-suites.sh w19             # برچسب دلخواه (نام لاگ‌ها)
#   bash wp/tests/browser/run-suites.sh w19 --seed      # اول داده‌ی آزمون را می‌کارد
#   bash wp/tests/browser/run-suites.sh w19 --only article-parity.cjs,home-parity.cjs
#
# پیش‌نیاز سرورها: مرجع 8098 و وردپرس 8099. اگر بالا نباشند، اسکریپت پیش از شروع
# هشدار می‌دهد و با کد ۲ بیرون می‌آید (تا نتیجه‌ی «سبز» الکی ساخته نشود).
# ---------------------------------------------------------------------------
set -uo pipefail

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
WP_PATH=/home/user/.cache/wp
PLAYWRIGHT_BROWSERS_PATH="${PLAYWRIGHT_BROWSERS_PATH:-/home/user/.cache/pw-browsers}"
TAG="full"; DO_SEED=0; ONLY=""; TAG_SET=0
while [ "$#" -gt 0 ]; do
  case "$1" in
    --seed)     DO_SEED=1 ;;
    --only)     shift; ONLY="${1:-}" ;;
    --only=*)   ONLY="${1#--only=}" ;;
    -*)         echo "گزینه‌ی ناشناس: $1" >&2; exit 2 ;;
    *)          if [ "$TAG_SET" -eq 0 ]; then TAG="$1"; TAG_SET=1; fi ;;
  esac
  shift
done

# سوئیت‌ها = همان پرونده‌هایی که در گزارش QA به‌عنوان آزمون ثبت شده‌اند.
# کاوشگرها (probe-*، mega-*) عمداً اینجا نیستند: آن‌ها ابزار سنجش یک‌باره‌ی موج‌ها هستند
# و به‌عنوان شاهد خام در گزارش به آن‌ها ارجاع داده شده؛ اجرای هرموج‌شان لازم نیست.
SUITES=(
  harness-all.cjs
  home-parity.cjs
  browse-parity.cjs
  schedule-parity.cjs
  account-parity.cjs
  player-parity.cjs
  live-parity.cjs
  download-parity.cjs
  magazine-parity.cjs
  article-parity.cjs
  info-parity.cjs
  about-parity.cjs
  cast-parity.cjs
  plans-parity.cjs
  drawer-parity.cjs
  picker-e2e.cjs
  editor-blocks-support.cjs
  editor-schedule.cjs
  editor-templates-audit.cjs
  lang-audit.cjs
  width-audit.cjs
  parity-shots.cjs
  axe-drawer.cjs
  axe-episode.cjs
  axe-player.cjs
)

if [ -n "$ONLY" ]; then
  IFS=',' read -r -a SUITES <<< "$ONLY"
fi

echo "=========================================================="
echo "آزمون‌های مرورگری — برچسب: $TAG — $( date '+%H:%M:%S' )"
echo "=========================================================="

# --- پیش‌نیاز: سرورها -------------------------------------------------------
for port in 8098 8099; do
  code="$( curl -s -o /dev/null -w '%{http_code}' "http://localhost:$port/" || true )"
  if [ "$code" != "200" ]; then
    echo "✗ سرور روی درگاه $port پاسخ ۲۰۰ نداد (کد: ${code:-بی‌پاسخ}) — آزمون اجرا نشد." >&2
    exit 2
  fi
done

# --- داده‌ی آزمون (اختیاری) --------------------------------------------------
if [ "$DO_SEED" -eq 1 ]; then
  echo "── کاشت داده‌ی آزمون ──"
  for seed in seed.php seed-article.php seed-magazine.php seed-info.php seed-cast.php seed-live.php seed-account.php; do
    out="$( cd "$DIR/.." && wp --path="$WP_PATH" eval-file "$DIR/../qa-env/$seed" 2>&1 )"
    printf '  %-22s %s\n' "$seed" "$( echo "$out" | tail -1 )"
  done
fi

mkdir -p /tmp/sweep-raw
LOG="/tmp/sweep-$TAG.txt"; : > "$LOG"
pass=0; fail=0; missing=0
for suite in "${SUITES[@]}"; do
  if [ ! -f "$DIR/$suite" ]; then
    printf 'MISS  %-32s پرونده وجود ندارد\n' "$suite" | tee -a "$LOG"
    missing=$(( missing + 1 )); continue
  fi
  raw="/tmp/sweep-raw/$TAG-${suite%.cjs}.txt"
  ( cd "$DIR" && PLAYWRIGHT_BROWSERS_PATH="$PLAYWRIGHT_BROWSERS_PATH" node "$suite" ) > "$raw" 2>&1
  code=$?
  summary="$( grep -Eo 'موفق:?[[:space:]]*[0-9۰-۹]+[[:space:]]+ناموفق:?[[:space:]]*[0-9۰-۹]+' "$raw" | tail -1 )"
  [ -z "$summary" ] && summary="$( grep -Eo 'PASS: [0-9]+[[:space:]]+FAIL: [0-9]+|passed[:]? [0-9]+[^,]*failed[:]? [0-9]+' "$raw" | tail -1 )"
  [ -z "$summary" ] && summary="$( grep -E 'PASS:|passed|موفق' "$raw" | tail -1 | cut -c1-60 )"
  if [ "$code" -eq 0 ]; then
    printf 'PASS  %-32s %s\n' "$suite" "$summary" | tee -a "$LOG"
    pass=$(( pass + 1 ))
  else
    printf 'FAIL  %-32s %s (exit %s)\n' "$suite" "$summary" "$code" | tee -a "$LOG"
    grep '✗' "$raw" | head -8 | tee -a "$LOG"
    fail=$(( fail + 1 ))
  fi
done
echo "---- $TAG: $pass PASS / $fail FAIL / $missing MISSING (خام: /tmp/sweep-raw/$TAG-*.txt · خلاصه: $LOG) ----" | tee -a "$LOG"
[ "$fail" -eq 0 ] && [ "$missing" -eq 0 ]
