<?php
/**
 * آزمون مسیر ذخیره‌تا‌نمایش فیلدهای لینک: کیفیت، زبان/دوبله و انکودر هر لینک.
 *
 * چرا لازم است؟ کاربر در «افزودن لینک دانلود» برای هر قسمت، کیفیت و زبان و
 * انکودر را جدا تعیین می‌کند و هر کدام خالی بماند از گروه ارث می‌برد. اگر یکی
 * از این مراحل (پاک‌سازی ورودی، ذخیره، خواندن، تخت‌کردن یا گروه‌بندی قسمت‌ها)
 * مقدار را جا بیندازد، جدول دانلود و کنترل کیفیت صفحه‌ی پخش غلط نشان می‌دهند.
 * همچنین نشانی‌های ناامن باید حتی از مسیر ذخیره رد شوند.
 *
 * اجرا: php wp/tests/test-link-fields.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );

$GLOBALS['mc_pass'] = 0;
$GLOBALS['mc_fail'] = 0;

function mc_ok( $ok, $label, $extra = '' ) {
	if ( $ok ) {
		$GLOBALS['mc_pass']++;
		echo "  ✓ {$label}" . ( '' !== $extra ? "  — {$extra}" : '' ) . "\n";
	} else {
		$GLOBALS['mc_fail']++;
		echo "  ✗ {$label}" . ( '' !== $extra ? "  — {$extra}" : '' ) . "\n";
	}
}

/* ---------------------------------------------------------------
 * پوسته‌ی وردپرس (کمینه)
 * ------------------------------------------------------------ */

$GLOBALS['mc_meta'] = array(); // post_id => [ key => value ]

function get_post_meta( $post_id, $key = '', $single = false ) {
	$value = $GLOBALS['mc_meta'][ (int) $post_id ][ $key ] ?? '';
	return $single ? $value : array( $value );
}
function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['mc_meta'][ (int) $post_id ][ $key ] = $value;
	return true;
}
function delete_post_meta( $post_id, $key ) {
	unset( $GLOBALS['mc_meta'][ (int) $post_id ][ $key ] );
	return true;
}
function get_posts( $args = array() ) {
	return array();
}
function get_the_title( $post_id = 0 ) {
	return 'عنوان ' . (int) $post_id;
}
function wp_rand( $min = 0, $max = 0 ) {
	return 4;
}
function wp_slash( $v ) {
	return $v;
}
function wp_unslash( $v ) {
	return $v;
}
function absint( $v ) {
	return abs( (int) $v );
}
function sanitize_key( $v ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) );
}
function sanitize_text_field( $v ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $v ) ) );
}
/* هم‌رفتار esc_url_raw برای نشانی‌های http(s) و magnet؛ بقیه‌ی طرح‌ها تهی می‌شوند. */
function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );
	return preg_match( '#^(https?://|magnet:)#i', $url ) ? $url : '';
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function wp_json_encode( $v, $flags = 0 ) {
	return json_encode( $v, $flags );
}
function manacore_qualities() {
	return array( '480p' => '480p', '720p' => '720p', '1080p' => '1080p', '2160p' => '2160p' );
}
function manacore_languages() {
	return array( 'sub_fa' => 'زیرنویس فارسی', 'dub_fa' => 'دوبله فارسی', 'dual' => 'دو زبانه' );
}
function manacore_link_types() {
	return array( 'direct' => 'مستقیم', 'stream' => 'استریم', 'magnet' => 'مگنت' );
}

require_once MANACORE_PATH . 'includes/class-links.php';

use ManaCore\Core\Links;

/* ---------------------------------------------------------------
 * ۱) ذخیره: هر لینک مقدار خودش را دارد؛ خالی‌ها از گروه ارث می‌برند
 * ------------------------------------------------------------ */

echo "\n[ذخیره و خواندن: مقدار هر لینک و ارث از گروه]\n";

