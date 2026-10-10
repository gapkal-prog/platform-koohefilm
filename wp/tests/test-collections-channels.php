<?php
/**
 * آزمون سمت سرور برای «مجموعه» و «کانال»: پاک‌سازی فهرست مرتب آثار، ترتیب
 * خودکار، کاور، جست‌وجوی آثار برای انتخابگرها، و اعتبارسنجی نشانی ویدئو.
 *
 * اجرا: php wp/tests/test-collections-channels.php
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

/**
 * یک پست آزمایشی در فروشگاه ساختگی.
 */
function mc_post( $id, $type, $title, $status = 'publish', $meta = array() ) {
	$GLOBALS['mc_p'][ $id ] = array(
		'type'   => $type,
		'status' => $status,
		'title'  => $title,
		'meta'   => $meta,
	);
}

function mc_id( $value ) {
	return is_object( $value ) ? (int) $value->ID : (int) $value;
}

function __( $t, $d = '' ) { return $t; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $u ) { return (string) $u; }
function esc_url_raw( $u ) { return (string) $u; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $tag, $value ) { return $value; }
function manacore_title_post_types() {
	return apply_filters( 'manacore_title_post_types', array( 'movie', 'series', 'anime' ) );
}
function manacore_fa_digits( $v ) { return strtr( (string) $v, '0123456789', '۰۱۲۳۴۵۶۷۸۹' ); }

function get_post( $post = null ) {
	$id = mc_id( $post );
	if ( ! isset( $GLOBALS['mc_p'][ $id ] ) ) {
		return null;
	}
	$p = $GLOBALS['mc_p'][ $id ];

	return new WP_Post( $id, $p['type'], $p['status'], $p['title'] );
}

/**
 * نمونه‌ی ساختگی WP_Post با همان ویژگی‌های عمومی.
 */
class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_title;
	public $post_name;

	public function __construct( $id, $type, $status, $title ) {
		$this->ID          = $id;
		$this->post_type   = $type;
		$this->post_status = $status;
		$this->post_title  = $title;
		$this->post_name   = 'slug-' . $id;
	}
}

function get_post_type( $post = null ) {
	$p = get_post( $post );
	return $p ? $p->post_type : false;
}

function get_the_title( $post = null ) {
	$p = get_post( $post );
	return $p ? $p->post_title : '';
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	$id   = mc_id( $post_id );
	$meta = isset( $GLOBALS['mc_p'][ $id ]['meta'] ) ? $GLOBALS['mc_p'][ $id ]['meta'] : array();

	if ( '' === $key ) {
		return $meta;
	}
	if ( ! array_key_exists( $key, $meta ) ) {
		return $single ? '' : array();
	}

	return $single ? $meta[ $key ] : array( $meta[ $key ] );
}

function get_the_terms( $post = null, $taxonomy = '' ) {
	return array();
}

function get_the_post_thumbnail_url( $post = null, $size = 'post-thumbnail' ) {
	return '';
}

function get_edit_post_link( $id, $context = 'display' ) {
	return 'http://example.test/wp-admin/post.php?post=' . (int) $id;
}

function get_permalink( $post = null ) {
	return 'http://example.test/?p=' . mc_id( $post );
}

function wp_http_validate_url( $url ) {
	$parts = parse_url( (string) $url );
	if ( ! $parts || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return false;
	}
	if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return false;
	}
	return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : false;
}

/**
 * جایگزین سادهٔ WP_Query: فیلتر نوع، وضعیت، عبارت، حذف و سقف نتیجه.
 */
class WP_Query {
	public $posts = array();

	public function __construct( $args = array() ) {
		$types    = (array) ( isset( $args['post_type'] ) ? $args['post_type'] : array() );
		$statuses = (array) ( isset( $args['post_status'] ) ? $args['post_status'] : array() );
		$exclude  = array_map( 'intval', (array) ( isset( $args['post__not_in'] ) ? $args['post__not_in'] : array() ) );
		$term     = mb_strtolower( (string) ( isset( $args['s'] ) ? $args['s'] : '' ) );
		$limit    = (int) ( isset( $args['posts_per_page'] ) ? $args['posts_per_page'] : 10 );

		foreach ( $GLOBALS['mc_p'] as $id => $p ) {
			if ( ! in_array( $p['type'], $types, true ) || ! in_array( $p['status'], $statuses, true ) ) {
				continue;
			}
			if ( in_array( (int) $id, $exclude, true ) ) {
				continue;
			}
			if ( '' !== $term && false === mb_strpos( mb_strtolower( $p['title'] ), $term ) ) {
				continue;
			}
			$this->posts[] = get_post( $id );
		}

		$this->posts = array_slice( $this->posts, 0, $limit );
	}
}

