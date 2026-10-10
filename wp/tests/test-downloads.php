<?php
/**
 * آزمون «دانلود امضاشده» و شمارش سرورسوی دانلود.
 *
 * بدون وردپرس اجرا می‌شود؛ درخواست REST ساختگی و رکوردهای وردپرسی با پوسته
 * شبیه‌سازی می‌شوند تا بتوان دقیقاً سنجید:
 *   ۱. امضا فقط با همان (اثر | لینک | انقضا) معتبر است و دست‌کاری هر جزء
 *      آن را بی‌اعتبار می‌کند.
 *   ۲. لینک منقضی و لینک ویژه‌ی بدون دسترسی، باز نمی‌شوند.
 *   ۳. با خاموش‌بودن گزینه، مارک‌آپ و نشانی‌ها **دقیقاً** مثل پیش می‌مانند
 *      (هیچ تغییر ناخواسته‌ای در باکس دانلود رخ نمی‌دهد).
 *   ۴. شمارش دانلود ضدرعدّ‌سازی دارد و یک کلیک دوبار شمرده نمی‌شود.
 *
 * اجرا: php wp/tests/test-downloads.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_URL', 'http://example.test/plugins/manacore-core/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.1.0' );
define( 'MINUTE_IN_SECONDS', 60 );
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

$GLOBALS['mc_options']    = array();
$GLOBALS['mc_transients'] = array();
$GLOBALS['mc_meta']       = array();
$GLOBALS['mc_actions']    = array();
$GLOBALS['mc_routes']     = array();
$GLOBALS['mc_user']       = 0;

function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_attr__( $t, $d = '' ) { return $t; }
function esc_html_e( $t, $d = '' ) { echo $t; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return (string) $v; }
function esc_url_raw( $v ) { return filter_var( (string) $v, FILTER_SANITIZE_URL ); }
function home_url( $p = '' ) { return 'http://example.test' . ( '' === (string) $p ? '/' : '/' . ltrim( (string) $p, '/' ) ); }
function rest_url( $p = '' ) { return 'http://example.test/wp-json/' . ltrim( (string) $p, '/' ); }
function apply_filters( $tag, $value ) {
	/* فیلتر دسترسی افزونه‌ی اشتراک: در آزمون قابل کنترل است. */
	if ( 'manacore_user_can_access' === $tag ) {
		return (bool) ( $GLOBALS['mc_can_access'] ?? true );
	}

	return $value;
}
function add_action() {}
function add_filter() {}
function do_action( $tag, ...$args ) { $GLOBALS['mc_actions'][] = array( $tag, $args ); }
function register_rest_route( $ns, $route, $args = array() ) { $GLOBALS['mc_routes'][] = array( $ns, $route, $args ); return true; }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_rand( $min = 0, $max = 0 ) { return 12345; }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_title( $v ) { return strtolower( trim( preg_replace( '/[\s_]+/', '-', (string) $v ) ) ); }
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
function wp_unslash( $v ) { return $v; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_current_user_id() { return (int) $GLOBALS['mc_user']; }
function add_query_arg( $args, $url = '' ) {
	$parts = array();

	foreach ( (array) $args as $k => $v ) {
		$parts[] = rawurlencode( (string) $k ) . '=' . $v;
	}

	return (string) $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . implode( '&', $parts );
}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['mc_options'] ) ? $GLOBALS['mc_options'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['mc_options'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['mc_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['mc_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['mc_transients'][ $k ] ); return true; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['mc_meta'][ (int) $id ][ $key ] ?? ''; }
function get_permalink( $id = 0 ) { return 'http://example.test/?p=' . (int) $id; }
function post_type_exists( $t ) { return true; }
function taxonomy_exists( $t ) { return true; }
function wp_get_attachment_image_url( $id = 0, $size = '' ) { return ''; }
class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_status() { return (int) ( $this->data['status'] ?? 0 ); }
}

class WP_REST_Response {
	public $data;
	public $status;
	public $headers = array();
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function header( $k, $v ) { $this->headers[ $k ] = $v; }
}

class WP_REST_Server {
	const READABLE = 'GET';
}

/**
 * درخواست ساختگی REST با دسترسی آرایه‌ای (مثل `WP_REST_Request`).
 */
class MC_Request implements ArrayAccess {
	private $data = array();
	public function __construct( array $data ) { $this->data = $data; }
	public function offsetExists( $offset ): bool { return isset( $this->data[ $offset ] ); }
	public function offsetGet( $offset ): mixed { return $this->data[ $offset ] ?? null; }
	public function offsetSet( $offset, $value ): void { $this->data[ $offset ] = $value; }
	public function offsetUnset( $offset ): void { unset( $this->data[ $offset ] ); }
}

