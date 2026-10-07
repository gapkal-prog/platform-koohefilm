<?php
/**
 * آزمون سمت سرور برای «مدیریت لینک‌ها از فهرست پیشخوان».
 *
 * چرا لازم است؟ ستون، پالایه و پیوند سریعِ لینک‌ها تنها راهِ دیدنِ وضعیت
 * لینکِ ده‌ها قسمت از فهرست است؛ اگر کوئری پالایه یا محتوای ستون خراب شود،
 * مدیر بدون هیچ پیامی فهرست نادرست می‌بیند (یا فیلتر بی‌اثر می‌ماند).
 *
 * اجرا: php wp/tests/test-links-admin.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.1.0' );
define( 'OBJECT', 'OBJECT' );

/* ---------------------------------------------------------------
 * چاپ
 * ------------------------------------------------------------ */

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
 * پوسته‌ی وردپرس (کمینه)
 * ------------------------------------------------------------ */

class WP_Post {
	public $ID        = 0;
	public $post_type = 'post';

	public function __construct( $id = 0, $type = 'post' ) {
		$this->ID        = (int) $id;
		$this->post_type = (string) $type;
	}
}

class WP_Query {
	public $vars    = array();
	public $is_main = true;

	public function __construct( $vars = array() ) {
		$this->vars = (array) $vars;
	}

	public function is_main_query() {
		return (bool) $this->is_main;
	}

	public function get( $key, $default = '' ) {
		return array_key_exists( $key, $this->vars ) ? $this->vars[ $key ] : $default;
	}

	public function set( $key, $value ) {
		$this->vars[ $key ] = $value;
	}
}

$GLOBALS['mc_hooks']    = array();
$GLOBALS['mc_is_admin'] = true;
$GLOBALS['mc_can_edit'] = true;

function mc_hook( $kind, $tag ) {
	$GLOBALS['mc_hooks'][] = $kind . ':' . $tag;
}

function add_filter( $tag, $cb = null, $p = 10, $a = 1 ) { mc_hook( 'filter', $tag ); }
function add_action( $tag, $cb = null, $p = 10, $a = 1 ) { mc_hook( 'action', $tag ); }
function mc_hooked( $kind, $tag ) { return in_array( $kind . ':' . $tag, $GLOBALS['mc_hooks'], true ); }

function is_admin() { return (bool) $GLOBALS['mc_is_admin']; }
function current_user_can( $cap, $id = 0 ) { return (bool) $GLOBALS['mc_can_edit']; }
function apply_filters( $tag, $value ) { return $value; }
function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_html_e( $t, $d = '' ) { echo $t; }
function esc_attr__( $t, $d = '' ) { return $t; }
function _n( $s, $p, $n, $d = '' ) { return 1 === (int) $n ? $s : $p; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return (string) $v; }
function esc_url_raw( $v ) { return (string) $v; }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function wp_unslash( $v ) { return $v; }
function wp_rand( $min = 0, $max = 0 ) { return 12345; }
function selected( $a, $b = true, $echo = true ) {
	$out = (string) $a === (string) $b ? " selected='selected'" : '';
	if ( $echo ) {
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	return $out;
}
function get_edit_post_link( $id = 0, $context = 'display' ) {
	return 999 === (int) $id ? '' : 'http://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit';
}

/* ---- مخزن پست/متا ---- */
$GLOBALS['mc_posts'] = array();

function mc_post( $id, $type, $meta = array() ) {
	$GLOBALS['mc_posts'][ $id ] = array( 'type' => $type, 'meta' => $meta );
}

function mc_links( $groups ) {
	return wp_json_encode( $groups );
}

function get_the_title( $id = 0 ) {
	/* مثل وردپرس: هم شناسه و هم شیء پست را می‌پذیرد. */
	return 'عنوان ' . ( is_object( $id ) ? (int) $id->ID : (int) $id );
}

function get_post_type( $post = 0 ) {
	$id = is_object( $post ) ? (int) $post->ID : (int) $post;
	return $GLOBALS['mc_posts'][ $id ]['type'] ?? '';
}

function get_post_meta( $id, $key = '', $single = false ) {
	$meta = $GLOBALS['mc_posts'][ (int) $id ]['meta'] ?? array();
	return $meta[ $key ] ?? ( $single ? '' : array() );
}

function get_posts( $args = array() ) {
	$out = array();

	foreach ( $GLOBALS['mc_posts'] as $id => $post ) {
		if ( ( $args['post_type'] ?? '' ) && $post['type'] !== $args['post_type'] ) {
			continue;
		}

		$match = true;

		foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
			if ( ! isset( $clause['key'] ) ) {
				continue;
			}

			$value = $post['meta'][ $clause['key'] ] ?? null;

			if ( 'EXISTS' === ( $clause['compare'] ?? '' ) ) {
				if ( null === $value ) {
					$match = false;
					break;
				}
				continue;
			}

			if ( (string) $value !== (string) ( $clause['value'] ?? '' ) ) {
				$match = false;
				break;
			}
		}

		if ( ! $match ) {
			continue;
		}

		$obj     = new WP_Post( $id, $post['type'] );
		$out[]   = $obj;
	}

	return $out;
}