/* ---- داده‌ی آزمون ---- */
mc_post( 1, 'movie', 'Alpha Movie' );
mc_post( 2, 'person', 'Not A Work' );
mc_post( 3, 'series', 'Beta Series' );
mc_post( 4, 'anime', 'Gamma Anime', 'draft' );
mc_post( 5, 'movie', 'Delta Movie', 'pending' );
mc_post( 6, 'episode', 'Episode Six' );
mc_post( 9, 'collection', 'Collection Nine', 'publish', array(
	'manacore_collection_sort'  => 'rating',
	'manacore_collection_cover' => 'https://cdn.example.test/cover.jpg',
) );
mc_post( 10, 'collection', 'Collection Ten', 'publish', array(
	'manacore_collection_sort' => 'bogus',
) );
mc_post( 11, 'channel', 'Channel Eleven', 'publish', array(
	'manacore_channel_now'    => 3,
	'manacore_channel_video'  => 'https://live.example.test/stream.m3u8',
) );

/* ---------------------------------------------------------------
 * بارگذاری کلاس‌ها
 * ------------------------------------------------------------ */
require_once MANACORE_PATH . 'includes/trait-singleton.php';

$collection = 'ManaCore\\Core\\Collection';
$picker     = 'ManaCore\\Core\\Picker';
$channel    = 'ManaCore\\Core\\Channel';

/* ---- ۱. پاک‌سازی فهرست مرتب ---- */
echo "\n[پاک‌سازی فهرست مرتب آثار]\n";
$clean = $collection::sanitize_items( array( '3', '1', '3', 'x', '0', '-2', 5, '2', '6' ) );
mc_ok( array( 3, 1, 5 ) === $clean, 'ترتیب ورودی حفظ و تکراری/غیرِاثر/غیرعددی حذف می‌شود', implode( ',', $clean ) );
mc_ok( array( 3, 1, 5 ) === $collection::sanitize_items( '3, 1 ,5,9999' ), 'ورودی رشته‌ای جداشده با ویرگول پذیرفته می‌شود' );
mc_ok( array( 5, 3, 1 ) === $collection::sanitize_items( array( '5', '3', '1' ) ), 'ترتیب انتخاب کاربر دقیقاً حفظ می‌شود (نه ترتیب شناسه)' );
mc_ok( array() === $collection::sanitize_items( 'chaos' ) && array() === $collection::sanitize_items( null ), 'ورودی نامعتبر فهرست تهی می‌دهد' );

$many = array();
for ( $i = 0; $i < 260; $i++ ) {
	$many[] = 1; // یک شناسه‌ی معتبر تکراری.
}
mc_ok( array( 1 ) === $collection::sanitize_items( $many ), 'تکراری‌ها روی شناسه‌ی واحد جمع می‌شوند' );

mc_post( 1000, 'movie', 'Bulk 0' );
for ( $i = 1; $i <= 250; $i++ ) {
	mc_post( 1000 + $i, 'movie', 'Bulk ' . $i );
}
$bulk = array();
for ( $i = 0; $i <= 250; $i++ ) {
	$bulk[] = 1000 + $i;
}
mc_ok( $collection::MAX_ITEMS === count( $collection::sanitize_items( $bulk ) ), 'سقف تعداد آثار هر مجموعه رعایت می‌شود', 'MAX_ITEMS=' . $collection::MAX_ITEMS );

/* ---- ۲. ترتیب خودکار و کاور ---- */
echo "\n[ترتیب خودکار و کاور]\n";
mc_ok( 'rating' === $collection::sort( 9 ), 'ترتیب معتبر خوانده می‌شود' );
mc_ok( 'manual' === $collection::sort( 10 ), 'مقدار نامعتبر به «دستی» برمی‌گردد' );
mc_ok( 'manual' === $collection::sort( 404 ), 'مجموعه‌ی ناموجود «دستی» است' );

$rating = $collection::sort_args( 'rating' );
mc_ok( 'manacore_imdb_rating' === $rating['meta_key'] && 'meta_value_num' === $rating['orderby'] && 'DESC' === $rating['order'], 'ترتیب امتیاز بر متای امتیاز IMDb و نزولی است' );
mc_ok( 'date' === $collection::sort_args( 'newest' )['orderby'] && 'DESC' === $collection::sort_args( 'newest' )['order'], 'جدیدترین ابتدا: date نزولی' );
mc_ok( 'ASC' === $collection::sort_args( 'oldest' )['order'], 'قدیمی‌ترین ابتدا: date صعودی' );
mc_ok( 'title' === $collection::sort_args( 'title' )['orderby'], 'ترتیب عنوان' );
mc_ok( array() === $collection::sort_args( 'manual' ) && array() === $collection::sort_args( 'nope' ), 'دستی و ناشناخته، آرگومان ترتیب نمی‌سازند' );