// همان شکلی که فرم ارسال می‌کند: گروه‌ها با فیلدهای خالی و عددهای رشته‌ای.
$posted = array(
	array(
		'id'       => 'grp_a',
		'title'    => 'فصل ۱',
		'season'   => '1',
		'quality'  => '720p',
		'language' => 'sub_fa',
		'encoder'  => 'GroupX',
		'size'     => '900MB',
		'items'    => array(
			array( 'id' => 'lnk_1', 'episode' => '1', 'url' => 'https://cdn.example/s01e01-1080.mkv', 'type' => 'direct', 'quality' => '1080p', 'language' => 'dub_fa', 'encoder' => 'FarsiPlus', 'size' => '' ),
			array( 'id' => 'lnk_2', 'episode' => '1', 'url' => 'https://cdn.example/s01e01-720.mkv', 'type' => 'direct', 'quality' => '', 'language' => '', 'encoder' => '', 'size' => '' ),
			array( 'id' => 'lnk_3', 'episode' => '2', 'url' => 'https://cdn.example/s01e02-custom.mkv', 'type' => 'direct', 'quality' => '1440p HDR', 'language' => '', 'encoder' => '', 'size' => '1.1GB' ),
			array( 'id' => 'lnk_4', 'episode' => '2', 'url' => 'https://cdn.example/s01e02-720.mkv', 'type' => 'direct', 'quality' => '', 'language' => '', 'encoder' => '', 'size' => '' ),
		),
	),
	array(
		'id'       => 'grp_b',
		'title'    => 'نسخه‌ی ۴K',
		'season'   => '1',
		'quality'  => '2160p',
		'language' => '',
		'encoder'  => '',
		'size'     => '',
		'items'    => array(
			array( 'id' => 'lnk_5', 'episode' => '1', 'url' => 'https://cdn.example/s01e01-4k.mkv', 'type' => 'direct', 'quality' => '', 'language' => '', 'encoder' => '', 'size' => '' ),
		),
	),
	array(
		'id'    => 'grp_bad',
		'title' => 'نشانی ناامن',
		'items' => array(
			array( 'id' => 'lnk_bad', 'episode' => '3', 'url' => 'javascript:alert(1)', 'type' => 'direct' ),
		),
	),
);

$clean = Links::sanitize( $posted );
update_post_meta( 10, Links::META_KEY, $clean );

$rows = Links::post_rows( 10 );
$by_url = array();
foreach ( $rows as $row ) {
	$by_url[ $row['url'] ] = $row;
}

$r1 = $by_url['https://cdn.example/s01e01-1080.mkv'] ?? array();
mc_ok( '1080p' === ( $r1['quality'] ?? '' ), 'کیفیت خود لینک (1080p) روی گروه (720p) می‌نشیند', $r1['quality'] ?? '' );
mc_ok( 'dub_fa' === ( $r1['language'] ?? '' ), 'زبان/دوبله‌ی خود لینک ذخیره و خوانده می‌شود', $r1['language'] ?? '' );
mc_ok( 'FarsiPlus' === ( $r1['encoder'] ?? '' ), 'انکودر خود لینک ذخیره و خوانده می‌شود', $r1['encoder'] ?? '' );

$r2 = $by_url['https://cdn.example/s01e01-720.mkv'] ?? array();
mc_ok( '720p' === ( $r2['quality'] ?? '' ), 'کیفیت خالی لینک از گروه ارث می‌برد', $r2['quality'] ?? '' );
mc_ok( 'sub_fa' === ( $r2['language'] ?? '' ), 'زبان خالی لینک از گروه ارث می‌برد', $r2['language'] ?? '' );
mc_ok( 'GroupX' === ( $r2['encoder'] ?? '' ), 'انکودر خالی لینک از گروه ارث می‌برد', $r2['encoder'] ?? '' );
mc_ok( '900MB' === ( $r2['size'] ?? '' ), 'حجم خالی لینک از گروه ارث می‌برد', $r2['size'] ?? '' );

