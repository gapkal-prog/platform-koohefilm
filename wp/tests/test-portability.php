<?php
/**
 * آزمون «تحویل حرفه‌ای»: پشتیبان‌گیری/بازگردانی تنظیمات، محتوای نمایشی و
 * فرمان‌های WP-CLI.
 *
 * بدون وردپرس اجرا می‌شود: هر تماس وردپرسی با پوسته‌ی ساختگی پاسخ داده
 * می‌شود تا بتوان دقیقاً سنجید که چه چیزی ذخیره، چه کلیدی رد و چه نوشته‌ای
 * ساخته/حذف می‌شود.
 *
 * سه چیز مهم این آزمون:
 *   ۱. نقشه‌ی کلیدها کامل باشد؛ یعنی خروجی `sanitize()` هیچ کلیدی بیرون از
 *      `Settings::keys_by_tab()` نداشته باشد. اگر کلیدی تازه اضافه شد و در
 *      نقشه نیامد، بازگردانی تنظیمات آن کلید را از دست می‌داد.
 *   ۲. بازگردانی «ادغامی» باشد: نبودِ یک کلید در فایل، مقدار ذخیره‌شده را
 *      صفر نکند و کلید ناشناخته هرگز ذخیره نشود.
 *   ۳. محتوای نمایشی قابل بازگشت باشد: همه‌ی نوشته‌های ساخته‌شده نشانه
 *      بگیرند و `remove()` فقط همان‌ها را حذف کند.
 *
 * اجرا: php wp/tests/test-portability.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_URL', 'http://example.test/plugins/manacore-core/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['mc_pass'] = 0;
$GLOBALS['mc_fail'] = 0;

function mc_ok( $ok, $label, $extra = '' ) {
	$GLOBALS[ $ok ? 'mc_pass' : 'mc_fail' ]++;

	$extra = preg_replace( '/\s+/', ' ', (string) $extra );

	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $label . ( '' !== $extra ? '  — ' . substr( $extra, 0, 300 ) : '' ) . "\n";
}

/* ---------------------------------------------------------------
 * پوسته‌ی وردپرس
 * ------------------------------------------------------------ */

$GLOBALS['mc_options']     = array();
$GLOBALS['mc_meta']        = array();
$GLOBALS['mc_types_by_id'] = array();
$GLOBALS['mc_pages']       = array();   // نامک => شناسه
$GLOBALS['mc_terms']       = 0;
$GLOBALS['mc_inserted']    = array();
$GLOBALS['mc_deleted']     = array();
$GLOBALS['mc_comments']    = array();
$GLOBALS['mc_next_id']     = 100;
$GLOBALS['mc_counts']      = array();