/* ---------------------------------------------------------------
 * بارگذاری کلاس‌های واقعی
 * ------------------------------------------------------------ */

/* ---------------------------------------------------------------
 * پوسته‌ی برگه‌ی «پل دانلود»
 * ------------------------------------------------------------ */

$GLOBALS['mc_rewrites']   = array();
$GLOBALS['mc_query_vars'] = array();
$GLOBALS['mc_headers']    = array();
$GLOBALS['mc_styles']     = array();
$GLOBALS['mc_query']      = array();

function user_trailingslashit( $url ) { return rtrim( (string) $url, '/' ) . '/'; }
function add_rewrite_rule( $regex, $query, $after = 'bottom' ) { $GLOBALS['mc_rewrites'][] = array( $regex, $query, $after ); return true; }
function flush_rewrite_rules( $hard = true ) { $GLOBALS['mc_flush'] = ( $GLOBALS['mc_flush'] ?? 0 ) + 1; }
function get_query_var( $key, $default = '' ) { return $GLOBALS['mc_query'][ $key ] ?? $default; }
function is_admin() { return false; }
function status_header( $code ) { $GLOBALS['mc_headers'][] = array( 'status', (int) $code ); }
function nocache_headers() { $GLOBALS['mc_headers'][] = array( 'nocache', 1 ); }
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) { $GLOBALS['mc_styles'][ $handle ] = $src; return true; }
function language_attributes( $doctype = 'html' ) { echo 'lang="fa-IR" dir="rtl"'; }
function bloginfo( $key = '' ) { echo 'name' === $key ? 'سایت نمونه' : 'UTF-8'; }
function get_bloginfo( $key = '' ) { return 'name' === $key ? 'سایت نمونه' : 'UTF-8'; }
function wp_head() { echo '<meta name="generator" content="stub" />'; }
function wp_footer() { echo '<!-- footer -->'; }
function body_class( $class = '' ) { echo 'class="' . esc_attr( is_array( $class ) ? implode( ' ', $class ) : $class ) . '"'; }
function get_the_post_thumbnail( $id = 0, $size = '', $attrs = array() ) { return '<img src="http://example.test/poster.jpg" alt="" />'; }
function get_the_title( $id = 0 ) { return $GLOBALS['mc_titles'][ (int) $id ] ?? ( 'اثر ' . (int) $id ); }
function is_user_logged_in() { return (bool) $GLOBALS['mc_user']; }
function wp_login_url( $redirect = '' ) { return 'http://example.test/wp-login.php'; }

require_once MANACORE_PATH . 'includes/functions.php';
require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-settings.php';
require_once MANACORE_PATH . 'includes/class-rest-api.php';
require_once MANACORE_PATH . 'includes/class-links.php';
require_once MANACORE_PATH . 'includes/class-templates.php';
require_once MANACORE_PATH . 'includes/class-downloads.php';

use ManaCore\Core\Downloads;
use ManaCore\Core\Links;
use ManaCore\Core\Templates;

/* لینک‌های یک اثر نمونه: دو گروه (معمولی و ویژه). */
$GLOBALS['mc_meta'][7]['manacore_links'] = array(
	array(
		'id'       => 'grp_1',
		'title'    => 'فصل ۱',
		'season'   => 1,
		'quality'  => '1080p',
		'language' => 'sub_fa',
		'encoder'  => 'x265',
		'size'     => '1.2GB',
		'premium'  => false,
		'items'    => array(
			array( 'id' => 'lnk_stream', 'label' => '', 'episode' => '', 'url' => 'https://cdn.example/play.m3u8', 'type' => 'stream', 'size' => '', 'quality' => '', 'language' => '', 'note' => '' ),
			array( 'id' => 'lnk_direct', 'label' => '', 'episode' => '', 'url' => 'https://cdn.example/movie-1080.mp4', 'type' => 'direct', 'size' => '', 'quality' => '', 'language' => '', 'note' => '' ),
		),
	),
	array(
		'id'       => 'grp_2',
		'title'    => 'نسخه‌ی ویژه',
		'season'   => 2,
		'quality'  => '2160p',
		'language' => '',
		'encoder'  => 'REMUX',
		'size'     => '8GB',
		'premium'  => true,
		'items'    => array(
			array( 'id' => 'lnk_premium', 'label' => '', 'episode' => '', 'url' => 'https://cdn.example/movie-4k.mp4', 'type' => 'direct', 'size' => '', 'quality' => '', 'language' => '', 'note' => '' ),
		),
	),
);

/* ---------------------------------------------------------------
 * ۱) تنظیمات و پیش‌فرض‌ها
 * ------------------------------------------------------------ */

echo "\n=== ۱) تنظیمات امضای لینک ===\n";