$r3 = $by_url['https://cdn.example/s01e02-custom.mkv'] ?? array();
mc_ok( '1440p HDR' === ( $r3['quality'] ?? '' ), 'کیفیت آزاد (متن) بدون تغییر ذخیره می‌شود', $r3['quality'] ?? '' );
mc_ok( '1.1GB' === ( $r3['size'] ?? '' ), 'حجم خود لینک بر حجم گروه برتری دارد', $r3['size'] ?? '' );

$r5 = $by_url['https://cdn.example/s01e01-4k.mkv'] ?? array();
mc_ok( '2160p' === ( $r5['quality'] ?? '' ), 'گروه بدون زبان، کیفیت گروه را می‌دهد' );
mc_ok( '' === ( $r5['language'] ?? 'x' ), 'زبان اختیاری: اگر لینک و گروه هیچ‌کدام نداشته باشند، خالی می‌ماند' );

mc_ok( ! isset( $by_url['javascript:alert(1)'] ), 'نشانی javascript: حتی از مسیر ذخیره رد می‌شود' );
mc_ok( 5 === count( $rows ), 'پنج لینک معتبر ذخیره می‌شوند و لینک ناامن کنار گذاشته می‌شود', (string) count( $rows ) );

/* ---------------------------------------------------------------
 * ۲) قسمت‌ها: هر کیفیت یک گزینه‌ی جدا برای همان قسمت
 * ------------------------------------------------------------ */

echo "\n[گزینه‌های پخش یک قسمت: همه‌ی کیفیت‌ها]\n";

$variants = Links::episode_variants( 10, 1, 1 );
$keys     = array_map(
	static function ( $row ) {
		return Links::variant_key( $row['quality'], $row['type'], $row['language'], $row['encoder'] );
	},
	$variants
);

mc_ok( 3 === count( $variants ), 'قسمت ۱ سه گزینه دارد (1080p، 720p ارثی و 4K)', (string) count( $variants ) );
mc_ok( 3 === count( array_unique( $keys ) ), 'کلیدهای کیفیت یکتا هستند (کنترل کیفیت گزینه‌ی تکراری ندارد)', implode( ' | ', $keys ) );
mc_ok( in_array( '1080p · دوبله فارسی · FarsiPlus', $keys, true ), 'کلید کامل یک گزینه با زبان و انکودر ساخته می‌شود' );

$variants2 = Links::episode_variants( 10, 1, 2 );
mc_ok( 2 === count( $variants2 ), 'قسمت ۲ دو گزینه دارد (کیفیت آزاد و 720p)', (string) count( $variants2 ) );

mc_ok( array() === Links::episode_variants( 10, 1, 99 ), 'قسمت بدون لینک فهرست خالی می‌دهد' );

/* ---------------------------------------------------------------
 * ۳) کلید گزینه: کیفیت خالی جای خود را به نوع می‌دهد
 * ------------------------------------------------------------ */

echo "\n[کلید گزینه‌ها]\n";

mc_ok( 'استریم' === Links::variant_key( '', 'stream' ), 'کیفیت خالی با برچسب نوع جایگزین می‌شود', Links::variant_key( '', 'stream' ) );
mc_ok( '720p · زیرنویس فارسی' === Links::variant_key( '720p', 'direct', 'sub_fa', '' ), 'زبان بدون انکودر در کلید می‌آید' );
mc_ok( '' === Links::variant_key( '', '', '', '' ), 'همه‌ی فیلدهای خالی کلید خالی می‌دهد' );

/* ---------------------------------------------------------------
 * ۴) ورودی‌های بد
 * ------------------------------------------------------------ */

echo "\n[ورودی‌های نامعتبر]\n";

mc_ok( array() === Links::sanitize( 'not-json{' ), 'رشته‌ی ناسالم JSON تهی برمی‌گرداند' );
mc_ok( array() === Links::sanitize( 42 ), 'ورودی غیرآرایه تهی برمی‌گرداند' );

echo "\n==========================================================\n";
echo "موفق: {$GLOBALS['mc_pass']}   ناموفق: {$GLOBALS['mc_fail']}\n";
echo "==========================================================\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
