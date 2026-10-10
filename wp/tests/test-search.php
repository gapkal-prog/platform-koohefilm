<?php
/**
 * آزمون موتور جستجو: نرمال‌سازی فارسی، شکل‌های جایگزین، پرس‌وجو و اتصال‌ها.
 *
 * این آزمون از همان پوسته‌ی ساختگی بقیه‌ی آزمون‌ها استفاده می‌کند و چهار چیز
 * را می‌سنجد:
 *   ۱. `Search::normalize()` — یکسان‌سازی نویسه‌های عربی/فارسی، نیم‌فاصله،
 *      اعراب و رقم‌ها.
 *   ۲. `Search::variants()` — شکل‌های جایگزین و سقف تعدادشان.
 *   ۳. `Search::expand_sql()` — گسترش شرط‌های LIKE بدون شکستن ساختار کوئری
 *      وردپرس (سدِّ رمز و «و» میان واژه‌ها).
 *   ۴. اتصال‌ها: مسیر REST از `Search::query()` استفاده کند و قالب هم از
 *      مسیر REST خود افزونه (نه `wp/v2/search`) بخواند — همان اشتباهی که
 *      جستجوی سربرگ را همیشه «بدون نتیجه» می‌کرد.
 *
 * اجرا: php wp/tests/test-search.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_URL', 'http://example.test/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );

$GLOBALS['mc_state'] = array(
	'is_admin' => false,
);
function mc_s( $k ) { return $GLOBALS['mc_state'][ $k ]; }

/* ---- توابع پایه‌ی وردپرس ---- */
function __( $t, $d = '' ) { return $t; }
function apply_filters( $tag, $value ) { return $value; }
function add_filter() {}
function add_action() {}
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function is_admin() { return mc_s( 'is_admin' ); }

/** سازنده‌ی کوئری ساختگی: فقط آرگومان‌ها را نگاه می‌دارد. */
class WP_Query {
	public $args = array();
	public $vars = array();

	public function __construct( $args = array() ) {
		$this->args = (array) $args;
		$this->vars = (array) $args;
		$GLOBALS['mc_queries'][] = $args;
	}

	public function get( $key, $default = '' ) {
		return array_key_exists( $key, $this->vars ) ? $this->vars[ $key ] : $default;
	}

	public function is_search() {
		return '' !== (string) $this->get( 's' );
	}

	public function is_main_query() {
		return ! empty( $this->vars['mc_is_main'] );
	}
}

/** $wpdb ساختگی: esc_like و prepare با حداقل رفتار لازم. */
class MC_Fake_Wpdb {
	public $posts    = 'wp_posts';
	public $postmeta = 'wp_postmeta';

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) {
			$pos = strpos( $query, '%s' );

			if ( false === $pos ) {
				break;
			}

			$query = substr( $query, 0, $pos ) . "'" . addcslashes( (string) $arg, "'\\" ) . "'" . substr( $query, $pos + 2 );
		}

		return $query;
	}
}

$GLOBALS['wpdb'] = new MC_Fake_Wpdb();

spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'ManaCore\\Core\\' ) ) {
			return;
		}
		$rel = strtolower( str_replace( array( 'ManaCore\\Core\\', '\\', '_' ), array( '', '/', '-' ), $class ) );
		foreach ( array( 'class-', 'trait-', 'interface-' ) as $p ) {
			$f = MANACORE_PATH . 'includes/' . $p . $rel . '.php';
			if ( file_exists( $f ) ) {
				require_once $f;
				return;
			}
		}
	}
);
require_once MANACORE_PATH . 'includes/functions.php';

use ManaCore\Core\Search;

$pass = 0;
$fail = 0;

function mc_ok( $ok, $label, $extra = '' ) {
	global $pass, $fail;

	if ( $ok ) {
		$pass++;
	} else {
		$fail++;
	}

	$extra = preg_replace( '/\s+/', ' ', (string) $extra );

	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $label
		. ( '' !== $extra ? '  — ' . substr( $extra, 0, $ok ? 60 : 600 ) : '' )
		. "\n";
}

echo "۱. نرمال‌سازی فارسی\n";