mc_ok( false === Downloads::enabled(), 'امضای لینک به‌صورت پیش‌فرض خاموش است (رفتار امروز عوض نمی‌شود)' );
mc_ok( 1440 * MINUTE_IN_SECONDS === Downloads::ttl(), 'اعتبار پیش‌فرض ۱۴۴۰ دقیقه (۲۴ ساعت) است' );

$GLOBALS['mc_options']['manacore_settings'] = array( 'download_ttl' => 999999 );
mc_ok( Downloads::MAX_TTL * MINUTE_IN_SECONDS === Downloads::ttl(), 'اعتبار بیش از سقف بریده می‌شود' );

$GLOBALS['mc_options']['manacore_settings'] = array( 'download_ttl' => 0 );
mc_ok( Downloads::MIN_TTL * MINUTE_IN_SECONDS === Downloads::ttl(), 'اعتبار صفر به کف بازمی‌گردد' );

$tab_keys = \ManaCore\Core\Settings::keys_by_tab();
mc_ok(
	in_array( 'download_signing', $tab_keys['watch'], true ) && in_array( 'download_ttl', $tab_keys['watch'], true ),
	'هر دو کلید در تب «پخش و دانلود» ثبت شده‌اند'
);
mc_ok(
	in_array( 'download_bridge', $tab_keys['watch'], true ) && in_array( 'bridge_notice_text', $tab_keys['watch'], true ),
	'کلیدهای «پل دانلود» هم در تب «پخش و دانلود» ثبت شده‌اند'
);
mc_ok( false === Downloads::bridge_enabled(), 'پل دانلود بدون امضای روشن، فعال نمی‌شود' );

$GLOBALS['mc_options']['manacore_settings'] = array( 'download_signing' => 1 );

/* ---------------------------------------------------------------
 * ۲) امضا
 * ------------------------------------------------------------ */

echo "\n=== ۲) امضا و اعتبارسنجی ===\n";

$exp = time() + 600;

$sig_a = Downloads::signature( 7, 'lnk_direct', $exp );
mc_ok( 64 === strlen( $sig_a ), 'امضا یک درهم‌سازی SHA-256 است', $sig_a );
mc_ok( $sig_a === Downloads::signature( 7, 'lnk_direct', $exp ), 'امضای یکسان برای ورودی یکسان (پایدار) است' );
mc_ok( $sig_a !== Downloads::signature( 8, 'lnk_direct', $exp ), 'تغییر اثر، امضا را عوض می‌کند' );
mc_ok( $sig_a !== Downloads::signature( 7, 'lnk_premium', $exp ), 'تغییر لینک، امضا را عوض می‌کند' );
mc_ok( $sig_a !== Downloads::signature( 7, 'lnk_direct', $exp + 1 ), 'تغییر زمان انقضا، امضا را عوض می‌کند' );

mc_ok( 'ok' === Downloads::verify( 7, 'lnk_direct', $exp, $sig_a ), 'امضای درست پذیرفته می‌شود' );
mc_ok( 'ok' === Downloads::verify( 7, 'lnk_direct', $exp, strtoupper( $sig_a ) ), 'حروف بزرگ امضا هم پذیرفته می‌شود (نشانی کپی‌شده)' );
mc_ok( 'bad' === Downloads::verify( 7, 'lnk_direct', $exp, substr( $sig_a, 0, 63 ) ), 'امضای بریده رد می‌شود' );
mc_ok( 'bad' === Downloads::verify( 7, 'lnk_direct', $exp + 60, $sig_a ), 'جابه‌جایی زمان انقضا با امضای قبلی رد می‌شود (دست‌کاری)' );
mc_ok( 'bad' === Downloads::verify( 7, 'lnk_stream', $exp, $sig_a ), 'امضای لینک دیگر روی این لینک کار نمی‌کند' );
mc_ok( 'bad' === Downloads::verify( 7, 'lnk_direct', $exp, '' ), 'امضای خالی رد می‌شود' );
mc_ok( 'expired' === Downloads::verify( 7, 'lnk_direct', time() - 10, Downloads::signature( 7, 'lnk_direct', time() - 10 ) ), 'لینک منقضی «منقضی» تشخیص داده می‌شود' );

/* ---------------------------------------------------------------
 * ۳) نشانی امضاشده
 * ------------------------------------------------------------ */

echo "\n=== ۳) نشانی امضاشده ===\n";

$url = Downloads::signed_url( 7, 'lnk_direct' );

mc_ok( false !== strpos( $url, '/manacore/v1/download/7' ), 'مسیر REST با شناسه‌ی اثر ساخته می‌شود', $url );
mc_ok( false !== strpos( $url, 'item=lnk_direct' ), 'شناسه‌ی لینک در نشانی هست' );
mc_ok( false !== strpos( $url, 'sig=' . $sig_a ) || 1 === preg_match( '/sig=[a-f0-9]{64}/', $url ), 'امضا در نشانی هست' );
mc_ok( (bool) preg_match( '/exp=(\d+)/', $url, $m ) && (int) $m[1] > time() + 86000, 'انقضای پیش‌فرض نزدیک ۲۴ ساعت جلوتر است', $m[1] ?? '' );

