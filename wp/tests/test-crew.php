<?php
/**
 * آزمون سمت سرور برای «عوامل» (Crew): تجزیه‌ی متن قدیمی و JSON، پاک‌سازی،
 * پیوند به صفحه‌ی عامل، خواندن بازیگران، و جست‌وجوی عوامل با اولویت نقش.
 *
 * اجرا: php wp/tests/test-crew.php
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
$GLOBALS['mc_p'] = array();

function mc_person( $id, $type, $title, $status = 'publish', $meta = array(), $roles = array() ) {
	$GLOBALS['mc_p'][ $id ] = array(
		'type'   => $type,
		'status' => $status,
		'title'  => $title,
		'meta'   => $meta,
		'roles'  => $roles,
	);
}

function __( $t, $d = '' ) { return $t; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return (string) $v; }
function esc_url_raw( $v ) { return (string) $v; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
function is_wp_error( $v ) { return false; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function taxonomy_exists( $t ) { return 'person_role' === $t; }
function get_post_type( $id ) { return isset( $GLOBALS['mc_p'][ (int) $id ] ) ? $GLOBALS['mc_p'][ (int) $id ]['type'] : false; }
function get_post_status( $id ) { return isset( $GLOBALS['mc_p'][ (int) $id ] ) ? $GLOBALS['mc_p'][ (int) $id ]['status'] : false; }
function get_the_title( $id ) { return isset( $GLOBALS['mc_p'][ (int) $id ] ) ? $GLOBALS['mc_p'][ (int) $id ]['title'] : ''; }
function get_permalink( $id ) { return 'http://example.test/person/' . (int) $id . '/'; }
function get_the_post_thumbnail_url( $id, $size = '' ) { return ''; }
function get_post_meta( $id, $key = '', $single = false ) {
	$meta = isset( $GLOBALS['mc_p'][ (int) $id ] ) ? $GLOBALS['mc_p'][ (int) $id ]['meta'] : array();
	return isset( $meta[ $key ] ) ? $meta[ $key ] : ( $single ? '' : array() );
}
function wp_get_post_terms( $id, $tax, $args = array() ) {
	return isset( $GLOBALS['mc_p'][ (int) $id ] ) ? $GLOBALS['mc_p'][ (int) $id ]['roles'] : array();
}

/**
 * get_posts پوشش‌دهنده: post_type، post_status، s (عنوان)، meta_query با OR و LIKE،
 * tax_query با نام ترم، post__not_in و numberposts.
 */
function get_posts( $args ) {
	$out = array();
	foreach ( $GLOBALS['mc_p'] as $id => $post ) {
		if ( $post['type'] !== $args['post_type'] || ! in_array( $post['status'], (array) $args['post_status'], true ) ) {
			continue;
		}
		if ( ! empty( $args['post__not_in'] ) && in_array( $id, $args['post__not_in'], true ) ) {
			continue;
		}
		if ( ! empty( $args['tax_query'][0]['terms'] ) && ! in_array( $args['tax_query'][0]['terms'], $post['roles'], true ) ) {
			continue;
		}
		if ( isset( $args['s'] ) && false === mb_stripos( $post['title'], $args['s'] ) ) {
			continue;
		}
		if ( ! empty( $args['meta_query'] ) ) {
			$hit = false;
			foreach ( $args['meta_query'] as $clause ) {
				if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
					continue;
				}
				$val = (string) ( $post['meta'][ $clause['key'] ] ?? '' );
				if ( '' !== $val && false !== stripos( $val, $args['meta_query'][0]['value'] ) ) {
					$hit = true;
				}
			}
			if ( ! $hit ) {
				continue;
			}
		}
		$out[] = $id;
	}
	return array_slice( $out, 0, (int) $args['numberposts'] );
}

use ManaCore\Core\Crew;

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo "آزمون عوامل (Crew)\n";
echo str_repeat( '=', 58 ) . "\n";

/* ---- ۱. تجزیه‌ی متن قدیمی ---- */
echo "\n[تجزیه‌ی متن قدیمی]\n";
$legacy = Crew::parse( 'علی، رضا ,  , مریم,علی' );
mc_ok( 3 === count( $legacy ), 'نام‌های خالی و تکراری حذف می‌شوند', count( $legacy ) . ' مورد' );
mc_ok( array( 'علی', 'رضا', 'مریم' ) === Crew::names( $legacy ), 'ویرگول فارسی و لاتین هر دو پشتیبانی می‌شوند' );
mc_ok( 0 === $legacy[0]['person_id'], 'متن قدیمی شناسه ندارد (person_id = 0)' );
mc_ok( array() === Crew::parse( '   ' ) && array() === Crew::parse( null ), 'ورودی خالی فهرست خالی می‌دهد' );

