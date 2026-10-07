<?php
/**
 * آزمون تبدیل تاریخ شمسی و رقم‌های فارسی.
 *
 * چرا این آزمون؟ نخستین نسخه‌ی `manacore_gregorian_to_jalali()` از
 * «شمار روزهای ماه» به‌جای «روزهای سپری‌شده تا ابتدای ماه» استفاده
 * می‌کرد و تاریخ را حدود یک ماه جابه‌جا نشان می‌داد. این آزمون با
 * تاریخ‌های مرجعِ نوروز (تأییدشده از منابع تقویم) از بازگشتش جلوگیری
 * می‌کند.
 *
 * اجرا: php wp/tests/test-dates.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_URL', 'http://example.test/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );

function __( $t, $d = '' ) { return $t; }
function apply_filters( $tag, $value ) { return $value; }
function add_action() {}
function add_filter() {}
function wp_date( $format, $time = null ) {
	return gmdate( $format, null === $time ? time() : $time );
}

require_once MANACORE_PATH . 'includes/functions.php';

$pass = 0;
$fail = 0;

function check( $ok, $label, $extra = '' ) {
	global $pass, $fail;

	if ( $ok ) {
		$pass++;
		echo "  ✓ $label" . ( $extra ? "  — $extra" : '' ) . "\n";
	} else {
		$fail++;
		echo "  ✗ $label" . ( $extra ? "  — $extra" : '' ) . "\n";
	}
}

echo "\n=== ۱) تبدیل میلادی به شمسی (تاریخ‌های مرجع نوروز) ===\n";

/*
 * مرجع‌ها (دو منبع مستقل تقویم):
 *   1403 → ۲۰ مارس ۲۰۲۴ تا ۲۰ مارس ۲۰۲۵ (سال کبیسه؛ ۳۰ اسفند ۱۴۰۳ = ۲۰ مارس ۲۰۲۵)
 *   1404 → ۲۱ مارس ۲۰۲۵ تا ۲۰ مارس ۲۰۲۶
 *   1405 → ۲۱ مارس ۲۰۲۶ تا ۲۰ مارس ۲۰۲۷
 */
$vectors = array(
	array( '2024-03-20', '1403-01-01' ),
	array( '2024-03-19', '1402-12-29' ),
	array( '2025-03-20', '1403-12-30' ),
	array( '2025-03-21', '1404-01-01' ),
	array( '2026-03-21', '1405-01-01' ),
	array( '2011-01-01', '1389-10-11' ),
	array( '2002-08-30', '1381-06-08' ),
	array( '2026-10-03', '1405-07-11' ),
);

foreach ( $vectors as $vector ) {
	list( $gregorian, $expected ) = $vector;
	list( $gy, $gm, $gd )        = array_map( 'intval', explode( '-', $gregorian ) );
	list( $jy, $jm, $jd )        = manacore_gregorian_to_jalali( $gy, $gm, $gd );

	$got = sprintf( '%04d-%02d-%02d', $jy, $jm, $jd );

	check( $got === $expected, "$gregorian → $expected", $got === $expected ? '' : "دریافت: $got" );
}

echo "\n=== ۲) قالب‌بندی فارسی ===\n";

check( '۱۰ مهر ۱۴۰۳' === manacore_fa_date( '2024-10-01' ), 'قالب «روز ماه سال» با رقم فارسی', manacore_fa_date( '2024-10-01' ) );
check( '۱۱ مهر' === manacore_fa_date( '2024-10-02', false ), 'بدون سال وقتی with_year=false', manacore_fa_date( '2024-10-02', false ) );
check( '' === manacore_fa_date( 'not-a-date' ), 'ورودی نامعتبر رشته‌ی خالی می‌دهد' );
check( '۱۲۳۴۵۶۷۸۹۰' === manacore_fa_digits( '1234567890' ), 'تبدیل رقم‌های لاتین به فارسی' );
check( '۰۷' === manacore_fa_digits( '07' ), 'صفر ابتدایی حفظ می‌شود' );

echo "\n=== ۳) ترتیب و بازه‌ی ماه‌های شمسی ===\n";

$month_lengths = array();
foreach ( array( '2026-04-10', '2026-05-10', '2026-06-10', '2026-07-10', '2026-08-10', '2026-09-10', '2026-10-10', '2026-11-10', '2026-12-10' ) as $d ) {
	list( , $jm ) = manacore_gregorian_to_jalali( (int) substr( $d, 0, 4 ), (int) substr( $d, 5, 2 ), (int) substr( $d, 8, 2 ) );
	$month_lengths[] = $jm;
}

$sorted = $month_lengths;
sort( $sorted );

check( $month_lengths === $sorted, 'ماه‌های شمسی با پیشرفت زمان صعودی می‌مانند', implode( ',', $month_lengths ) );
check( min( $month_lengths ) >= 1 && max( $month_lengths ) <= 12, 'همه‌ی ماه‌ها در بازه‌ی ۱ تا ۱۲ هستند' );

echo "\n";
echo "==========================================================\n";
echo "موفق: $pass   ناموفق: $fail\n";
echo "==========================================================\n";

exit( $fail > 0 ? 1 : 0 );