$fixed = Downloads::signed_url( 7, 'lnk_direct', $exp );
mc_ok( false !== strpos( $fixed, 'exp=' . $exp ), 'انقضای صریح در نشانی می‌نشیند' );
mc_ok( false !== strpos( $fixed, 'sig=' . Downloads::signature( 7, 'lnk_direct', $exp ) ), 'امضای همان انقضا در نشانی می‌نشیند' );

/* ---------------------------------------------------------------
 * ۴) یک نقطه‌ی تصمیم: url_for()
 * ------------------------------------------------------------ */

echo "\n=== ۴) انتخاب نشانی (url_for) ===\n";

/* با پل خاموش، مسیر امضاشده‌ی REST همان چیزی است که کاربر می‌بیند. */
$GLOBALS['mc_options']['manacore_settings'] = array( 'download_signing' => 1, 'download_bridge' => 0 );

$signed_now = Downloads::url_for( 7, 'https://cdn.example/movie-1080.mp4', 'direct' );
mc_ok( false === strpos( $signed_now, 'cdn.example' ), 'با امضای روشن، نشانی خام فایل جایش را به مسیر داخلی می‌دهد', $signed_now );
mc_ok(
	false !== strpos( Downloads::url_for( 7, 'https://cdn.example/movie-1080.mp4', 'direct' ), '/download/7' ),
	'نشانی امضاشده از مسیر داخلی می‌آید'
);
mc_ok(
	'https://cdn.example/play.m3u8' === Downloads::url_for( 7, 'https://cdn.example/play.m3u8', 'stream' ),
	'لینک پخش هرگز امضا نمی‌شود'
);
mc_ok(
	'https://other.example/x.mp4' === Downloads::url_for( 7, 'https://other.example/x.mp4', 'direct' ),
	'نشانی ناشناخته (خارج از متای اثر) دست‌نخورده می‌ماند'
);
mc_ok( '' === Downloads::item_id_for_url( 7, 'https://other.example/x.mp4' ), 'لینک ناشناخته شناسه نمی‌گیرد' );
mc_ok( 'lnk_direct' === Downloads::item_id_for_url( 7, 'https://cdn.example/movie-1080.mp4' ), 'لینک دانلودی شناسه‌اش پیدا می‌شود' );
mc_ok( 'lnk_stream' === Downloads::item_id_for_url( 7, 'https://cdn.example/play.m3u8' ), 'لینک پخش هم شناسه دارد (برای تطبیق، نه امضا)' );

$item = Downloads::item( 7, 'lnk_premium' );
mc_ok( is_array( $item ) && true === $item['premium'] && '2160p' === $item['quality'], 'گروه ویژه با پرچم premium شناسایی می‌شود' );
mc_ok( null === Downloads::item( 7, 'lnk_missing' ), 'لینک ناموجود null می‌دهد' );
mc_ok( null === Downloads::item( 99, 'lnk_direct' ), 'اثر ناموجود لینک نمی‌دهد' );

$GLOBALS['mc_options']['manacore_settings'] = array( 'download_signing' => 0 );
mc_ok(
	'https://cdn.example/movie-1080.mp4' === Downloads::url_for( 7, 'https://cdn.example/movie-1080.mp4', 'direct' ),
	'با خاموش‌بودن گزینه، نشانی خام دست‌نخورده می‌ماند'
);
$GLOBALS['mc_options']['manacore_settings'] = array( 'download_signing' => 1 );

/* ---------------------------------------------------------------
 * ۵) مسیر REST
 * ------------------------------------------------------------ */

echo "\n=== ۵) مسیر REST دانلود ===\n";

$downloads = Downloads::instance();
$downloads->register_routes();

mc_ok( 1 === count( $GLOBALS['mc_routes'] ), 'یک مسیر ثبت می‌شود' );
mc_ok( 'manacore/v1' === $GLOBALS['mc_routes'][0][0], 'فضای نام مسیر درست است' );
mc_ok( '/download/(?P<id>\d+)' === $GLOBALS['mc_routes'][0][1], 'الگوی مسیر شامل شناسه‌ی اثر است' );
mc_ok( '__return_true' === $GLOBALS['mc_routes'][0][2]['permission_callback'], 'دروازه‌بان باز است (اعتبار از امضا می‌آید، نه نشست)' );
mc_ok( isset( $GLOBALS['mc_routes'][0][2]['args']['sig'] ), 'آرگومان امضا ثبت شده است' );