/* ---------------------------------------------------------------
 * بارگذاری افزونه
 * ------------------------------------------------------------ */

require_once MANACORE_PATH . 'includes/functions.php';
require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-links.php';
require_once MANACORE_PATH . 'includes/class-links-admin.php';

use ManaCore\Core\Links;
use ManaCore\Core\Links_Admin;

/* ---------------------------------------------------------------
 * داده‌ی آزمون
 * ------------------------------------------------------------ */

$three_links = array(
	array(
		'id'      => 'g1',
		'title'   => 'کیفیت بالا',
		'season'  => 0,
		'quality' => '1080p',
		'items'   => array(
			array( 'id' => 'i1', 'url' => 'https://example.test/a.mp4', 'type' => 'direct' ),
			array( 'id' => 'i2', 'url' => 'https://example.test/b.mp4', 'type' => 'direct' ),
		),
	),
	array(
		'id'      => 'g2',
		'title'   => 'کیفیت کم',
		'season'  => 0,
		'quality' => '480p',
		'items'   => array(
			array( 'id' => 'i3', 'url' => 'https://example.test/c.mp4', 'type' => 'direct' ),
		),
	),
);

$episode_links = array(
	array(
		'id'      => 'ep',
		'title'   => 'قسمت اول',
		'season'  => 1,
		'quality' => '720p',
		'items'   => array(
			array( 'id' => 'e1', 'url' => 'https://example.test/e1.mp4', 'type' => 'direct' ),
		),
	),
);

mc_post( 4, 'movie', array( 'manacore_links' => mc_links( $three_links ) ) );
mc_post( 5, 'movie', array() );
mc_post( 8, 'series', array( 'manacore_links' => mc_links( $episode_links ) ) );
mc_post( 9, 'series', array() );
mc_post( 11, 'episode', array(
	'manacore_parent_title'   => 8,
	'manacore_season_number'  => 1,
	'manacore_episode_number' => 1,
	'manacore_links'          => mc_links( $episode_links ),
) );
mc_post( 999, 'movie', array( 'manacore_links' => mc_links( $three_links ) ) );

/* ---------------------------------------------------------------
 * ۱) انواع محتوا و ستون‌ها
 * ------------------------------------------------------------ */

echo "\n=== ۱) انواع محتوا و ستون‌ها ===\n";

$types = Links_Admin::post_types();
mc_ok( array( 'movie', 'series', 'anime', 'episode' ) === $types, 'انواع محتوای هدف، همان چهار نوع افزونه است', implode( ',', $types ) );

$columns = Links_Admin::instance()->columns( array( 'cb' => 'cb', 'title' => 'عنوان', 'date' => 'تاریخ' ) );
mc_ok( isset( $columns['manacore_links'] ), 'ستون «لینک‌ها» به فهرست اضافه می‌شود' );
mc_ok( array( 'cb', 'title', 'manacore_links', 'date' ) === array_keys( $columns ), 'ستون لینک پیش از تاریخ می‌آید و ترتیب دیگر ستون‌ها می‌ماند' );

$no_date = Links_Admin::instance()->columns( array( 'cb' => 'cb', 'title' => 'عنوان' ) );
mc_ok( isset( $no_date['manacore_links'] ) && ! isset( $no_date['date'] ), 'در نبود ستون تاریخ، ستون لینک بدون خطا اضافه می‌شود' );

/* ---------------------------------------------------------------
 * ۲) محتوای ستون
 * ------------------------------------------------------------ */

echo "\n=== ۲) محتوای ستون لینک‌ها ===\n";

$admin = Links_Admin::instance();

ob_start();
$admin->column_content( Links_Admin::COLUMN, 4 );
$cell = (string) ob_get_clean();

mc_ok( false !== strpos( $cell, '۳ لینک' ), 'شمارش لینک‌های اثر با ارقام فارسی نشان داده می‌شود' );
mc_ok( false !== strpos( $cell, 'is-ok' ), 'اثر لینک‌دار نشان «سالم» می‌گیرد' );
mc_ok( false !== strpos( $cell, '#manacore-links' ), 'پیوند ستون به متاباکس لینک‌ها می‌رود' );
mc_ok( false === strpos( $cell, 'بدون لینک' ), 'برای اثر لینک‌دار، هشدار «بدون لینک» نمی‌آید' );

ob_start();
$admin->column_content( Links_Admin::COLUMN, 5 );
$empty_cell = (string) ob_get_clean();