mc_ok( 'شوگان' === Search::normalize( 'شوگان' ), 'نویسه‌ی «ی» عربی به «ی» فارسی' );
mc_ok( 'کتاب' === Search::normalize( 'كتاب' ), 'نویسه‌ی «ک» عربی به «ک» فارسی' );
mc_ok( 'مصطفی' === Search::normalize( 'مصطفى' ), 'الف مقصوره به «ی»' );
mc_ok( 'می رود' === Search::normalize( "می\u{200C}رود" ), 'نیم‌فاصله به فاصله' );
mc_ok( '2024' === Search::normalize( '۲۰۲۴' ), 'رقم‌های فارسی به لاتین' );
mc_ok( '2024' === Search::normalize( '٢٠٢٤' ), 'رقم‌های عربی به لاتین' );
mc_ok( 'سلام' === Search::normalize( 'سَلام' ), 'اعراب حذف می‌شود' );
mc_ok( 'dune part two' === Search::normalize( "  Dune   Part\nTwo  " ), 'حروف لاتین کوچک و فاصله‌ها یکسان' );
mc_ok( '' === Search::normalize( '' ), 'ورودی خالی' );

echo "\n۲. شکل‌های جایگزین\n";

$plain = Search::variants( 'داستان' );
mc_ok( 1 === count( $plain ), 'واژه‌ی بدون نویسه‌ی هم‌ارز یک شکل دارد', count( $plain ) . ' شکل' );
mc_ok( 'داستان' === $plain[0], 'شکل خودِ کاربر اول می‌آید' );

$persian = Search::variants( 'یادداشت' );
mc_ok( in_array( 'يادداشت', $persian, true ), 'شکل عربیِ «ی» ساخته می‌شود' );
mc_ok( 'یادداشت' === $persian[0], 'ترتیب شکل‌ها حفظ می‌شود' );

$arabic = Search::variants( 'يك' );
mc_ok( in_array( 'یک', $arabic, true ), 'ورودی عربی شکل فارسی می‌گیرد' );

$zwnj = Search::variants( "می‌رود" );
mc_ok( in_array( 'میرود', $zwnj, true ), 'شکل چسبیده‌ی نیم‌فاصله' );
mc_ok( in_array( 'می رود', $zwnj, true ), 'شکل بافاصله‌ی نیم‌فاصله' );

$digits = Search::variants( '2024' );
mc_ok( in_array( '۲۰۲۴', $digits, true ), 'شکل فارسیِ رقم‌ها' );

$many = Search::variants( 'می‌كند١٢٣٤' );
mc_ok( count( $many ) <= 6, 'سقف شکل‌ها رعایت می‌شود', count( $many ) . ' شکل' );
mc_ok( count( Search::variants( '' ) ) === 0, 'واژه‌ی خالی شکل ندارد' );
mc_ok( count( $many ) === count( array_unique( $many ) ), 'شکل‌ها تکراری نیستند' );

echo "\n۳. پاک‌سازی نوع‌های محتوا\n";

mc_ok( array( 'movie', 'series' ) === Search::sanitize_types( 'movie, series' ), 'رشته با کاما' );
mc_ok( array( 'movie', 'anime' ) === Search::sanitize_types( array( 'movie', 'anime' ) ), 'آرایه' );
mc_ok( array( 'movie', 'series' ) === Search::sanitize_types( 'Movie movie series' ), 'یکتاسازی و کوچک‌سازی' );
mc_ok( array() === Search::sanitize_types( '   ' ), 'ورودی خالی' );

echo "\n۴. پرس‌وجوی مشترک\n";

$GLOBALS['mc_queries'] = array();
Search::query( array( 's' => 'شوگان', 'per_page' => 6 ) );
$args = $GLOBALS['mc_queries'][0];

mc_ok( array( 'movie', 'series', 'anime' ) === $args['post_type'], 'نوع‌های محتوا، آثار سایت' );
mc_ok( 'publish' === $args['post_status'], 'فقط منتشرشده‌ها' );
mc_ok( 'شوگان' === $args['s'], 'عبارت جستجو منتقل می‌شود' );
mc_ok( 6 === $args['posts_per_page'], 'تعداد در برگه' );
mc_ok( ! empty( $args['manacore_search'] ), 'پرچم شکل‌های جایگزین روشن است' );
mc_ok( ! empty( $args['manacore_search_meta'] ), 'جستجو در نام اصلی/نام‌های دیگر روشن است' );
mc_ok( true === $args['no_found_rows'], 'شمارش کل به‌طور پیش‌فرض خاموش' );