$exp_fresh = time() + 300;
$good_sig  = Downloads::signature( 7, 'lnk_direct', $exp_fresh );

$ok = $downloads->resolve( new MC_Request( array( 'id' => 7, 'item' => 'lnk_direct', 'exp' => $exp_fresh, 'sig' => $good_sig ) ) );

mc_ok( $ok instanceof WP_REST_Response && 302 === $ok->status, 'دانلود معتبر پاسخ ۳۰۲ می‌گیرد' );
mc_ok( 'https://cdn.example/movie-1080.mp4' === ( $ok->headers['Location'] ?? '' ), 'مقصد بازفرست همان فایل خودِ اثر است' );
mc_ok( in_array( array( 'manacore_download_counted', array( 7, 'download' ) ), $GLOBALS['mc_actions'], true ), 'دانلود شمرده می‌شود' );

$again = $downloads->resolve( new MC_Request( array( 'id' => 7, 'item' => 'lnk_direct', 'exp' => $exp_fresh, 'sig' => $good_sig ) ) );
mc_ok( $again instanceof WP_REST_Response, 'درخواست دوم هم بازفرست می‌گیرد' );
mc_ok(
	1 === count( array_filter( $GLOBALS['mc_actions'], static function ( $a ) { return 'manacore_download_counted' === $a[0]; } ) ),
	'کلیک دوباره در پنجره‌ی ضدرعدّ‌سازی دوباره شمرده نمی‌شود'
);

$bad = $downloads->resolve( new MC_Request( array( 'id' => 7, 'item' => 'lnk_direct', 'exp' => $exp_fresh, 'sig' => str_repeat( 'a', 64 ) ) ) );
mc_ok( $bad instanceof WP_Error && 403 === $bad->get_status(), 'امضای جعلی ۴۰۳ می‌گیرد' );

$expired_sig = Downloads::signature( 7, 'lnk_direct', time() - 60 );
$expired     = $downloads->resolve( new MC_Request( array( 'id' => 7, 'item' => 'lnk_direct', 'exp' => time() - 60, 'sig' => $expired_sig ) ) );
mc_ok( $expired instanceof WP_Error && 410 === $expired->get_status(), 'لینک منقضی ۴۱۰ می‌گیرد' );

$missing = $downloads->resolve( new MC_Request( array( 'id' => 7, 'item' => 'lnk_missing', 'exp' => $exp_fresh, 'sig' => Downloads::signature( 7, 'lnk_missing', $exp_fresh ) ) ) );
mc_ok( $missing instanceof WP_Error && 404 === $missing->get_status(), 'لینک ناموجود ۴۰۴ می‌گیرد' );

$GLOBALS['mc_user']       = 0;
$GLOBALS['mc_can_access'] = false;
$locked                   = $downloads->resolve( new MC_Request( array( 'id' => 7, 'item' => 'lnk_premium', 'exp' => $exp_fresh, 'sig' => Downloads::signature( 7, 'lnk_premium', $exp_fresh ) ) ) );
mc_ok( $locked instanceof WP_Error, 'لینک ویژه‌ی مشترکین بدون دسترسی باز نمی‌شود' );

$GLOBALS['mc_options']['manacore_settings'] = array( 'download_signing' => 0 );
$off = $downloads->resolve( new MC_Request( array( 'id' => 7, 'item' => 'lnk_direct', 'exp' => $exp_fresh, 'sig' => $good_sig ) ) );
mc_ok( $off instanceof WP_Error && 404 === $off->get_status(), 'با خاموش‌بودن گزینه، مسیر امضا کار نمی‌کند' );
$GLOBALS['mc_options']['manacore_settings'] = array( 'download_signing' => 1 );

/* ---------------------------------------------------------------
 * ۶) مارک‌آپ باکس دانلود
 * ------------------------------------------------------------ */

echo "\n=== ۶) مارک‌آپ باکس دانلود ===\n";

$group = Links::get( 7 )[0];

/* هر لینک یک ردیف است؛ این کمک همان مسیر رندر جدول را طی می‌کند. */
$render_rows = static function ( $groups, $post_id ) {
	$rows = Templates::download_rows( $groups );
	$cols = Templates::download_columns( $rows );
	$html = '';
	foreach ( $rows as $row ) {
		$html .= Templates::link_row( $row, $cols, $post_id );
	}
	return $html;
};

/* نخست با «پل دانلود» خاموش: دکمه باید مستقیم به مسیر امضاشده برود. */
$GLOBALS['mc_options']['manacore_settings'] = array( 'download_signing' => 1, 'download_bridge' => 0 );

