<?php
/**
 * آزمون سمت سرور برای صفحه‌بندی حلقه‌ی بلوک‌های عنوان: حالت صفحه‌بندی
 * (`paginationMode` با پشتیبانی از `loadMore` قدیمی)، نام پارامتر صفحه
 * برای هر حلقه، خواندن صفحه‌ی درخواستی، شماره‌گذاری ترتیب حلقه‌ها، و
 * اعمال `loopPage` روی کوئری.
 *
 * اجرا: php wp/tests/test-pagination.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_URL', 'http://example.test/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['mc_pass'] = 0;
$GLOBALS['mc_fail'] = 0;

function mc_ok( $ok, $label, $extra = '' ) {
	if ( $ok ) {
		$GLOBALS['mc_pass']++;
		echo "  ✓ {$label}" . ( '' !== $extra ? "  — {$extra}" : '' ) . "\n";
	} else {
		$GLOBALS['mc_fail']++;
		echo "  ✗ {$label}\n";
	}
}

/* ---------------------------------------------------------------
 * بارگذاری خودکار کلاس‌ها (همان قرارداد افزونه)
 * ------------------------------------------------------------ */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'ManaCore\\Core\\' ) ) {
			return;
		}
		$relative = strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), substr( $class, strlen( 'ManaCore\\Core\\' ) ) ) );
		$file     = MANACORE_PATH . 'includes/class-' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/* ---------------------------------------------------------------
 * پوسته‌ی وردپرس (کمینه)
 * ------------------------------------------------------------ */
$GLOBALS['mc_query_var'] = array();