$GLOBALS['mc_queries'] = array();
Search::query( array( 's' => 'dune', 'type' => 'movie,episode', 'per_page' => 100, 'found_rows' => true ) );
$args = $GLOBALS['mc_queries'][0];

mc_ok( array( 'movie' ) === $args['post_type'], 'نوع‌های بیگانه (قسمت) از فیلتر بیرون می‌مانند' );
mc_ok( 24 === $args['posts_per_page'], 'سقف تعداد در برگه' );
mc_ok( false === $args['no_found_rows'], 'شمارش کل با درخواست صریح' );

$GLOBALS['mc_queries'] = array();
Search::query( array( 's' => '   ' ) );
$args = $GLOBALS['mc_queries'][0];

mc_ok( array( 0 ) === $args['post__in'], 'عبارت خالی همه‌ی محتوا را برنمی‌گرداند' );
mc_ok( ! isset( $args['s'] ), 'عبارت خالی وارد کوئری نمی‌شود' );

echo "\n۵. گسترش شرط‌های LIKE\n";

/** ساخت کوئری ساختگی برای فیلتر posts_search. */
function mc_search_query( $term, $flags = array(), $main = false ) {
	$vars = array_merge( array( 's' => $term, 'mc_is_main' => $main ), $flags );

	return new WP_Query( $vars );
}

/** همان رشته‌ای که وردپرس برای یک واژه می‌سازد (سه ستون + سدِّ رمز). */
function mc_wp_search_sql( $term, $column = 'wp_posts.post_title' ) {
	global $wpdb;

	$like = '%' . $wpdb->esc_like( $term ) . '%';

	return " AND ( ( ({$column} LIKE '" . $like . "') ) )  AND (post_password = '') ";
}

$search = Search::instance();

$sql    = mc_wp_search_sql( 'یادداشت' );
$query  = mc_search_query( 'یادداشت', array( 'manacore_search' => true ) );
$out    = $search->expand_sql( $sql, $query );

mc_ok( $out !== $sql, 'شرط واژه‌ی هم‌ارز گسترش می‌یابد' );
mc_ok( false !== strpos( $out, "'%یادداشت%'" ), 'شکل اصلی حفظ می‌شود' );
mc_ok( false !== strpos( $out, "'%يادداشت%'" ), 'شکل عربی اضافه می‌شود' );
mc_ok( false !== strpos( $out, ' OR ' ), 'شرط‌ها با OR به هم می‌پیوندند' );
mc_ok( false !== strpos( $out, "post_password = ''" ), 'سدِّ رمز دست‌نخورده می‌ماند' );
mc_ok(
	substr_count( $out, ' AND ' ) === substr_count( $sql, ' AND ' ),
	'ساختار «و»ی وردپرس عوض نمی‌شود (فقط شرط‌های LIKE باز می‌شوند)',
	$out
);

$same = mc_wp_search_sql( 'dune' );
mc_ok( $same === $search->expand_sql( $same, mc_search_query( 'dune', array( 'manacore_search' => true ) ) ), 'واژه‌ی بدون هم‌ارز دست‌نخورده می‌ماند' );

global $wpdb;
$weird = " AND ( ( (wp_posts.post_title LIKE '%50\\%off%') ) ) ";
mc_ok( $weird === $search->expand_sql( $weird, mc_search_query( '50%off', array( 'manacore_search' => true ) ) ), 'واژه با نویسه‌ی ویژه‌ی LIKE گسترش نمی‌یابد' );

$meta = " AND ( ( (wp_posts.post_title LIKE '%داستان%') OR EXISTS ( SELECT 1 FROM wp_postmeta mcm WHERE mcm.meta_value LIKE '%داستان%' ) ) ) ";
$clean = trim( preg_replace( '/\s+/', ' ', $meta ) );
mc_ok( $clean === trim( preg_replace( '/\s+/', ' ', $search->expand_sql( $meta, mc_search_query( 'داستان', array( 'manacore_search_meta' => true ) ) ) ) ), 'واژه‌ی بدون هم‌ارز در متا هم دست‌نخورده می‌ماند' );