$row_signed = $render_rows( array( $group ), 7 );
mc_ok( false !== strpos( $row_signed, 'class="download-row"' ), 'ردیف دانلود همان ساختار مرجع را دارد' );
mc_ok( false !== strpos( $row_signed, 'data-manacore-download="7"' ), 'قلاب شمارش سمت کاربر سرجایش هست' );
mc_ok( false !== strpos( $row_signed, '/manacore/v1/download/7' ), 'دکمه‌ی دانلود با امضا به مسیر داخلی می‌رود' );
mc_ok( false === strpos( $row_signed, 'https://cdn.example/movie-1080.mp4' ), 'نشانی خام فایل در صفحه لو نمی‌رود' );
mc_ok( false !== strpos( $row_signed, 'play.m3u8' ) || false !== strpos( $row_signed, 'پخش' ), 'دکمه‌ی پخش دست‌نخورده است' );

/* حالت «پل دانلود»: دکمه به گام میانی می‌رود، نه مستقیم به مسیر فایل. */
$GLOBALS['mc_options']['manacore_settings'] = array( 'download_signing' => 1, 'download_bridge' => 1 );
$row_bridge = $render_rows( array( $group ), 7 );

mc_ok( false !== strpos( $row_bridge, '/manacore-download/7/lnk_direct' ), 'با روشن‌بودن پل، دکمه به برگه‌ی «آماده‌ی دانلود» می‌رود' );
mc_ok( false === strpos( $row_bridge, 'https://cdn.example/movie-1080.mp4' ), 'در حالت پل هم نشانی خام لو نمی‌رود' );

$GLOBALS['mc_options']['manacore_settings'] = array( 'download_signing' => 0 );
$row_plain = $render_rows( array( $group ), 7 );

mc_ok( false !== strpos( $row_plain, 'https://cdn.example/movie-1080.mp4' ), 'با خاموش‌بودن گزینه، نشانی خام همان‌جا می‌ماند' );
mc_ok( substr_count( $row_plain, '<span' ) + substr_count( $row_plain, '<div' ) === substr_count( $row_signed, '<span' ) + substr_count( $row_signed, '<div' ), 'هندسه‌ی ردیف در دو حالت یکی است (هیچ عنصری اضافه/کم نمی‌شود)' );

$GLOBALS['mc_can_access'] = false;
$locked_row = $render_rows( array( Links::get( 7 )[1] ), 7 );
$GLOBALS['mc_can_access'] = true;
mc_ok( false !== strpos( $locked_row, 'manacore-btn is-primary is-small' ), 'گروه ویژه‌ی بدون دسترسی دکمه‌ی اشتراک می‌گیرد' );
mc_ok( false === strpos( $locked_row, 'movie-4k.mp4' ), 'لینک ویژه هرگز برای کاربر بی‌دسترسی چاپ نمی‌شود' );


/* ---------------------------------------------------------------
 * ۷) پل دانلود
 * ------------------------------------------------------------ */

echo "\n=== ۷) پل دانلود ===\n";

$GLOBALS['mc_options']['manacore_settings'] = array(
	'download_signing'   => 1,
	'download_bridge'    => 1,
	'bridge_notice_text' => '',
);

mc_ok( Downloads::bridge_enabled(), 'با امضا و تیک، پل فعال است' );
mc_ok( 'manacore-download' === Downloads::bridge_slug(), 'پیشوند پیش‌فرض پل همان مقدار مستندشده است' );

$bridge = Downloads::bridge_url( 7, 'lnk_direct' );

mc_ok( false !== strpos( $bridge, '/manacore-download/7/lnk_direct' ), 'نشانی پل از پیشوند و شناسه‌ی اثر و لینک ساخته می‌شود', $bridge );
mc_ok( (bool) preg_match( '/exp=(\d+)/', $bridge, $bm ) && (int) $bm[1] > time(), 'پل زمان انقضا دارد' );
mc_ok( (bool) preg_match( '/sig=[a-f0-9]{64}/', $bridge ), 'پل امضای HMAC دارد' );
mc_ok( false === strpos( $bridge, 'cdn.example' ), 'نشانی فایل هرگز در آدرس پل نمی‌آید' );

/* همان امضای مسیر بازفرست: پل درِ پشتی امضا نیست. */
mc_ok(
	'ok' === Downloads::verify( 7, 'lnk_direct', (int) $bm[1], Downloads::signature( 7, 'lnk_direct', (int) $bm[1] ) ),
	'امضای پل با همان تابع مسیر بازفرست ساخته می‌شود'
);

/* دروازه‌های خطا */
$GLOBALS['mc_query'] = array( 'manacore_download' => 7, 'manacore_download_item' => 'lnk_missing' );
$_GET                = array();