/* ---- ۲. تجزیه‌ی JSON ---- */
echo "\n[تجزیه‌ی JSON]\n";
$json = Crew::parse( '[{"name":"نیما","person_id":12},{"name":"نیما","person_id":0},{"name":"  ","person_id":5}]' );
mc_ok( 2 === count( $json ), 'JSON: نام خالی حذف و نام آزاد با شناسه‌ی متفاوت نگه داشته می‌شود' );
mc_ok( 12 === $json[0]['person_id'], 'JSON: شناسه‌ی عامل خوانده می‌شود' );
$broken = Crew::parse( '[broken' );
mc_ok( 1 === count( $broken ) && '[broken' === $broken[0]['name'] && 0 === $broken[0]['person_id'], 'JSON خراب به‌عنوان متن قدیمی تجزیه می‌شود' );

/* ---- ۳. پاک‌سازی و رمزگذاری ---- */
echo "\n[پاک‌سازی و ذخیره]\n";
mc_person( 12, 'person', 'نیما یوشیج', 'publish' );
mc_person( 13, 'person', 'پیش‌نویس', 'draft' );
mc_person( 99, 'movie', 'فیلم' );

$clean = Crew::sanitize( array(
	array( 'name' => '  نیما  ', 'person_id' => 12 ),
	array( 'name' => 'فیلم', 'person_id' => 99 ),
	array( 'name' => '', 'person_id' => 12 ),
	array( 'name' => 'بی‌عامل', 'person_id' => 0 ),
) );
mc_ok( 3 === count( $clean ), 'پاک‌سازی: نام خالی با شناسه از عنوان عامل پر می‌شود', count( $clean ) . ' مورد' );
mc_ok( 12 === $clean[0]['person_id'], 'شناسه‌ی عامل معتبر نگه داشته می‌شود' );
mc_ok( 0 === $clean[1]['person_id'], 'شناسه‌ای که پست نوع person نیست، صفر می‌شود' );
mc_ok( 'فیلم' === $clean[1]['name'], 'شناسه‌ی نامعتبر، نام آزاد را پاک نمی‌کند' );
$from_title = Crew::sanitize( array( array( 'name' => '', 'person_id' => 12 ) ) );
mc_ok( 1 === count( $from_title ) && 'نیما یوشیج' === $from_title[0]['name'] && 12 === $from_title[0]['person_id'], 'ردیف بی‌نام پیوندخورده، نامش را از عنوان عامل می‌گیرد' );
$no_name = Crew::sanitize( array( array( 'name' => '  ', 'person_id' => 0 ) ) );
mc_ok( array() === $no_name, 'ردیف بی‌نام و بی‌شناسه ذخیره نمی‌شود' );

$encoded = Crew::encode( $clean );
mc_ok( false !== strpos( $encoded, 'نیما' ) && false === strpos( $encoded, '\\u' ), 'JSON خروجی به‌صورت UTF-8 خام است' );
mc_ok( Crew::names( Crew::parse( $encoded ) ) === Crew::names( $clean ), 'رمزگذاری و تجزیه‌ی دوباره یکسان است' );
mc_ok( '' === Crew::encode( array() ) || '[]' === Crew::encode( array() ), 'فهرست خالی JSON خالی می‌دهد' );

/* ---- ۴. پیوند ---- */
echo "\n[پیوند به صفحه‌ی عامل]\n";
$linked = Crew::link_html( array( 'name' => 'نیما یوشیج', 'person_id' => 12 ) );
mc_ok( false !== strpos( $linked, 'class="manacore-person-link"' ) && false !== strpos( $linked, '/person/12/' ), 'عامل منتشرشده لینک می‌گیرد' );
$draft = Crew::link_html( array( 'name' => 'پیش‌نویس', 'person_id' => 13 ) );
mc_ok( false === strpos( $draft, '<a' ), 'عامل پیش‌نویس لینک نمی‌گیرد' );
$free = Crew::link_html( array( 'name' => '<b>نام</b>', 'person_id' => 0 ) );
mc_ok( '&lt;b&gt;نام&lt;/b&gt;' === $free, 'نام آزاد escape می‌شود (XSS)' );