mc_ok( false !== strpos( $empty_cell, 'بدون لینک' ), 'اثر بدون لینک هشدار می‌گیرد' );
mc_ok( false !== strpos( $empty_cell, 'افزودن لینک' ), 'برای اثر بدون لینک، پیوند «افزودن لینک» می‌آید' );
mc_ok( false !== strpos( $empty_cell, 'is-empty' ), 'وضعیت خالی نشان مخصوص خودش را دارد' );

ob_start();
$admin->column_content( Links_Admin::COLUMN, 8 );
$series_cell = (string) ob_get_clean();

mc_ok( false !== strpos( $series_cell, 'در قسمت‌ها' ), 'در سریال‌ها، لینک‌های قسمت‌ها هم شمرده می‌شوند' );
mc_ok( false !== strpos( $series_cell, 'is-ok' ), 'سریالی که فقط قسمت‌هایش لینک دارند «بدون لینک» شمرده نمی‌شود' );

ob_start();
$admin->column_content( Links_Admin::COLUMN, 9 );
$plain_series = (string) ob_get_clean();

mc_ok( false !== strpos( $plain_series, 'بدون لینک' ), 'سریال با قسمت‌های بی‌لینک هشدار می‌گیرد' );

ob_start();
$admin->column_content( 'title', 4 );
mc_ok( '' === (string) ob_get_clean(), 'ستون‌های دیگر دست‌نخورده می‌مانند' );

mc_ok( false !== strpos( Links_Admin::links_url( 4 ), '#manacore-links' ), 'نشانی لینک‌ها به لنگر متاباکس ختم می‌شود' );
mc_ok( '' === Links_Admin::links_url( 999 ), 'در نبود نشانی ویرایش، رشته‌ی خالی می‌دهد' );

/* ---------------------------------------------------------------
 * ۳) پالایه‌ی وضعیت لینک
 * ------------------------------------------------------------ */

echo "\n=== ۳) پالایه‌ی وضعیت لینک ===\n";

ob_start();
$admin->filter_dropdown( 'movie' );
$dropdown = (string) ob_get_clean();

mc_ok( false !== strpos( $dropdown, 'همه‌ی وضعیت‌های لینک' ), 'گزینه‌ی «همه» در پالایه هست' );
mc_ok( false !== strpos( $dropdown, 'دارای لینک' ) && false !== strpos( $dropdown, 'بدون لینک' ), 'دو گزینه‌ی وضعیت در پالایه هست' );
mc_ok( false !== strpos( $dropdown, 'manacore_link_state' ), 'نام فیلد پالایه با ثابت کلاس یکی است' );

ob_start();
$admin->filter_dropdown( 'post' );
mc_ok( '' === (string) ob_get_clean(), 'برای انواع محتوای بی‌ربط پالایه‌ای چاپ نمی‌شود' );

$_GET['manacore_link_state'] = 'no';
ob_start();
$admin->filter_dropdown( 'episode' );
$selected_dropdown = (string) ob_get_clean();
unset( $_GET['manacore_link_state'] );

mc_ok( false !== strpos( $selected_dropdown, "selected='selected'" ), 'وضعیت انتخاب‌شده در پالایه پایدار می‌ماند' );

$GLOBALS['mc_is_admin'] = true;

$query = new WP_Query( array( 'post_type' => 'movie' ) );
$_GET['manacore_link_state'] = 'yes';
$admin->apply_filter( $query );
$clause = (array) $query->get( 'meta_query' );

mc_ok( isset( $clause[0]['key'] ) && 'manacore_links' === $clause[0]['key'], 'پالایه روی متای لینک‌ها می‌نشیند' );
mc_ok( 'EXISTS' === $clause[0]['compare'], 'وضعیت «دارای لینک» با EXISTS ساخته می‌شود' );

$query = new WP_Query( array( 'post_type' => 'episode' ) );
$_GET['manacore_link_state'] = 'no';
$admin->apply_filter( $query );
$clause = (array) $query->get( 'meta_query' );
unset( $_GET['manacore_link_state'] );

mc_ok( 'NOT EXISTS' === $clause[0]['compare'], 'وضعیت «بدون لینک» با NOT EXISTS ساخته می‌شود' );

$query = new WP_Query( array( 'post_type' => 'movie' ) );
$admin->apply_filter( $query );
mc_ok( '' === $query->get( 'meta_query' ), 'بدون انتخاب وضعیت، کوئری دست‌نخورده می‌ماند' );

$_GET['manacore_link_state'] = 'yes';
$query = new WP_Query( array( 'post_type' => 'page' ) );
$admin->apply_filter( $query );
mc_ok( '' === $query->get( 'meta_query' ), 'روی انواع محتوای دیگر پالایه اعمال نمی‌شود' );