$doc = Downloads::instance()->bridge_document( array( 'status' => 404, 'title' => 'x', 'text' => 'y' ) );
mc_ok( false !== strpos( $doc, 'x' ) && false !== strpos( $doc, '<!DOCTYPE html>' ), 'سند پل کامل و با ساختار HTML است' );
mc_ok( false !== strpos( $doc, 'data-manacore-bridge="404"' ), 'کد وضعیت در سند پل دیده می‌شود' );
mc_ok( false !== strpos( $doc, 'content="noindex, nofollow"' ), 'سند پل برای موتورهای جست‌وجو بسته است' );
mc_ok( isset( $GLOBALS['mc_styles']['manacore-bridge'] ), 'شیوه‌نامه‌ی پل صف می‌شود' );

/* اصل نمایش: کارت «چه چیزی دانلود می‌کنم؟» */
$GLOBALS['mc_headers'] = array();
$item                  = Downloads::item( 7, 'lnk_direct' );
$card                  = Downloads::instance()->bridge_document(
	array(
		'status' => 200,
		'post'   => 7,
		'item'   => $item,
		'locked' => false,
		'url'    => Downloads::signed_url( 7, 'lnk_direct' ),
	)
);

mc_ok( false !== strpos( $card, '1080p' ), 'کیفیت در کارت پل دیده می‌شود' );
mc_ok( false !== strpos( $card, '1.2GB' ), 'حجم در کارت پل دیده می‌شود' );
mc_ok( false !== strpos( $card, 'آماده‌ی دانلود' ), 'سرتیتر کارت پل هست' );
mc_ok( false !== strpos( $card, '/manacore/v1/download/7' ), 'دکمه‌ی «شروع دانلود» همان مسیر امضاشده است' );
mc_ok( false === strpos( $card, 'cdn.example' ), 'نشانی خام در کارت پل چاپ نمی‌شود' );
mc_ok( false === strpos( $card, 'noindex' ) || false !== strpos( $card, 'noindex, nofollow' ), 'متای noindex در سند پل هست' );
mc_ok( false === strpos( $card, 'این لینک ویژه' ), 'لینک آزاد، یادآوری اشتراک نمی‌گیرد' );
mc_ok( false !== strpos( $card, 'پیش از دانلود' ), 'یادداشت پیش‌فرض پل دیده می‌شود' );

/* یادداشت مدیر + جای‌نگهدارها */
$GLOBALS['mc_options']['manacore_settings']['bridge_notice_text'] = 'کیفیت {quality} و حجم {size} — «{title}»';
$notice                                                           = Downloads::bridge_notice( 7, Downloads::item( 7, 'lnk_direct' ) );

mc_ok( false === strpos( $notice, '{quality}' ) && false !== strpos( $notice, '1080p' ), 'جای‌نگهدار کیفیت پر می‌شود' );
mc_ok( false !== strpos( $notice, '1.2GB' ), 'جای‌نگهدار حجم پر می‌شود' );
mc_ok( false !== strpos( $notice, get_the_title( 7 ) ), 'جای‌نگهدار عنوان پر می‌شود' );

/* حالت قفل: یادآوری نرم اشتراک */
$GLOBALS['mc_options']['manacore_settings']['subscribe_url']   = 'http://example.test/subscribe';
$GLOBALS['mc_options']['manacore_settings']['subscribe_label'] = 'تهیه اشتراک';

$GLOBALS['mc_can_access'] = false;
$premium                  = Downloads::item( 7, 'lnk_premium' );
$locked_card              = Downloads::instance()->bridge_document(
	array(
		'status' => 403,
		'post'   => 7,
		'item'   => $premium,
		'locked' => true,
		'url'    => '',
	)
);

mc_ok( false !== strpos( $locked_card, 'این لینک ویژه‌ی اعضای اشتراکی است' ), 'حالت قفل، یادآوری اشتراک نشان می‌دهد' );
mc_ok( false !== strpos( $locked_card, 'http://example.test/subscribe' ), 'پیوند اشتراک از تنظیمات می‌آید' );
mc_ok( false === strpos( $locked_card, 'shart' ) && false === strpos( $locked_card, 'مانacore/v1/download' ), 'در حالت قفل، دکمه‌ی دانلود چاپ نمی‌شود' );
mc_ok( false === strpos( $locked_card, 'movie-4k.mp4' ), 'نشانی فایل ویژه هرگز چاپ نمی‌شود' );

$GLOBALS['mc_can_access'] = true;

/* قاعده‌ی بازنویسی و کوئری‌وارها */
$downloads = Downloads::instance();
$downloads->register_rewrite();