mc_ok( 'https://cdn.example.test/cover.jpg' === $collection::cover( 9 ), 'کاور اختصاصی بر تصویر شاخص اولویت دارد' );
mc_ok( '' === $collection::cover( 10 ), 'بدون کاور و بدون تصویر شاخص، نشانی تهی است' );

/* ---- ۳. جست‌وجوی آثار برای انتخابگرها ---- */
echo "\n[جست‌وجوی آثار]\n";
$all = $picker::works( '', '', 15 );
$ids = array_column( $all, 'id' );
mc_ok( ! in_array( 2, $ids, true ) && ! in_array( 6, $ids, true ), 'فقط آثار (فیلم/سریال/انیمه) و نه کسی و قسمت برمی‌گردند' );
mc_ok( in_array( 4, $ids, true ) && in_array( 5, $ids, true ), 'پیش‌نویس و در انتظار هم در انتخابگر دیده می‌شوند' );

$term = $picker::works( 'beta', '', 15 );
mc_ok( 1 === count( $term ) && 3 === $term[0]['id'], 'جست‌وجوی عنوان نتیجه‌ی درست می‌دهد' );

$typed = $picker::works( '', 'series', 15 );
mc_ok( 1 === count( $typed ) && 'series' === $typed[0]['type'], 'فیلتر نوع اثر اعمال می‌شود' );

$excluded = $picker::works( '', '', 15, array( 1, 3 ) );
mc_ok( ! in_array( 1, array_column( $excluded, 'id' ), true ), 'آثار حذف‌شده (exclude) از نتایج خارج می‌شوند' );

$capped = $picker::works( '', '', 500 );
mc_ok( count( $capped ) <= 30, 'سقف نتایج جست‌وجو ۳۰ است' );

$item = $picker::item( 3 );
mc_ok( 'سریال' === $item['type_label'] && 'منتشر شده' === $picker::status_label( 'publish' ), 'برچسب فارسی نوع و وضعیت' );
mc_ok( isset( $item['id'], $item['title'], $item['type'], $item['status'], $item['edit_url'], $item['thumb'] ), 'ساختار یک اثر در پیشخوان کامل است' );
mc_ok( null === $picker::item( 777 ), 'شناسه‌ی ناموجود اثر ندارد' );

mc_ok( 5 === $picker::valid_id( '5' ) && 0 === $picker::valid_id( 2 ) && 0 === $picker::valid_id( 0 ), 'فقط شناسه‌ی نوع مجاز پذیرفته می‌شود' );
mc_ok( 6 === $picker::valid_id( 6, array( 'movie', 'episode' ) ) && 0 === $picker::valid_id( 6, array( 'movie' ) ), 'فهرست نوع‌های مجاز قابل تعیین است' );

/* ---- ۴. نشانی ویدئوی کانال ---- */
echo "\n[نشانی ویدئوی کانال]\n";
mc_ok( 'https://live.example.test/stream.m3u8' === $channel::clean_video( 'https://live.example.test/stream.m3u8' ), 'نشانی مطلق https پذیرفته می‌شود' );
mc_ok( '' === $channel::clean_video( 'javascript:alert(1)' ), 'نشانی javascript رد می‌شود' );
mc_ok( '' === $channel::clean_video( '/uploads/live.mp4' ), 'نشانی نسبی رد می‌شود' );
mc_ok( '' === $channel::clean_video( 'ftp://files.example.test/a.mp4' ), 'پروتکل غیر http(s) رد می‌شود' );
mc_ok( '' === $channel::clean_video( '   ' ), 'ورودی خالی تهی می‌ماند' );

/* ---- ۵. اثر در حال پخش ---- */
echo "\n[اثر در حال پخش]\n";
$now = $channel::now_playing( 11 );
mc_ok( is_array( $now ) && 3 === $now['id'] && 'Beta Series' === $now['title'], 'اثر منتشرشده‌ی کانال نمایش داده می‌شود' );
mc_ok( null === $channel::now_playing( 999 ), 'کانال بدون اثر، اثر ندارد' );

mc_post( 12, 'channel', 'Channel Twelve', 'publish', array( 'manacore_channel_now' => 4 ) );
mc_ok( null === $channel::now_playing( 12 ) && null !== $channel::now_work( 12 ), 'اثر پیش‌نویس در نمایش عمومی نیست ولی در پیشخوان دیده می‌شود' );

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo 'موفق: ' . $GLOBALS['mc_pass'] . '   ناموفق: ' . $GLOBALS['mc_fail'] . "\n";
echo str_repeat( '=', 58 ) . "\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