$query = new WP_Query( array( 'post_type' => 'movie' ) );
$query->is_main = false;
$admin->apply_filter( $query );
mc_ok( '' === $query->get( 'meta_query' ), 'کوئری‌های فرعی دست‌نخورده می‌مانند' );

$GLOBALS['mc_is_admin'] = false;
$query = new WP_Query( array( 'post_type' => 'movie' ) );
$admin->apply_filter( $query );
mc_ok( '' === $query->get( 'meta_query' ), 'بیرون از پیشخوان پالایه اعمال نمی‌شود' );
$GLOBALS['mc_is_admin'] = true;

$query = new WP_Query( array( 'post_type' => 'movie' ) );
$query->set( 'meta_query', array( array( 'key' => 'manacore_year' ) ) );
$admin->apply_filter( $query );
$clauses = (array) $query->get( 'meta_query' );
unset( $_GET['manacore_link_state'] );

mc_ok( isset( $clauses[0]['key'] ) && 'manacore_year' === $clauses[0]['key'], 'پالایه، شرط‌های موجود کوئری را پاک نمی‌کند' );
mc_ok( isset( $clauses[1]['key'] ) && 'manacore_links' === $clauses[1]['key'], 'شرط لینک بعد از شرط‌های موجود اضافه می‌شود' );
mc_ok( 'AND' === ( $clauses['relation'] ?? '' ), 'دو شرط فراداده‌ای با رابطه‌ی AND ترکیب می‌شوند' );

/* ---------------------------------------------------------------
 * ۴) پیوند سریع ردیف
 * ------------------------------------------------------------ */

echo "\n=== ۴) پیوند سریع «لینک‌ها» در ردیف ===\n";

$actions = $admin->row_actions( array( 'edit' => '<a>ویرایش</a>' ), new WP_Post( 4, 'movie' ) );
mc_ok( isset( $actions['manacore_links'] ) && false !== strpos( $actions['manacore_links'], '#manacore-links' ), 'ردیف اثر، پیوند سریع «لینک‌ها» می‌گیرد' );
mc_ok( isset( $actions['edit'] ), 'پیوندهای دیگر ردیف حفظ می‌شوند' );

$GLOBALS['mc_can_edit'] = false;
$actions = $admin->row_actions( array(), new WP_Post( 4, 'movie' ) );
mc_ok( ! isset( $actions['manacore_links'] ), 'بی‌دسترسی، پیوند سریع ساخته نمی‌شود' );
$GLOBALS['mc_can_edit'] = true;

$actions = $admin->row_actions( array(), new WP_Post( 1, 'page' ) );
mc_ok( ! isset( $actions['manacore_links'] ), 'برای برگه‌ها پیوند سریع لینک ساخته نمی‌شود' );

mc_ok( ! isset( $admin->row_actions( array(), null )['manacore_links'] ), 'ورودی نامعتبر خطا نمی‌دهد' );

/* ---------------------------------------------------------------
 * ۵) اتصال قلاب‌ها
 * ------------------------------------------------------------ */

echo "\n=== ۵) اتصال قلاب‌ها ===\n";

$admin->hooks();

/*
 * ثبت ستون‌ها به `init` سپرده شده است: `self::post_types()` از فهرست
 * نوع‌های محتوا می‌آید که برچسب‌هایش ترجمه‌پذیر است و صدا زدن ترجمه پیش
 * از `after_setup_theme` هشدار `_load_textdomain_just_in_time` می‌دهد.
 */
mc_ok( mc_hooked( 'action', 'init' ), 'ثبت قلاب‌ها روی init انجام می‌شود (نه در بارگذاری افزونه)' );

$admin->register();

foreach ( array( 'movie', 'series', 'anime', 'episode' ) as $type ) {
	mc_ok( mc_hooked( 'filter', 'manage_' . $type . '_posts_columns' ), "ستون فهرست {$type} ثبت می‌شود" );
	mc_ok( mc_hooked( 'action', 'manage_' . $type . '_posts_custom_column' ), "رندر ستون فهرست {$type} ثبت می‌شود" );
}

mc_ok( mc_hooked( 'action', 'restrict_manage_posts' ), 'پالایه‌ی بالای فهرست ثبت می‌شود' );
mc_ok( mc_hooked( 'action', 'pre_get_posts' ), 'اعمال پالایه روی کوئری ثبت می‌شود' );
mc_ok( mc_hooked( 'filter', 'post_row_actions' ), 'پیوند سریع ردیف ثبت می‌شود' );

/* ---------------------------------------------------------------
 * پایان
 * ------------------------------------------------------------ */

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo 'موفق: ' . $GLOBALS['mc_pass'] . '   ناموفق: ' . $GLOBALS['mc_fail'] . "\n";
echo str_repeat( '=', 58 ) . "\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