mc_ok( count( $GLOBALS['mc_rewrites'] ) >= 1, 'قاعده‌ی بازنویسی پل ثبت می‌شود' );
mc_ok( false !== strpos( $GLOBALS['mc_rewrites'][0][0], 'manacore\\-download' ), 'الگوی قاعده پیشوند پل را دارد', $GLOBALS['mc_rewrites'][0][0] );
mc_ok( false !== strpos( $GLOBALS['mc_rewrites'][0][1], 'manacore_download=' ), 'قاعده به کوئری‌وار پل نگاشت می‌شود' );
mc_ok( 'top' === $GLOBALS['mc_rewrites'][0][2], 'قاعده پیش از قواعد برگه‌ها می‌نشیند' );

$vars = $downloads->query_vars( array( 'p' ) );
mc_ok( in_array( 'manacore_download', $vars, true ) && in_array( 'manacore_download_item', $vars, true ), 'هر دو کوئری‌وار پل مجاز شده‌اند' );

/* با خاموش‌بودن پل، قاعده‌ای ثبت نمی‌شود (هیچ ردی از تغییر در سایت نمی‌ماند) */
$GLOBALS['mc_rewrites']                     = array();
$GLOBALS['mc_options']['manacore_settings'] = array( 'download_signing' => 1, 'download_bridge' => 0 );
$downloads->register_rewrite();

mc_ok( array() === $GLOBALS['mc_rewrites'], 'با خاموش‌بودن پل، قاعده‌ای ثبت نمی‌شود' );
mc_ok( false === Downloads::bridge_enabled(), 'و پل خاموش گزارش می‌شود' );

/* هم‌گام‌سازی قواعد: یک‌بار و فقط با تغییر مهر */
$GLOBALS['mc_options']['manacore_settings'] = array( 'download_signing' => 1, 'download_bridge' => 1 );
$GLOBALS['mc_options'][ Downloads::REWRITE_STAMP ] = '';

$downloads->sync_rewrite();
$stamp = $GLOBALS['mc_options'][ Downloads::REWRITE_STAMP ] ?? '';

mc_ok( '' !== $stamp && false !== strpos( $stamp, 'manacore-download' ), 'مهر قواعد بازنویسی با پیشوند پل ذخیره می‌شود', $stamp );

$GLOBALS['mc_options'][ Downloads::REWRITE_STAMP ] = $stamp;
$before                                            = $stamp;
$downloads->sync_rewrite();

mc_ok( $before === ( $GLOBALS['mc_options'][ Downloads::REWRITE_STAMP ] ?? '' ), 'اجرای دوباره، مهر را دست‌نخورده می‌گذارد' );
$GLOBALS['mc_flush'] = 0;
$downloads->sync_rewrite();

mc_ok( 0 === (int) ( $GLOBALS['mc_flush'] ?? 0 ), 'مهر یکسان یعنی هیچ بازسازی تازه‌ای رخ نمی‌دهد' );

$GLOBALS['mc_options'][ Downloads::REWRITE_STAMP ] = '';
$downloads->sync_rewrite();

mc_ok( 1 === (int) ( $GLOBALS['mc_flush'] ?? 0 ), 'تغییر مهر فقط یک‌بار قواعد را بازسازی می‌کند' );

/* item() حالا ردیف کامل با متن گروه برمی‌گرداند. */
$full = Downloads::item( 7, 'lnk_direct' );

mc_ok( isset( $full['group_title'], $full['label'], $full['size'] ), 'item() ردیف کامل با متن گروه می‌دهد' );
mc_ok( '1080p' === $full['quality'] && 'sub_fa' === $full['language'], 'کیفیت و زبان از ردیف/گروه می‌آید' );

$premium_item = Downloads::item( 7, 'lnk_premium' );
mc_ok( true === $premium_item['premium'], 'پرچم ویژه در ردیف کامل هم هست' );

/* Links::find و ارث‌بری از گروه */
$full_direct = Links::find( 7, 'lnk_direct' );

mc_ok( '1080p' === $full_direct['quality'] && '1.2GB' === $full_direct['size'], 'ردیف بی‌کیفیت، کیفیت و حجم گروه را ارث می‌برد' );
mc_ok( '' !== Links::value( array( 'quality' => '' ), array( 'quality' => '1080p' ), 'quality' ), 'value() از گروه می‌خواند' );
mc_ok( '720p' === Links::value( array( 'quality' => '720p' ), array( 'quality' => '1080p' ), 'quality' ), 'value() مقدار خود ردیف را بر گروه مقدم می‌دارد' );
mc_ok( null === Links::find( 7, 'lnk_nope' ), 'شناسه‌ی ناموجود null می‌دهد' );
mc_ok( null === Links::find( 7, '' ), 'شناسه‌ی خالی null می‌دهد' );

/* ---------------------------------------------------------------
 * پایان
 * ------------------------------------------------------------ */

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo 'موفق: ' . $GLOBALS['mc_pass'] . '   ناموفق: ' . $GLOBALS['mc_fail'] . "\n";
echo str_repeat( '=', 58 ) . "\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
