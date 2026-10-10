<?php
/**
 * آزمون حالت نوار فیلتر و نوار مرور، و برگه‌های عادی.
 *
 * ریشه‌های مشکلی که این آزمون از بازگشتشان جلوگیری می‌کند:
 *
 *   ۱. «دکمه‌ی ارسال» فرم‌های فیلتر و نوار مرور هر پارامتری را که خودشان
 *      نمی‌ساختند پاک می‌کرد؛ تغییر مرتب‌سازی، فیلترهای سایدبار را می‌برد و
 *      تغییر سایدبار، جستجو و مرتب‌سازی را. حالا `manacore_preserved_query_args()`
 *      هر چیزی را که فرم خودش نمی‌سازد نگه می‌دارد.
 *   ۲. پارامترهای آرایه‌ای (`mc_genre[]`) دور ریخته می‌شدند؛ حالا به‌صورت
 *      ورودی‌های پنهان `name[]` برمی‌گردند.
 *   ۳. `s` روی برگه‌ی عادی (`/browse/?s=x`) وردپرس را به حالت جستجو می‌برد و
 *      برگه ۴۰۴ می‌شد. `Query::drop_search_on_pages()` این پارامتر را روی
 *      برگه‌ها کنار می‌گذارد.
 *   ۴. گزینه‌های «پرطرفدارترین‌ها» در سایدبار به `/trending/` می‌رفت که
 *      برگه‌ای ندارد و ۴۰۴ می‌داد.
 *   ۵. دکمه‌ی بازیگر/کارگردان: نامک‌های درصدرمزگذاری‌شده‌ی فارسی در
 *      `sanitize_html_class()` از بین می‌رفتند؛ حالا شناسه‌ی عددی هر نقش
 *      روی دکمه و کارت یکسان است.
 *
 * اجرا: php wp/tests/test-filter-state.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );

/* ---- توابع پایه‌ی وردپرس ---- */
function __( $text, $domain = '' ) { return $text; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
function apply_filters( $tag, $value ) { return $value; }
function add_filter() {}
function add_action() {}
function is_admin() { return false; }

/* ترِیت Singleton که کلاس Query به آن نیاز دارد (فقط ساختار، بی‌رفتار). */
eval( 'namespace ManaCore\\Core; trait Singleton { public static function instance() { return new static(); } }' );

require MANACORE_PATH . 'includes/functions.php';
require MANACORE_PATH . 'includes/class-query.php';

{

	$pass = 0;
	$fail = 0;

	function mc_ok( $ok, $label ) {
		global $pass, $fail;

		if ( $ok ) {
			++$pass;
			echo '  ✓ ' . $label . "\n";
		} else {
			++$fail;
			echo '  ✗ ' . $label . "\n";
		}
	}

	function mc_strip_comments( $src ) {
		$src = preg_replace( '#/\*.*?\*/#s', '', $src );
		return preg_replace( '#^\s*//.*$#m', '', $src );
	}

	echo "==========================================================\n";
	echo "آزمون حالت نوار فیلتر و برگه‌های عادی\n";
	echo "==========================================================\n\n";

	/* ---- ۱. پارامترهای نگه‌داشته‌شده ---- */
	echo "۱. manacore_preserved_query_args()\n";

	$_GET = array(
		'manacore_q' => 'درام',
		'mc_year_min' => '1972',
		'mc_year_max' => '',
		'mc_genre'    => array( '%d8%af%d8%b1%d8%a7%d9%85', '', 'x' ),
		'mc_sort'     => 'oldest',
		'type'        => 'series',
		'paged'       => '3',
		'page'        => '2',
		'nested'      => array( array( 'deep' ) ),
	);

	$kept = manacore_preserved_query_args( array( 'manacore_q', 'mc_sort', 'type' ) );

	mc_ok( '1972' === ( $kept['mc_year_min'] ?? null ), 'پارامتر فیلترِ فرم دیگر (سال) نگه داشته می‌شود' );
	mc_ok( array( '%d8%af%d8%b1%d8%a7%d9%85', 'x' ) === ( $kept['mc_genre'] ?? null ), 'آرایه‌ی ژانر با مقدارهای خالی پاک‌سازی و نگه داشته می‌شود' );
	mc_ok( ! array_key_exists( 'manacore_q', $kept ), 'پارامتری که همان فرم می‌سازد، تکراری نمی‌شود' );
	mc_ok( ! array_key_exists( 'mc_sort', $kept ), 'مرتب‌سازی که همان فرم می‌سازد، تکراری نمی‌شود' );
	mc_ok( ! array_key_exists( 'type', $kept ), 'نوع نشانی که همان فرم می‌سازد، تکراری نمی‌شود' );
	mc_ok( ! array_key_exists( 'paged', $kept ) && ! array_key_exists( 'page', $kept ), 'صفحه‌بندی هرگز نگه داشته نمی‌شود' );
	mc_ok( ! array_key_exists( 'mc_year_max', $kept ), 'مقدار خالی نگه داشته نمی‌شود' );
	mc_ok( ! array_key_exists( 'nested', $kept ), 'آرایه‌ی تودرتو نگه داشته نمی‌شود' );

	/* ---- ۲. چاپ ورودی‌های پنهان ---- */
	echo "\n۲. manacore_hidden_fields()\n";

	ob_start();
	manacore_hidden_fields( $kept );
	$html = ob_get_clean();

	mc_ok( false !== strpos( $html, 'name="mc_year_min" value="1972"' ), 'ورودی پنهان برای مقدار ساده تولید می‌شود' );
	mc_ok( false !== strpos( $html, 'name="mc_genre[]" value="x"' ), 'ورودی پنهان آرایه با نام name[] تولید می‌شود' );
	mc_ok( 2 === substr_count( $html, 'name="mc_genre[]"' ), 'هر مقدار آرایه یک ورودی پنهان است' );

	$_GET = array( 'mc_year_min' => '1972"><script>alert(1)</script>' );
	ob_start();
	manacore_hidden_fields( manacore_preserved_query_args( array() ) );
	$escaped = ob_get_clean();
	mc_ok( false === strpos( $escaped, '<script>' ), 'مقدار نگه‌داشته‌شده در ویژگی escape می‌شود' );

	/* ---- ۳. برگه‌ی عادی و پارامتر s ---- */
	echo "\n۳. Query::drop_search_on_pages()\n";

	$query = new ManaCore\Core\Query();

	$vars = $query->drop_search_on_pages( array( 'pagename' => 'browse', 's' => 'x', 'mc_year_min' => '1972' ) );
	mc_ok( ! array_key_exists( 's', $vars ), 'روی برگه (pagename) پارامتر s کنار گذاشته می‌شود' );
	mc_ok( '1972' === $vars['mc_year_min'], 'سایر پارامترهای برگه دست‌نخورده می‌مانند' );

	$vars = $query->drop_search_on_pages( array( 's' => 'x' ) );
	mc_ok( 'x' === ( $vars['s'] ?? null ), 'جستجوی بدون برگه (صفحه‌ی جستجو و آرشیو) دست‌نخورده می‌ماند' );

	/* ---- ۴. متن‌های منبع ---- */
	echo "\n۴. کدهای منبع: گزینه‌ی «مشاهده‌ی همه» و شناسه‌ی نقش\n";

	$root    = dirname( __DIR__ );
	$sidebar = file_get_contents( $root . '/themes/koohe-film/parts/sidebar.html' );
	$blocks  = mc_strip_comments( file_get_contents( MANACORE_PATH . 'includes/class-blocks.php' ) );
	$query_s = mc_strip_comments( file_get_contents( MANACORE_PATH . 'includes/class-query.php' ) );

	mc_ok( false === strpos( $sidebar, 'href="/trending/"' ), 'سایدبار دیگر به /trending/ (برگه‌ی ناموجود) نمی‌رود' );
	mc_ok( false !== strpos( $sidebar, 'href="/browse/?mc_sort=rating"' ), 'مشاهده‌ی همه‌ی پرطرفدارترین‌ها به کشف با مرتب‌سازی امتیاز می‌رود' );
	mc_ok( false !== strpos( $query_s, "add_filter( 'request', array( \$this, 'drop_search_on_pages' ) )" ), 'فیلتر request برای s روی برگه ثبت است' );
	mc_ok( false !== strpos( $blocks, "esc_attr( (string) \$term->term_id )" ), 'دکمه‌ی نقش با شناسه‌ی عددی نوشته می‌شود' );
	mc_ok( false !== strpos( $blocks, "wp_list_pluck( \$termlist, 'term_id' )" ), 'کارت‌های عامل با شناسه‌ی عددی نقش‌ها نوشته می‌شوند' );
	mc_ok( false === strpos( $blocks, "array_map( 'sanitize_html_class', \$slugs )" ), 'sanitize_html_class (که ٪ را حذف می‌کرد) از مسیر نقش‌ها حذف شده است' );

	echo "\n----------------------------------------------\n";
	echo 'موفق: ' . $pass . '   ناموفق: ' . $fail . "\n";
	exit( $fail > 0 ? 1 : 0 );
}