function __( $t, $d = '' ) { return $t; }
function _e( $t, $d = '' ) { echo $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_attr__( $t, $d = '' ) { return $t; }
function esc_html_e( $t, $d = '' ) { echo $t; }
function esc_attr_e( $t, $d = '' ) { echo $t; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_js( $v ) { return (string) $v; }
function esc_url( $v ) { return (string) $v; }
function esc_url_raw( $v ) { return filter_var( (string) $v, FILTER_SANITIZE_URL ); }
function esc_textarea( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function apply_filters( $tag, $value ) { return $value; }
function add_action() {}
function add_filter() {}
function do_action() {}
function register_setting() {}
function settings_fields( $g ) {}
function submit_button( $t = '', $type = 'primary', $name = 'submit', $wrap = true, $other = array() ) {}
function wp_nonce_field( $a = '', $b = '' ) {}
function wp_die( $m = '', $c = 0 ) { throw new RuntimeException( (string) $m ); }
function nocache_headers() {}
function is_admin() { return true; }
function current_user_can() { return true; }
function check_admin_referer( $a = '' ) { return true; }
function wp_unslash( $v ) { return $v; }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function wp_json_encode( $v, $f = 0 ) { return (string) json_encode( $v, $f | JSON_UNESCAPED_UNICODE ); }
function wp_slash( $v ) { return $v; }
function wp_rand( $min = 0, $max = 0 ) { return 12345; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_title( $v ) { return strtolower( trim( preg_replace( '/[\s_]+/', '-', (string) $v ) ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_\- ]/', '', (string) $v ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function home_url( $p = '' ) { return 'http://example.test' . $p; }
function admin_url( $p = '' ) { return 'http://example.test/wp-admin/' . $p; }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ? $timestamp : time() ); }
function delete_expired_transients( $force = false ) { $GLOBALS['mc_expired_deleted'] = true; }
function flush_rewrite_rules( $hard = true ) { $GLOBALS['mc_rewritten'] = true; }
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['mc_options'] ) ? $GLOBALS['mc_options'][ $k ] : $d;
}
function update_option( $k, $v ) { $GLOBALS['mc_options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['mc_options'][ $k ] ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['mc_options'][ 'transient_' . $k ] ); return true; }
function add_query_arg( $args, $url = '' ) {
	$parts = array();
	foreach ( (array) $args as $k => $v ) {
		$parts[] = rawurlencode( $k ) . '=' . rawurlencode( (string) $v );
	}
	return (string) $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . implode( '&', $parts );
}
function taxonomy_exists( $t ) { return in_array( $t, array( 'genre', 'country', 'language', 'quality' ), true ); }
function post_type_exists( $t ) { return in_array( $t, (array) ( $GLOBALS['mc_post_types'] ?? array( 'movie', 'series', 'anime', 'episode', 'person', 'collection' ) ), true ); }
function get_post_type( $id = 0 ) { return $GLOBALS['mc_types_by_id'][ (int) $id ] ?? 'movie'; }
function get_post_status( $id = 0 ) { return isset( $GLOBALS['mc_types_by_id'][ (int) $id ] ) ? 'publish' : false; }
function get_post_field( $field, $id ) {
	if ( 'post_name' !== $field || ! isset( $GLOBALS['mc_pages_by_id'][ (int) $id ] ) ) {
		return '';
	}
	return $GLOBALS['mc_pages_by_id'][ (int) $id ];
}
function wp_count_posts( $type = 'post' ) {
	$o          = new stdClass();
	$o->publish = (int) ( $GLOBALS['mc_counts'][ $type ] ?? 0 );
	$o->draft   = 0;
	return $o;
}
function get_page_by_path( $path, $output = OBJECT, $type = 'page' ) {
	if ( ! isset( $GLOBALS['mc_pages'][ $path ] ) ) {
		return null;
	}
	$o            = new WP_Post();
	$o->ID        = (int) $GLOBALS['mc_pages'][ $path ];
	$o->post_name = $path;
	return $o;
}
function get_comments( $args = array() ) { return 0; }
function wp_insert_comment( $data ) {
	$id = ++$GLOBALS['mc_next_id'];
	$GLOBALS['mc_comments'][ $id ] = $data;
	return $id;
}
function wp_insert_post( $arr, $wp_error = false ) {
	$id = ++$GLOBALS['mc_next_id'];

	$GLOBALS['mc_inserted'][ $id ] = $arr;
	$GLOBALS['mc_types_by_id'][ $id ] = (string) ( $arr['post_type'] ?? 'post' );

	return $id;
}
function wp_delete_post( $id, $force = false ) {
	$GLOBALS['mc_deleted'][] = (int) $id;
	unset( $GLOBALS['mc_types_by_id'][ (int) $id ], $GLOBALS['mc_meta'][ (int) $id ] );
	return true;
}
function update_post_meta( $id, $key, $value ) { $GLOBALS['mc_meta'][ (int) $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['mc_meta'][ (int) $id ][ $key ] ); return true; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['mc_meta'][ (int) $id ][ $key ] ?? ''; }
function term_exists( $term, $tax = '' ) { return false; }
function wp_insert_term( $term, $tax, $args = array() ) { $GLOBALS['mc_terms']++; return array( 'term_id' => $GLOBALS['mc_terms'] ); }
function wp_set_object_terms( $id, $terms, $tax, $append = false ) { return $terms; }
function get_posts( $args = array() ) {
	$ids = array();

	foreach ( $GLOBALS['mc_meta'] as $id => $meta ) {
		if ( isset( $args['meta_key'] ) && array_key_exists( $args['meta_key'], $meta ) ) {
			$ids[] = $id;
		}
	}

	return $ids;
}

class WP_Post {
	public $ID = 0;
	public $post_name = '';
}

class WP_Error {
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

/**
 * پوسته‌ی `$wpdb` (فقط دو کوئری پاک‌سازی به آن می‌خورد).
 */
class MC_Wpdb {
	public $prefix = 'wp_';
	public $posts  = 'wp_posts';
	public $options = 'wp_options';
	public $queries = array();
	public $vars    = array();

	public function esc_like( $t ) { return addcslashes( (string) $t, '_%\\' ); }
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$args = array_map(
			static function ( $a ) {
				return is_int( $a ) ? $a : "'" . $a . "'";
			},
			$args
		);
		return vsprintf( str_replace( array( '%s', '%d' ), array( '%s', '%d' ), $query ), $args );
	}
	public function get_var( $q = '' ) {
		$this->queries[] = $q;
		return array_shift( $this->vars );
	}
	public function query( $q ) {
		$this->queries[] = $q;
		return 1;
	}
}

$GLOBALS['wpdb'] = new MC_Wpdb();

class WP_CLI {
	public static $lines = array();
	public static function line( $m ) { self::$lines[] = (string) $m; }
	public static function log( $m ) { self::$lines[] = (string) $m; }
	public static function success( $m ) { self::$lines[] = 'SUCCESS ' . (string) $m; }
	public static function warning( $m ) { self::$lines[] = 'WARNING ' . (string) $m; }
	public static function error( $m ) { throw new RuntimeException( (string) $m ); }
	public static function add_command( $n, $c, $a = array() ) { self::$lines[] = 'COMMAND ' . $n; }
}

/* ---------------------------------------------------------------
 * بارگذاری کلاس‌های واقعی
 * ------------------------------------------------------------ */

require_once MANACORE_PATH . 'includes/functions.php';
require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-settings.php';
require_once MANACORE_PATH . 'includes/class-links.php';
/*
 * `Settings::sanitize()` کرانه‌ی اعتبار لینک دانلود را از ثابت‌های
 * `Downloads` می‌خواند (یک منبع حقیقت)، پس این کلاس هم باید بارگذاری شود.
 */
require_once MANACORE_PATH . 'includes/class-downloads.php';
require_once MANACORE_PATH . 'includes/class-install.php';
require_once MANACORE_PATH . 'includes/class-portability.php';
require_once MANACORE_PATH . 'includes/class-demo.php';
require_once MANACORE_PATH . 'includes/class-cli.php';

use ManaCore\Core\Cli;
use ManaCore\Core\Demo;
use ManaCore\Core\Portability;
use ManaCore\Core\Settings;

/* ---------------------------------------------------------------
 * ۱) نقشه‌ی کلیدها
 * ------------------------------------------------------------ */

echo "\n=== ۱) نقشه‌ی کلیدها و پاک‌سازی ===\n";

$map   = Settings::keys_by_tab();
$tabs  = array_keys( Settings::tabs() );
$plain = Settings::option_keys();

mc_ok( $plain === array_values( array_unique( $plain ) ), 'هیچ کلیدی در دو تب تکرار نشده است' );
mc_ok( ! array_diff( array_keys( $map ), $tabs ), 'هر تب نقشه در فهرست تب‌ها هست' );
mc_ok( in_array( 'requests_per_page', $plain, true ), 'کلید تب درخواست‌ها در نقشه هست' );
mc_ok( in_array( 'ads_positions', $plain, true ), 'کلید جایگاه‌های تبلیغاتی در نقشه هست' );

/*
 * نگهبان «هم‌خوانی پنل و نقشه» — دو خطای بی‌صدای ممکن:
 *   ۱. نام فیلدی در پنل با کلید نقشه یکی نباشد (تایپ) → ذخیره‌سازی آن
 *      تنظیم بی‌صدا دور می‌ریزد؛
 *   ۲. کلیدی در نقشه باشد ولی هیچ‌جا در پنل رندر نشود → گزینه‌ای که مدیر
 *      هرگز نمی‌بیند.
 */
$settings_src = (string) file_get_contents( MANACORE_PATH . 'includes/class-settings.php' );
$fields       = array();

if ( preg_match_all( '/manacore_settings\[([a-z0-9_]+)\]/', $settings_src, $matches ) ) {
	$fields = array_values( array_unique( $matches[1] ) );
}

$unknown_fields = array_values( array_diff( $fields, array_merge( $plain, array( '_tab' ) ) ) );
mc_ok( array() === $unknown_fields, 'هر نام فیلد پنل یک کلید شناخته‌شده است', implode( ',', $unknown_fields ) );

$unseen = array();

foreach ( $plain as $key ) {
	if ( false === strpos( $settings_src, "'" . $key . "'" ) ) {
		$unseen[] = $key;
	}
}

mc_ok( array() === $unseen, 'هر کلید نقشه در پنل دیده می‌شود (گزینه‌ی پنهان نداریم)', implode( ',', $unseen ) );
mc_ok(
	array( 'general', 'watch', 'requests', 'ads', 'mega', 'analytics', 'tools' ) === $tabs,
	'نام و ترتیب تب‌های پنل همان ترتیب مستندشده است'
);

/* ورودی «همه‌ی کلیدها» ساخته می‌شود تا پوشش نقشه با خروجی واقعی سنجیده شود. */
$sink = array( '_tab' => 'general' );

foreach ( $plain as $key ) {
	if ( 'ads_positions' === $key ) {
		$sink[ $key ] = array( 'top' );
	} elseif ( in_array( $key, array( 'items_per_page', 'requests_per_page', 'ads_per_position', 'mega_terms', 'mega_columns', 'mega_featured_id' ), true ) ) {
		$sink[ $key ] = 2;
	} elseif ( in_array( $key, array( 'slug_movie', 'slug_series', 'slug_anime', 'slug_episode', 'slug_person', 'slug_collection' ), true ) ) {
		$sink[ $key ] = 'film';
	} elseif ( in_array( $key, array( 'default_color_mode' ), true ) ) {
		$sink[ $key ] = 'dark';
	} elseif ( in_array( $key, array( 'mega_taxonomy' ), true ) ) {
		$sink[ $key ] = 'genre';
	} elseif ( in_array( $key, array( 'requests_intro', 'requests_thanks', 'download_notice_text', 'player_notice_text', 'custom_qualities' ), true ) ) {
		$sink[ $key ] = "متن\nنمونه";
	} else {
		$sink[ $key ] = 1;
	}
}

$clean = array();

foreach ( array_keys( $map ) as $tab ) {
	$tab_input          = $sink;
	$tab_input['_tab']  = $tab;
	$clean              = array_merge( $clean, Settings::instance()->sanitize( $tab_input ) );
}

mc_ok( ! array_diff( array_keys( $clean ), $plain ), 'خروجی sanitize بیرون از نقشه کلیدی نمی‌سازد' );
mc_ok( ! array_diff( $plain, array_keys( $clean ) ), 'همه‌ی کلیدهای نقشه از ورودی کامل برمی‌گردند', implode( ', ', array_diff( $plain, array_keys( $clean ) ) ) );

/* ---------------------------------------------------------------
 * ۲) بسته‌ی پشتیبان
 * ------------------------------------------------------------ */

echo "\n=== ۲) بسته‌ی پشتیبان (export) ===\n";

$GLOBALS['mc_options']['manacore_settings']   = array( 'items_per_page' => 12, 'enable_ratings' => 1 );
$GLOBALS['mc_options']['manacore_watch_page'] = 55;
$GLOBALS['mc_pages_by_id']                    = array( 55 => 'watch' );

$payload = Portability::export();

mc_ok( 'manacore-settings' === $payload['format'], 'نام قالب فایل درست است' );
mc_ok( 1 === $payload['version'], 'نسخه‌ی قالب فایل درست است' );
mc_ok( MANACORE_VERSION === $payload['plugin'], 'نسخه‌ی افزونه در فایل ثبت می‌شود' );
mc_ok( 12 === ( $payload['settings']['items_per_page'] ?? 0 ), 'مقادیر تنظیمات در فایل می‌آید' );
mc_ok( 'watch' === ( $payload['pages']['manacore_watch_page'] ?? '' ), 'برگه‌ی پخش با نامک منتقل می‌شود (نه شناسه)' );
mc_ok( false !== strpos( $payload['site'], 'example.test' ), 'نشانی سایت مبدأ در فایل هست' );

$json = Portability::to_json();
$back = json_decode( $json, true );

mc_ok( is_array( $back ) && 'manacore-settings' === $back['format'], 'خروجی JSON خوانا است' );
mc_ok( is_string( $json ) && false === strpos( $json, '\\u06' ), 'نویسه‌های فارسی در JSON escape نمی‌شوند' );
mc_ok( (bool) preg_match( '/manacore-settings-\d{4}-\d{2}-\d{2}\.json/', Portability::file_name() ), 'نام فایل پیشنهادی تاریخ دارد' );

/* ---------------------------------------------------------------
 * ۳) اعتبارسنجی ورودی
 * ------------------------------------------------------------ */

echo "\n=== ۳) اعتبارسنجی ورودی (import) ===\n";

$cases = array(
	''                                => 'empty',
	'این یک متن ساده است'             => 'json',
	'{"format":"other-plugin"}'       => 'format',
	'{"format":"manacore-settings","version":99}' => 'version',
	'{"format":"manacore-settings","version":1}'  => '',
);

foreach ( $cases as $raw => $expected ) {
	$result = Portability::import( $raw );
	$first  = $result['errors'][0] ?? '';

	mc_ok( $expected === $first, 'ورودی نامعتبر «' . ( '' === $raw ? '(خالی)' : substr( $raw, 0, 22 ) ) . '» با دلیل «' . ( '' === $expected ? 'بدون خطا' : $expected ) . '» پاسخ می‌گیرد', $first );
}

$big = Portability::import( str_repeat( 'x', Portability::MAX_BYTES + 10 ) );
mc_ok( 'size' === ( $big['errors'][0] ?? '' ), 'ورودی بزرگ‌تر از سقف رد می‌شود' );

/* ---------------------------------------------------------------
 * ۴) اعمال بازگردانی
 * ------------------------------------------------------------ */

echo "\n=== ۴) اعمال بازگردانی ===\n";

$GLOBALS['mc_options']['manacore_settings']   = array( 'items_per_page' => 12, 'enable_ratings' => 1 );
$GLOBALS['mc_options']['manacore_watch_page'] = 0;
$GLOBALS['mc_pages']                          = array( 'watch' => 55 );

$result = Portability::import(
	array(
		'format'   => 'manacore-settings',
		'version'  => 1,
		'settings' => array( 'items_per_page' => 40, 'mega_title' => 'پنل تازه', 'evil_key' => 'x' ),
		'pages'    => array( 'manacore_watch_page' => 'watch' ),
	)
);

mc_ok( $result['ok'], 'بازگردانی معتبر موفق است' );
mc_ok( 2 === $result['applied'], 'فقط کلیدهای شناخته‌شده شمرده می‌شوند', (string) $result['applied'] );
mc_ok( array( 'evil_key' ) === $result['skipped'], 'کلید ناشناخته در گزارش می‌آید' );
mc_ok( ! isset( $GLOBALS['mc_options']['manacore_settings']['evil_key'] ), 'کلید ناشناخته هرگز ذخیره نمی‌شود' );
mc_ok( 40 === $GLOBALS['mc_options']['manacore_settings']['items_per_page'], 'مقدار تازه ذخیره شد' );
mc_ok( 1 === $GLOBALS['mc_options']['manacore_settings']['enable_ratings'], 'تبی که در فایل نبود، مقدار قبلی‌اش را نگه داشت' );
mc_ok( 'پنل تازه' === $GLOBALS['mc_options']['manacore_settings']['mega_title'], 'تب دیگر همان فایل هم اعمال شد' );
mc_ok( 55 === $GLOBALS['mc_options']['manacore_watch_page'], 'برگه‌ی پخش از نامک به شناسه‌ی همین سایت وصل شد' );
mc_ok( array( 'general' => 1, 'mega' => 1 ) === $result['tabs'], 'گزارش تب‌به‌تب درست است', wp_json_encode( $result['tabs'] ) );

/* کرانه‌گذاری همان تب: مقدار بیرون بازه باید بریده شود، نه ذخیره‌ی خام. */
Portability::import( array( 'settings' => array( 'items_per_page' => 9999 ) ) );
mc_ok( 100 === $GLOBALS['mc_options']['manacore_settings']['items_per_page'], 'کرانه‌گذاری sanitize روی مقدار واردشده هم اجرا می‌شود' );

/* حالت آزمایشی: نه تنظیمات و نه برگه‌ها تغییر نمی‌کنند. */
$before = $GLOBALS['mc_options']['manacore_settings'];
$dry    = Portability::import( array( 'settings' => array( 'items_per_page' => 7 ) ), array( 'dry_run' => true ) );

mc_ok( $dry['dry_run'], 'نتیجه حالت آزمایشی را گزارش می‌کند' );
mc_ok( 1 === $dry['applied'], 'شمارش در حالت آزمایشی هم انجام می‌شود' );
mc_ok( $before === $GLOBALS['mc_options']['manacore_settings'], 'حالت آزمایشی هیچ چیزی ذخیره نمی‌کند' );

/* فایل دست‌ساز بدون قالب: فقط نقشه‌ی کلید/مقدار. */
$bare = Portability::import( '{"items_per_page":22}' );
mc_ok( $bare['ok'] && 1 === $bare['applied'], 'فایل دست‌ساز بدون قالب هم پذیرفته می‌شود' );
mc_ok( 22 === $GLOBALS['mc_options']['manacore_settings']['items_per_page'], 'مقدار فایل دست‌ساز ذخیره شد' );

list( $code, $reason ) = Portability::notice_for( array( 'errors' => 'x', 'applied' => 0, 'pages' => array() ) );
mc_ok( 'import-error' === $code, 'نتیجه‌ی خطادار کد پیام خطا می‌دهد' );

list( $code2 ) = Portability::notice_for( array( 'errors' => array(), 'applied' => 3, 'pages' => array(), 'dry_run' => true ) );
mc_ok( 'import-dry' === $code2, 'حالت آزمایشی کد پیام جدا دارد' );

$summary = Portability::summary();
mc_ok( isset( $summary['keys'], $summary['ads'] ), 'خلاصه‌ی کارت پنل کلیدهای لازم را دارد' );

/* ---------------------------------------------------------------
 * ۵) محتوای نمایشی
 * ------------------------------------------------------------ */

echo "\n=== ۵) محتوای نمایشی ===\n";

$blueprint = Demo::blueprint();
$episodes  = 0;

foreach ( $blueprint['series'] as $series ) {
	$episodes += Demo::episode_count( $series );
}

mc_ok( 3 === count( $blueprint['movies'] ), 'سه فیلم نمونه در نقشه هست' );
mc_ok( 2 === count( $blueprint['series'] ), 'دو سریال نمونه در نقشه هست' );
mc_ok( 9 === $episodes, 'قسمت‌های سریال از جمع فصل‌ها می‌آید', (string) $episodes );

$links = Demo::links( 'demo' );
mc_ok( 3 === count( $links ), 'سه کیفیت برای هر اثر ساخته می‌شود' );
mc_ok( 2 === count( $links[0]['items'] ), 'هر کیفیت دو کنش (پخش و دانلود) دارد' );
mc_ok( false !== strpos( $links[0]['items'][0]['url'], 'gtv-videos-bucket' ), 'رسانه از نمونه‌ی آزاد می‌آید' );

$GLOBALS['mc_pages']       = array( 'watch' => 55, 'the-godfather' => 11, 'shogun' => 12 );
$GLOBALS['mc_meta']        = array();
$GLOBALS['mc_deleted']     = array();
$GLOBALS['mc_terms']       = 0;
$GLOBALS['mc_next_id']     = 100;

$seed = Demo::seed();

mc_ok( 20 === $seed['created'], 'شمارش نوشته‌های ساخته‌شده درست است', (string) $seed['created'] );
mc_ok( count( $seed['ids']['movie'] ) === 3 && count( $seed['ids']['series'] ) === 2, 'شناسه‌ها به تفکیک نوع برمی‌گردند' );
mc_ok( count( $seed['ids']['episode'] ) === 9, 'همه‌ی قسمت‌ها ساخته شدند' );
mc_ok( $seed['terms'] > 0, 'ترم‌های نمونه ساخته شدند', (string) $seed['terms'] );

$movie_id = $seed['ids']['movie'][0];
mc_ok( 1 === get_post_meta( $movie_id, Demo::META, true ), 'نشانه‌ی نمایشی روی نوشته هست' );
mc_ok( ! empty( $GLOBALS['mc_meta'][ $movie_id ]['manacore_links'] ), 'لینک‌ها با ساختار خود افزونه ذخیره شدند' );

$stored = json_decode( (string) $GLOBALS['mc_meta'][ $movie_id ]['manacore_links'], true );
mc_ok( is_array( $stored ) && 3 === count( $stored ), 'JSON لینک‌ها خوانا است' );
mc_ok( ! empty( $GLOBALS['mc_meta'][ $movie_id ]['manacore_original_title'] ), 'نام اصلی اثر ثبت شد' );
mc_ok( ! empty( $GLOBALS['mc_meta'][ $seed['ids']['series'][0] ]['manacore_seasons'] ), 'فصل‌های سریال ثبت شدند' );
mc_ok( ! empty( $GLOBALS['mc_meta'][ $seed['ids']['person'][0] ]['manacore_person_role'] ), 'عوامل با نقش ثبت شدند' );
mc_ok( 3 === count( $GLOBALS['mc_comments'] ), 'دیدگاه‌های نمونه ساخته شدند', (string) count( $GLOBALS['mc_comments'] ) );

$status = Demo::status();
mc_ok( $status['total'] === 20, 'وضعیت، همه‌ی نوشته‌های نمایشی را می‌شمارد', (string) $status['total'] );
mc_ok( 9 === $status['counts']['episode'], 'شمارش قسمت‌ها در وضعیت درست است' );

$again = Demo::seed();
mc_ok( $again['skipped'] && 0 === $again['created'], 'اجرای دوباره بی‌اثر است' );

$forced = Demo::seed( array( 'force' => true ) );
mc_ok( 20 === $forced['created'], 'حالت force محتوای قبلی را عوض می‌کند' );

$deleted = Demo::remove();
mc_ok( $deleted > 0, 'حذف، نوشته‌های نمایشی را برمی‌دارد', (string) $deleted );
mc_ok( empty( Demo::status()['total'] ), 'پس از حذف، هیچ نوشته‌ی نمایشی نمی‌ماند' );
mc_ok( false === get_option( Demo::OPTION ), 'نشانه‌ی ساخت هم پاک می‌شود' );

/* ---------------------------------------------------------------
 * ۶) فرمان‌های WP-CLI
 * ------------------------------------------------------------ */

echo "\n=== ۶) فرمان‌های WP-CLI ===\n";

$cli = new Cli();

WP_CLI::$lines = array();
$cli->status( array(), array() );
$out = implode( "\n", WP_CLI::$lines );

mc_ok( false !== strpos( $out, 'نسخه‌ی پایگاه‌داده' ), 'دستور status نسخه‌ی پایگاه‌داده را می‌گوید' );
mc_ok( false !== strpos( $out, 'movie' ), 'دستور status شمارش نوع‌های محتوا را می‌دهد' );
mc_ok( false !== strpos( $out, 'پخش' ), 'دستور status وضعیت برگه‌ی پخش را می‌گوید' );

$file = sys_get_temp_dir() . '/manacore-cli-' . getmypid() . '.json';
@unlink( $file );

WP_CLI::$lines = array();
$cli->export( array(), array( 'file' => $file ) );

mc_ok( is_readable( $file ), 'دستور export فایل را می‌سازد' );
mc_ok( false !== strpos( implode( "\n", WP_CLI::$lines ), 'SUCCESS' ), 'دستور export موفقیت را گزارش می‌کند' );

$decoded = json_decode( (string) file_get_contents( $file ), true );
mc_ok( 'manacore-settings' === ( $decoded['format'] ?? '' ), 'فایل خروجی CLI همان قالب پنل است' );

$GLOBALS['mc_options']['manacore_settings']['items_per_page'] = 12;

WP_CLI::$lines = array();
$cli->import( array( $file ), array() );
mc_ok( false !== strpos( implode( "\n", WP_CLI::$lines ), 'SUCCESS' ), 'دستور import نتیجه را گزارش می‌کند' );

WP_CLI::$lines = array();
try {
	$cli->import( array( '/tmp/does-not-exist-manacore.json' ), array() );
} catch ( RuntimeException $e ) {
	WP_CLI::$lines[] = 'ERROR';
}
mc_ok( in_array( 'ERROR', WP_CLI::$lines, true ), 'فایل ناموجود خطا می‌دهد (بی‌صدا رد نمی‌شود)' );

$GLOBALS['mc_meta']    = array();
$GLOBALS['mc_next_id'] = 100;

WP_CLI::$lines = array();
$cli->demo( array( 'create' ), array() );
mc_ok( false !== strpos( implode( "\n", WP_CLI::$lines ), 'SUCCESS' ), 'دستور demo create محتوا می‌سازد' );

WP_CLI::$lines = array();
$cli->demo( array( 'remove' ), array() );
mc_ok( false !== strpos( implode( "\n", WP_CLI::$lines ), 'SUCCESS' ), 'دستور demo remove محتوا را برمی‌دارد' );

try {
	$cli->demo( array( 'bogus' ), array() );
	$bad_action = false;
} catch ( RuntimeException $e ) {
	$bad_action = true;
}
mc_ok( $bad_action, 'کنش نامعتبر demo خطا می‌دهد' );

WP_CLI::$lines = array();
$cli->rebuild();
mc_ok( ! empty( $GLOBALS['mc_rewritten'] ), 'دستور rebuild قواعد پیوند را بازمی‌سازد' );

$GLOBALS['wpdb']->vars = array( 5, 2 );
WP_CLI::$lines         = array();
$cli->cleanup( array(), array() );

$out = implode( "\n", WP_CLI::$lines );
mc_ok( false !== strpos( $out, 'ترنزینت‌های منقضی: 5' ), 'دستور cleanup شمارش ترنزینت‌ها را می‌گوید', $out );
mc_ok( false !== strpos( $out, 'WARNING' ), 'بدون --yes فقط هشدار می‌دهد' );
mc_ok( empty( $GLOBALS['mc_expired_deleted'] ), 'بدون --yes چیزی حذف نمی‌شود' );

$GLOBALS['wpdb']->vars = array( 1, 1 );
$GLOBALS['wpdb']->queries = array();
WP_CLI::$lines         = array();
$cli->cleanup( array(), array( 'yes' => true ) );

mc_ok( ! empty( $GLOBALS['mc_expired_deleted'] ), 'با --yes ترنزینت‌های منقضی حذف می‌شوند' );
mc_ok( (bool) preg_grep( '/DELETE s FROM wp_manacore_stats/', $GLOBALS['wpdb']->queries ), 'ردیف‌های آمار بی‌صاحب با JOIN امن حذف می‌شوند', implode( ' | ', $GLOBALS['wpdb']->queries ) );

WP_CLI::$lines = array();
Cli::register();
mc_ok( ! in_array( 'COMMAND manacore', WP_CLI::$lines, true ), 'بی WP_CLI هیچ فرمانی ثبت نمی‌شود' );

/* ---------------------------------------------------------------
 * پایان
 * ------------------------------------------------------------ */

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo 'موفق: ' . $GLOBALS['mc_pass'] . '   ناموفق: ' . $GLOBALS['mc_fail'] . "\n";
echo str_repeat( '=', 58 ) . "\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