/* ---- ۵. خواندن نقش‌ها از متای اثر (هر دو قالب) ---- */
echo "\n[خواندن نقش‌ها]\n";
mc_person( 20, 'movie', 'فیلم الف' );
$GLOBALS['mc_p'][20]['meta'] = array(
	'manacore_director' => 'علی، رضا',
	'manacore_writer'   => wp_json_encode( array( array( 'name' => 'نیما یوشیج', 'person_id' => 12 ) ) ),
	'manacore_cast'     => wp_json_encode( array(
		array( 'name' => '  نیما  ', 'character' => 'نقش', 'photo' => 'http://x.test/p.jpg', 'person_id' => 12 ),
		array( 'name' => '', 'person_id' => 12 ),
		array( 'name' => 'مریم', 'person_id' => 99 ),
	) ),
);
$directors = Crew::items( 20, 'director' );
mc_ok( array( 'علی', 'رضا' ) === Crew::names( $directors ), 'کارگردان متنی قدیمی خوانده می‌شود' );
$writers = Crew::items( 20, 'writer' );
mc_ok( 12 === $writers[0]['person_id'], 'نویسنده‌ی JSON شناسه‌ی عامل را نگه می‌دارد' );
mc_ok( false !== strpos( Crew::html( 20, 'writer' ), 'manacore-person-link' ), 'HTML نویسنده لینک عامل دارد' );
mc_ok( false === strpos( Crew::html( 20, 'director' ), '<a' ), 'کارگردان بدون شناسه لینک ندارد' );

$cast = Crew::cast( 20 );
mc_ok( 2 === count( $cast ), 'بازیگر بی‌نام حذف می‌شود', count( $cast ) . ' مورد' );
mc_ok( 'نیما' === $cast[0]['name'] && 12 === $cast[0]['person_id'], 'بازیگر با نام تمیز و شناسه' );
mc_ok( 0 === $cast[1]['person_id'], 'بازیگر با شناسه‌ی نامعتبر صفر می‌شود' );
mc_ok( '' === Crew::html( 20, 'unknown_role' ), 'نقش ناشناخته تهی است' );

/* ---- ۶. جست‌وجو با اولویت نقش ---- */
echo "\n[جست‌وجوی عوامل]\n";
mc_person( 1, 'person', 'علی حاتمی', 'publish', array(), array( 'کارگردان' ) );
mc_person( 2, 'person', 'مریم', 'publish', array( 'manacore_person_english' => 'Ali Mohammadi' ), array( 'بازیگر' ) );
mc_person( 3, 'person', 'امیر', 'publish', array( 'manacore_person_english' => 'Amir', 'manacore_person_birth_year' => 1360 ), array( 'کارگردان' ) );
mc_person( 4, 'person', 'علی‌رضا', 'draft', array(), array() );

$by_title = Crew::search( 'علی', 'director', 10 );
mc_ok( array( 1, 4 ) === array_column( $by_title, 'id' ) && true === $by_title[0]['role_match'] && false === $by_title[1]['role_match'], 'جست‌وجوی نام فارسی: نقش مطابق اول، پیش‌نویس هم دیده می‌شود' );

$by_latin = Crew::search( 'ali', 'director', 10 );
mc_ok( array( 2 ) === array_column( $by_latin, 'id' ) && false === $by_latin[0]['role_match'], 'جست‌وجوی نام لاتین با نقش ناهمخوان هم پیدا می‌کند' );

$browse = Crew::search( '', 'director', 10 );
mc_ok( true === $browse[0]['role_match'] && true === $browse[1]['role_match'] && 2 === count( array_filter( $browse, static function ( $i ) { return $i['role_match']; } ) ), 'بدون عبارت، کارگردان‌ها اول می‌آیند' );

$limited = Crew::search( '', 'director', 2 );
mc_ok( 2 === count( $limited ) && array_reduce( $limited, static function ( $c, $i ) { return $c && $i['role_match']; }, true ), 'سقف نتیجه رعایت می‌شود' );

$unique = Crew::search( 'علی', '', 10 );
$ids    = array_column( $unique, 'id' );
mc_ok( count( $ids ) === count( array_unique( $ids ) ), 'نتایج تکراری ندارند' );

$summary = Crew::summary( 3, true );
mc_ok( 'Amir' === $summary['english'] && 1360 === $summary['birth_year'] && array( 'کارگردان' ) === $summary['roles'], 'خلاصه‌ی عامل نام لاتین، سال تولد و نقش را دارد' );

/* ---- ۷. شناسه‌ی معتبر ---- */
echo "\n[شناسه‌ی معتبر]\n";
mc_ok( 12 === Crew::valid_person_id( '12' ) && 0 === Crew::valid_person_id( 99 ) && 0 === Crew::valid_person_id( 'abc' ), 'فقط شناسه‌ی پست person پذیرفته می‌شود' );

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo 'موفق: ' . $GLOBALS['mc_pass'] . '   ناموفق: ' . $GLOBALS['mc_fail'] . "\n";
echo str_repeat( '=', 58 ) . "\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