$arabic_term = " AND ( ( (wp_posts.post_title LIKE '%يك%') ) ) ";
$expanded    = $search->expand_sql( $arabic_term, mc_search_query( 'يك', array( 'manacore_search' => true ) ) );
mc_ok( false !== strpos( $expanded, "'%یک%'" ), 'شکل فارسیِ واژه‌ی عربی اضافه می‌شود', $expanded );

mc_ok( $sql === $search->expand_sql( $sql, mc_search_query( 'یادداشت' ) ), 'کوئری بدون پرچم و بدون برگه‌ی جستجو گسترش نمی‌یابد' );

$GLOBALS['mc_state']['is_admin'] = true;
mc_ok( $sql === $search->expand_sql( $sql, mc_search_query( 'یادداشت', array( 'manacore_search' => true ) ) ), 'در پیشخوان دست‌نخورده می‌ماند' );
$GLOBALS['mc_state']['is_admin'] = false;

$main_sql = mc_wp_search_sql( 'یادداشت' );
mc_ok( $search->expand_sql( $main_sql, mc_search_query( 'یادداشت', array(), true ) ) !== $main_sql, 'کوئری اصلیِ برگه‌ی جستجو هم گسترش می‌یابد' );

echo "\n۶. اتصال‌ها (بازرسی منبع)\n";

/**
 * برداشتن توضیحات از یک فایل منبع.
 *
 * بازرسی منبع نباید با متن توضیحات فریب بخورد؛ مثلاً توضیحی که همین اشکال
 * قدیمی را روایت می‌کند، نباید مثل کد شکسته شمرده شود.
 *
 * @param string $source متن منبع.
 * @return string
 */
function mc_strip_comments( $source ) {
	$source = preg_replace( '#/\*.*?\*/#s', '', (string) $source );
	$source = preg_replace( '#(^|\s)//[^\n]*#', '$1', $source );

	return (string) $source;
}

$core = file_get_contents( MANACORE_PATH . 'manacore-core.php' );
mc_ok( false !== strpos( $core, 'Search::instance()->hooks()' ), 'موتور جستجو در راه‌اندازی افزونه ثبت شده است' );

$rest = file_get_contents( MANACORE_PATH . 'includes/class-rest-api.php' );
mc_ok( false !== strpos( $rest, 'Search::query(' ), 'مسیر REST از پرس‌وجوی مشترک استفاده می‌کند' );
mc_ok( false !== strpos( $rest, 'set_transient' ), 'پاسخ جستجو کش کوتاه‌مدت دارد' );

$theme_inc = dirname( __DIR__ ) . '/themes/koohe-film/inc/assets.php';
$theme_js  = dirname( __DIR__ ) . '/themes/koohe-film/assets/js/theme.js';
$header    = dirname( __DIR__ ) . '/themes/koohe-film/parts/header.html';

$inc = file_get_contents( $theme_inc );
$js  = file_get_contents( $theme_js );
$head = file_get_contents( $header );

mc_ok( false !== strpos( $inc, "'searchUrl'" ), 'قالب نشانی جستجوی افزونه را می‌دهد' );
mc_ok( false === strpos( mc_strip_comments( $js ), 'wp/v2/search' ), 'مسیر شکسته‌ی wp/v2/search از کد حذف شده است' );
mc_ok( false !== strpos( $js, 'config.searchUrl' ), 'اورلی از نشانی افزونه می‌خواند' );
mc_ok( false !== strpos( $js, "config.i18n.error" ), 'خطای جستجو پیام جداگانه دارد' );
mc_ok( false !== strpos( $head, 'data-koohe-search-label' ), 'برچسب فهرست نتایج در سربرگ هست' );

echo "\n----------------------------------------------\n";
echo 'موفق: ' . $pass . '   ناموفق: ' . $fail . "\n";
exit( $fail > 0 ? 1 : 0 );