function __( $t, $d = '' ) { return $t; }
function _x( $t, $c = '', $d = '' ) { return $t; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return (string) $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_unslash( $v ) { return $v; }
function get_query_var( $var, $default = '' ) {
	return isset( $GLOBALS['mc_query_var'][ $var ] ) ? $GLOBALS['mc_query_var'][ $var ] : $default;
}

/* ---------------------------------------------------------------
 * ۱) حالت صفحه‌بندی و سازگاری با ویژگی بولی `loadMore`
 * ------------------------------------------------------------ */
echo "۱) حالت صفحه‌بندی\n";
use ManaCore\Core\Block_Support as S;

mc_ok( 'loadMore' === S::pagination_mode( array( 'paginationMode' => 'loadMore' ) ), 'حالت صریح loadMore خوانده می‌شود' );
mc_ok( 'numbered' === S::pagination_mode( array( 'paginationMode' => 'numbered', 'loadMore' => true ) ), 'حالت صریح، بر loadMore قدیمی اولویت دارد' );
mc_ok( 'none' === S::pagination_mode( array( 'paginationMode' => 'none', 'loadMore' => true ) ), 'حالت none حتی با loadMore=true مؤثر است' );
mc_ok( 'loadMore' === S::pagination_mode( array( 'loadMore' => true ) ), 'بلوک قدیمی: loadMore=true یعنی دکمه' );
mc_ok( 'none' === S::pagination_mode( array( 'loadMore' => false ) ), 'بلوک قدیمی: loadMore=false یعنی بدون صفحه‌بندی' );
mc_ok( 'none' === S::pagination_mode( array() ), 'بدون هیچ ویژگی: بدون صفحه‌بندی' );
mc_ok( 'none' === S::pagination_mode( array( 'paginationMode' => 'bogus', 'loadMore' => false ) ), 'مقدار نامعتبر به loadMore می‌افتد' );
mc_ok( 'loadMore' === S::pagination_mode( array( 'paginationMode' => 'bogus', 'loadMore' => true ) ), 'مقدار نامعتبر با loadMore=true دکمه می‌شود' );
mc_ok( 'none' === S::pagination_mode( array( 'paginationMode' => 'LOADMORE' ) ), 'تطبیق دقیق: حروف بزرگ پذیرفته نمی‌شود' );

mc_ok( S::paginates( array( 'paginationMode' => 'numbered' ) ), 'paginates: حالت numbered صفحه‌بندی است' );
mc_ok( S::paginates( array( 'paginationMode' => 'loadMore' ) ), 'paginates: حالت loadMore صفحه‌بندی است' );
mc_ok( ! S::paginates( array( 'paginationMode' => 'none' ) ), 'paginates: حالت none صفحه‌بندی نیست' );
mc_ok( ! S::paginates( array() ), 'paginates: بدون ویژگی صفحه‌بندی نیست' );

/* ---------------------------------------------------------------
 * ۲) نام پارامتر صفحه برای هر حلقه
 * ------------------------------------------------------------ */
echo "۲) پارامتر صفحه\n";
mc_ok( 'paged' === S::page_param( array( 'inheritFilters' => true ), 3 ), 'inheritFilters از paged استفاده می‌کند' );
mc_ok( 'paged' === S::page_param( array( 'inheritQuery' => true ), 1 ), 'inheritQuery از paged استفاده می‌کند' );
mc_ok( 'mc_page_2' === S::page_param( array(), 2 ), 'حلقه‌ی مستقل: mc_page_N با شماره‌ی حلقه' );
mc_ok( 'mc_page_1' === S::page_param( array(), 0 ), 'شماره‌ی حلقه‌ی کمتر از ۱ به ۱ می‌افتد' );
mc_ok( S::page_param( array(), 1 ) !== S::page_param( array(), 2 ), 'دو حلقه‌ی مستقل پارامتر متفاوت دارند' );

/* ---------------------------------------------------------------
 * ۳) خواندن صفحه‌ی درخواستی
 * ------------------------------------------------------------ */
echo "۳) صفحه‌ی درخواستی\n";
$_GET = array();
mc_ok( 1 === S::requested_page( 'mc_page_1' ), 'بدون پارامتر: صفحه‌ی ۱' );
$_GET = array( 'mc_page_1' => '3' );
mc_ok( 3 === S::requested_page( 'mc_page_1' ), 'mc_page_1=3 خوانده می‌شود' );
mc_ok( 1 === S::requested_page( 'mc_page_2' ), 'پارامتر حلقه‌ی دیگر روی این حلقه اثر ندارد' );
$_GET = array( 'mc_page_1' => '-4' );
mc_ok( S::requested_page( 'mc_page_1' ) >= 1, 'عدد منفی هرگز صفحه‌ی کمتر از ۱ نمی‌دهد' );
$_GET = array( 'mc_page_1' => 'abc' );
mc_ok( 1 === S::requested_page( 'mc_page_1' ), 'مقدار غیرعددی به صفحه‌ی ۱ می‌افتد' );
$_GET = array( 'paged' => '2' );
mc_ok( 2 === S::requested_page( 'paged' ), 'paged از $_GET خوانده می‌شود' );
$_GET = array();
$GLOBALS['mc_query_var']['paged'] = 4;
mc_ok( 4 === S::requested_page( 'paged' ), 'paged از query var هم خوانده می‌شود' );
$GLOBALS['mc_query_var'] = array();
$_GET = array();

/* ---------------------------------------------------------------
 * ۴) شماره‌گذاری ترتیب حلقه‌ها در رندر
 * ------------------------------------------------------------ */
echo "۴) شماره‌ی حلقه\n";
$reflection = new ReflectionProperty( S::class, 'loop_count' );
$reflection->setAccessible( true );
$reflection->setValue( null, 0 );
mc_ok( 1 === S::next_loop_index(), 'نخستین حلقه شماره‌ی ۱ می‌گیرد' );
mc_ok( 2 === S::next_loop_index(), 'حلقه‌ی دوم شماره‌ی ۲ می‌گیرد' );
mc_ok( 3 === S::next_loop_index(), 'حلقه‌ی سوم شماره‌ی ۳ می‌گیرد' );

/* ---------------------------------------------------------------
 * ۵) ویژگی‌های صفحه‌بندی در ثبت بلوک
 * ------------------------------------------------------------ */
echo "۵) ویژگی‌ها\n";
$attrs = S::pagination_attributes();
mc_ok( isset( $attrs['paginationMode'], $attrs['autoLoad'], $attrs['loadMore'], $attrs['loadText'], $attrs['showFilterSummary'] ), 'همه‌ی ویژگی‌های صفحه‌بندی تعریف شده‌اند' );
mc_ok( 'string' === $attrs['paginationMode']['type'] && '' === $attrs['paginationMode']['default'], 'paginationMode رشته و پیش‌فرض خالی است' );
mc_ok( 'boolean' === $attrs['autoLoad']['type'] && false === $attrs['autoLoad']['default'], 'autoLoad بولی و پیش‌فرض خاموش است' );

/* ---------------------------------------------------------------
 * ۶) نتیجه
 * ------------------------------------------------------------ */
echo "\nموفق: {$GLOBALS['mc_pass']}   ناموفق: {$GLOBALS['mc_fail']}\n";
exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
