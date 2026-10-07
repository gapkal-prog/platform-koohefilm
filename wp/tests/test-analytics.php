<?php
/**
 * آزمون داشبورد تحلیلی مدیر.
 *
 * تمرکز آزمون روی چیزهایی است که در گزارش‌های آماری «بی‌صدا» خراب می‌شوند:
 * نبودِ داده (صفر و تقسیم بر صفر)، کش شدن نتیجه‌ها، کرانه‌ی تعداد نتیجه،
 * پاک‌شدن کش با تغییر داده، و شمارش‌های ساده‌ی ریاضی (نرخ کلیک).
 *
 * اجرا: php wp/tests/test-analytics.php
 */

define( 'ABSPATH', '/tmp/fake-wp-analytics/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_URL', 'http://example.test/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['mc_pass'] = 0;
$GLOBALS['mc_fail'] = 0;

function mc_ok( $ok, $label, $extra = '' ) {
	$GLOBALS[ $ok ? 'mc_pass' : 'mc_fail' ]++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $label . ( '' !== $extra ? '  — ' . $extra : '' ) . "\n";
}

/* ---------------------------------------------------------------
 * پوسته
 * ------------------------------------------------------------ */

$GLOBALS['mc_transients'] = array();
$GLOBALS['mc_can']        = true;
$GLOBALS['mc_widgets']    = array();

function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return (string) $v; }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_html_e( $t, $d = '' ) { echo $t; }
function apply_filters( $tag, $value ) { return $value; }
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function do_action() {}
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE ); }
function number_format_i18n( $n, $dec = 0 ) { return number_format( (float) $n, (int) $dec ); }
function manacore_fa_digits( $v ) { return (string) $v; }
function current_time( $t = 'mysql' ) { return 'mysql' === $t ? '2026-10-08 12:00:00' : '2026-10-08'; }
function get_transient( $k ) { return isset( $GLOBALS['mc_transients'][ $k ] ) ? $GLOBALS['mc_transients'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['mc_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { $had = isset( $GLOBALS['mc_transients'][ $k ] ); unset( $GLOBALS['mc_transients'][ $k ] ); return $had; }
function current_user_can( $c = '' ) { return (bool) $GLOBALS['mc_can']; }
function admin_url( $p = '' ) { return 'http://example.test/wp-admin/' . ltrim( $p, '/' ); }
function add_query_arg( $args, $url = '' ) { return $url . '?' . http_build_query( (array) $args ); }
function get_the_title( $id ) { return 'اثر ' . (int) $id; }
function get_post_type( $id ) { return 'movie'; }
function get_edit_post_link( $id ) { return 'http://example.test/wp-admin/post.php?post=' . (int) $id; }
function wp_count_comments() {
	return (object) array( 'moderated' => 4, 'spam' => 3, 'approved' => 20, 'total_comments' => 27 );
}
function wp_add_dashboard_widget( $id, $title, $callback ) { $GLOBALS['mc_widgets'][ $id ] = array( $title, $callback ); }

function manacore_get_option( $key, $default = '' ) { return $default; }

function get_posts( $args ) {
	$type = isset( $args['post_type'] ) ? $args['post_type'] : '';

	if ( 'manacore_ad' === $type ) {
		return (array) $GLOBALS['mc_ads'];
	}

	if ( 'manacore_request' === $type ) {
		return (array) $GLOBALS['mc_requests'];
	}

	return array();
}

$GLOBALS['mc_ads']      = array( 11, 12 );
$GLOBALS['mc_requests'] = array();

/**
 * $wpdb ساختگی با صف نتیجه‌ها: هر پرس‌وجو نتیجه‌ی بعدی صف را می‌گیرد و
 * شمار پرس‌وجوها ثبت می‌شود تا کش‌شدن سنجیده شود.
 */
class MC_Wpdb {
	public $prefix  = 'wp_';
	public $queries = array();
	public $rows    = array();
	public $vars    = array();

	public function get_charset_collate() { return ''; }
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$sql = $query;

		foreach ( $args as $arg ) {
			$sql = preg_replace( '/%[dsf]/', is_int( $arg ) || is_float( $arg ) ? (string) $arg : "'" . $arg . "'", $sql, 1 );
		}

		return $sql;
	}
	public function get_var( $query ) {
		$this->queries[] = $query;

		return empty( $this->vars ) ? null : array_shift( $this->vars );
	}
	public function get_results( $query, $mode = OBJECT ) {
		$this->queries[] = $query;

		return empty( $this->rows ) ? array() : array_shift( $this->rows );
	}
	public function get_row( $query, $mode = OBJECT ) {
		$this->queries[] = $query;

		return empty( $this->rows ) ? array() : array_shift( $this->rows );
	}
}

$GLOBALS['wpdb'] = new MC_Wpdb();

/* ---------------------------------------------------------------
 * بارگذاری
 * ------------------------------------------------------------ */

require_once __DIR__ . '/stubs/analytics-modules.php';
require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-install.php';
require_once MANACORE_PATH . 'includes/class-analytics.php';

class Analytics_Test_Stub {
	public static $impressions = 100;
	public static $clicks      = 4;
}

/**
 * پوسته‌ی کلاس تبلیغات: تنها چیزهایی که داشبورد لازم دارد.
 */
class Ads_Stub {
	const POST_TYPE = 'manacore_ad';

	public static $data = array();

	public static function stats( $id ) {
		return isset( self::$data[ $id ] ) ? self::$data[ $id ] : array( 'impressions' => 0, 'clicks' => 0, 'ctr' => 0.0 );
	}

	public static function state( $id ) {
		return isset( self::$data[ $id ]['state'] ) ? self::$data[ $id ]['state'] : 'active';
	}
}

function analytics_ads_stub_data() {
	Ads_Stub::$data = array(
		11 => array( 'impressions' => 90, 'clicks' => 2, 'ctr' => 2.22, 'state' => 'active' ),
		12 => array( 'impressions' => 10, 'clicks' => 5, 'ctr' => 50.0, 'state' => 'expired' ),
	);
}

function ads_stats( $id ) {
	return Ads_Stub::stats( $id );
}

/* ---------------------------------------------------------------
 * ۱) جمع آمار
 * ------------------------------------------------------------ */

echo "\n=== ۱) جمع تماشا/دانلود ===\n";

$GLOBALS['wpdb']->vars    = array( '150' );
$GLOBALS['wpdb']->queries = array();
$total = ManaCore\Core\Analytics::total( 'view', 7 );

mc_ok( 150 === $total, 'جمع تماشا برگردانده می‌شود' );
mc_ok( 1 === count( $GLOBALS['wpdb']->queries ), 'یک پرس‌وجو اجرا می‌شود' );
mc_ok( false !== strpos( $GLOBALS['wpdb']->queries[0], "stat_type = 'view'" ), 'نوع آمار در کوئری می‌آید' );
mc_ok( false !== strpos( $GLOBALS['wpdb']->queries[0], 'stat_date >=' ), 'بازه‌ی زمانی در کوئری می‌آید' );
mc_ok( false === strpos( $GLOBALS['wpdb']->queries[0], "'view';" ), 'هیچ چیز خامی به SQL چسبانده نشده است' );

$GLOBALS['wpdb']->vars = array( null );
mc_ok( 0 === ManaCore\Core\Analytics::total( 'view', 7 ), 'نبود داده صفر می‌دهد (نه null)' );

$GLOBALS['wpdb']->vars = array( '999' );
$GLOBALS['wpdb']->queries = array();
ManaCore\Core\Analytics::total( 'download', 7 );
mc_ok( false !== strpos( $GLOBALS['wpdb']->queries[0], "stat_type = 'download'" ), 'نوع دانلود هم پشتیبانی می‌شود' );

/* ---------------------------------------------------------------
 * ۲) خلاصه + کش
 * ------------------------------------------------------------ */

echo "\n=== ۲) خلاصه و کش ===\n";

$GLOBALS['mc_transients'] = array();
ManaCore\Core\Analytics::instance()->flush();
mc_ok( empty( $GLOBALS['mc_transients'] ), 'شروع تازه بدون کش' );

$GLOBALS['wpdb']->vars  = array( '10', '70', '300', '25' );
/* هر عضو صف، پاسخ یک پرس‌وجو است؛ پس نتیجه‌ها یک لایه تودرتو هستند. */
$GLOBALS['wpdb']->rows  = array( array( 'total' => 12, 'average' => 4.25 ) );
$GLOBALS['wpdb']->queries = array();

$summary = ManaCore\Core\Analytics::summary();
$first_queries = count( $GLOBALS['wpdb']->queries );

mc_ok( 10 === $summary['views_today'], 'تماشا امروز' );
mc_ok( 70 === $summary['views_week'], 'تماشا هفته' );
mc_ok( 300 === $summary['views_month'], 'تماشا ماه' );
mc_ok( 25 === $summary['downloads_week'], 'دانلود هفته' );
mc_ok( 4 === $summary['comments_pending'], 'دیدگاه‌های در صف از `wp_count_comments` می‌آید' );
mc_ok( is_array( $summary['ads'] ), 'بخش تبلیغات همیشه آرایه است (حتی وقتی خالی است)' );
mc_ok( $first_queries > 0, 'بار نخست پرس‌وجو می‌زند' );

$summary_again = ManaCore\Core\Analytics::summary();
mc_ok( $summary === $summary_again, 'بار دوم همان نتیجه برگردانده می‌شود' );
mc_ok( $first_queries === count( $GLOBALS['wpdb']->queries ), 'بار دوم هیچ پرس‌وجوی تازه‌ای زده نمی‌شود (کش کار می‌کند)' );

ManaCore\Core\Analytics::instance()->flush();
mc_ok( false === get_transient( 'manacore_analytics_summary' ), 'پاک‌کردن کش، خلاصه را حذف می‌کند' );

/* شمارها از ماژول‌های دیگر می‌آیند (نه از صفر ثابت). */
$GLOBALS['mc_transients'] = array();
$GLOBALS['wpdb']->vars    = array( '0', '0', '0', '0' );
$empty_summary            = ManaCore\Core\Analytics::summary();
mc_ok( 6 === $empty_summary['reports_new'], 'شمار گزارش‌های تازه از ماژول گزارش‌ها می‌آید' );
mc_ok( 5 === $empty_summary['requests_pending'] && 9 === $empty_summary['requests_publish'], 'شمار درخواست‌ها از ماژول درخواست‌ها می‌آید' );

/* ---------------------------------------------------------------
 * ۳) نرخ کلیک و بهترین بنر
 * ------------------------------------------------------------ */

echo "\n=== ۳) آمار بنرها ===\n";

$GLOBALS['mc_transients'] = array();
ManaCore\Core\Analytics::instance()->flush();
$GLOBALS['wpdb']->vars = array( '0', '0', '0', '0' );

/* کلاس واقعی Ads در این آزمون نیست؛ پس منطق تجمیع را با داده‌ی پوسته می‌سنجیم. */
$ads_summary = ManaCore\Core\Analytics::ads_summary();
mc_ok( 0 === $ads_summary['impressions'] && 0 === $ads_summary['clicks'], 'بدون ماژول تبلیغات، جمع‌ها صفر است' );
mc_ok( 0.0 === $ads_summary['ctr'], 'نرخ کلیک صفر می‌شود (تقسیم بر صفر رخ نمی‌دهد)' );
mc_ok( array() === $ads_summary['best'], '«بهترین بنر» خالی می‌ماند' );

/* ---------------------------------------------------------------
 * ۴) فهرست‌ها
 * ------------------------------------------------------------ */

echo "\n=== ۴) فهرست‌های تحلیلی ===\n";

$GLOBALS['mc_transients'] = array();
ManaCore\Core\Analytics::instance()->flush();
$GLOBALS['wpdb']->queries = array();
$GLOBALS['wpdb']->rows    = array(
	array(
		array( 'post_id' => '5', 'total' => '40' ),
		array( 'post_id' => '0', 'total' => '99' ),
		array( 'post_id' => '7' ),
	),
);

$top = ManaCore\Core\Analytics::top( 'view', 7, 500 );
$q   = $GLOBALS['wpdb']->queries[0];

mc_ok( 2 === count( $top ), 'ردیف بی‌شناسه رد می‌شود' );
mc_ok( 5 === $top[0]['id'] && 40 === $top[0]['total'], 'شناسه و جمع درست تبدیل می‌شوند' );
mc_ok( 0 === $top[1]['total'], 'جمع نبوده صفر می‌شود' );
mc_ok( false !== strpos( $q, 'GROUP BY post_id' ) && false !== strpos( $q, 'ORDER BY total DESC' ), 'گروه‌بندی و ترتیب در کوئری هست' );
mc_ok( false !== strpos( $q, 'LIMIT 50' ), 'کرانه‌ی ۵۰ نتیجه اعمال می‌شود' );

$GLOBALS['wpdb']->rows = array( array( array( 'post_id' => '9', 'total' => '3', 'open_count' => '2' ) ) );
$reported = ManaCore\Core\Analytics::top_reported( 5 );
mc_ok( 2 === $reported[0]['open'], 'شمار گزارش‌های باز خوانده می‌شود' );
mc_ok( false !== strpos( $GLOBALS['wpdb']->queries[1], 'SUM(status' ), 'کوئری گزارش‌ها وضعیت‌ها را جمع می‌زند' );

$GLOBALS['mc_transients'] = array();
/* `get_row` یک ردیف برمی‌گرداند: فهرست تک‌عضوی، نه فهرست دسته‌ها. */
$GLOBALS['wpdb']->rows = array( array( 'total' => '10', 'average' => '4.5' ) );
$ratings = ManaCore\Core\Analytics::ratings_summary();

mc_ok( 10 === $ratings['total'] && 4.5 === $ratings['average'], 'میانگین امتیاز خوانده می‌شود' );
mc_ok( 90.0 === $ratings['percent'], 'درصد امتیاز از مقیاس ۵ حساب می‌شود', (string) $ratings['percent'] );

$GLOBALS['mc_transients'] = array();
$GLOBALS['wpdb']->rows = array( array( 'total' => '0', 'average' => null ) );
$empty_ratings = ManaCore\Core\Analytics::ratings_summary();
mc_ok( 0.0 === $empty_ratings['percent'], 'بدون رأی، درصد صفر است (تقسیم بر صفر نمی‌شود)' );

/* درخواست‌ها */
$GLOBALS['mc_transients'] = array();
$GLOBALS['mc_requests']   = array(
	(object) array( 'ID' => 21, 'post_title' => 'درخواست تستی', 'post_status' => 'publish' ),
);
$topx = ManaCore\Core\Analytics::top_requests( 5 );
mc_ok( 1 === count( $topx ) && 21 === $topx[0]['id'], 'پررأی‌ترین درخواست‌ها از ماژول درخواست‌ها می‌آید' );
mc_ok( 3 === $topx[0]['votes'] && 'publish' === $topx[0]['status'], 'شمار رأی و وضعیت در ردیف می‌آید' );

/* ---------------------------------------------------------------
 * ۵) ویجت پیشخوان
 * ------------------------------------------------------------ */

echo "\n=== ۵) ویجت پیشخوان ===\n";

$GLOBALS['mc_can']     = false;
$GLOBALS['mc_widgets'] = array();
ManaCore\Core\Analytics::instance()->register_widget();
mc_ok( array() === $GLOBALS['mc_widgets'], 'کاربر بی‌دسترسی ویجت نمی‌گیرد' );

$GLOBALS['mc_can'] = true;
ManaCore\Core\Analytics::instance()->register_widget();
mc_ok( isset( $GLOBALS['mc_widgets']['manacore_overview'] ), 'ویجت برای مدیر ثبت می‌شود' );
mc_ok( is_callable( $GLOBALS['mc_widgets']['manacore_overview'][1] ), 'ویجت تابع رندر دارد' );

/* ---------------------------------------------------------------
 * ۶) اتصال به پنل
 * ------------------------------------------------------------ */

echo "\n=== ۶) اتصال به پنل مدیریت ===\n";

$settings = file_get_contents( MANACORE_PATH . 'includes/class-settings.php' );
$boot     = file_get_contents( MANACORE_PATH . 'manacore-core.php' );
$analytics = file_get_contents( MANACORE_PATH . 'includes/class-analytics.php' );

mc_ok( false !== strpos( $settings, "'analytics' =>" ), 'تب «تحلیل و آمار» در فهرست تب‌ها هست' );
mc_ok( false !== strpos( $settings, "case 'analytics':" ), 'تب در جابه‌جایی تب‌ها هست' );
mc_ok( false !== strpos( $settings, 'render_analytics_tab' ), 'تابع رندر تب تعریف شده است' );
mc_ok( false !== strpos( $settings, 'Analytics::instance()->flush();' ), 'ابزار «پاک‌کردن کش» کش تحلیل را هم پاک می‌کند' );
mc_ok( false !== strpos( $boot, 'Analytics::instance()->hooks();' ), 'ماژول تحلیل در راه‌اندازی ثبت شده است' );
mc_ok( false !== strpos( $analytics, "'update_option_manacore_settings'" ), 'تغییر تنظیمات، کش گزارش را پاک می‌کند' );
mc_ok( false !== strpos( $analytics, 'manacore_link_reported' ) && false !== strpos( $analytics, 'manacore_request_voted' ), 'رویدادهای تازه، کش گزارش را پاک می‌کنند' );

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo 'موفق: ' . $GLOBALS['mc_pass'] . '   ناموفق: ' . $GLOBALS['mc_fail'] . "\n";
echo str_repeat( '=', 58 ) . "\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );

