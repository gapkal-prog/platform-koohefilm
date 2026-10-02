<?php
/**
 * آزمون سمت سرور برای موتور نمایش شرطی و سازنده‌ی کوئری.
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_URL', 'http://example.test/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );

/* ---- وضعیت قابل تنظیم آزمون ---- */
$GLOBALS['mc_state'] = array(
	'is_admin'    => false,
	'singular'    => false,
	'post_type'   => '',
	'post_id'     => 0,
	'archive'     => false,
	'tax'         => false,
	'category'    => false,
	'tag'         => false,
	'home'        => false,
	'front'       => false,
	'search'      => false,
	'author'      => false,
	'404'         => false,
	'logged_in'   => false,
	'roles'       => array(),
	'terms'       => array(),
	'queried'     => null,
	'query_vars'  => array(),
	'meta'        => array(),
	'sub_level'   => '',
	'links'       => 0,
);
function mc_s( $k ) { return $GLOBALS['mc_state'][ $k ]; }

/* ---- توابع پایه ---- */
function __( $t, $d = '' ) { return $t; }
function _x( $t, $c = '', $d = '' ) { return $t; }
function apply_filters( $tag, $value ) { return $value; }
function add_filter() {} function add_action() {}
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_\- ]/', '', (string) $v ); }
function is_wp_error( $v ) { return false; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE ); }
function get_option( $k, $d = false ) { return $d; }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function taxonomy_exists( $t ) { return in_array( $t, array( 'genre', 'country', 'release_year', 'network', 'studio', 'quality', 'language', 'category', 'post_tag' ), true ); }
function term_exists( $t, $tax = '' ) { return true; }
function get_term_by( $f, $v, $tax = '' ) { return false; }
function sanitize_title( $v ) { return strtolower( trim( preg_replace( '/[\s_]+/', '-', (string) $v ) ) ); }
function get_userdata( $id ) { return false; }
function wp_list_pluck( $list, $field ) { $o = array(); foreach ( (array) $list as $i ) { $o[] = is_object( $i ) ? $i->$field : $i[ $field ]; } return $o; }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return (string) $v; }
function translate_user_role( $r ) { return $r; }
function wp_roles() { return new class { public function get_names() { return array( 'administrator' => 'Admin' ); } }; }
function get_post_types( $a = array(), $o = 'names' ) { return array(); }
function get_taxonomies( $a = array(), $o = 'names' ) { return array(); }
function get_terms( $a = array() ) { return array(); }

/* ---- شرط‌های وردپرس ---- */
function is_admin() { return mc_s( 'is_admin' ); }
function is_singular( $t = '' ) {
	if ( ! mc_s( 'singular' ) ) { return false; }
	if ( '' === $t ) { return true; }
	return in_array( mc_s( 'post_type' ), (array) $t, true );
}
function is_archive() { return mc_s( 'archive' ); }
function is_home() { return mc_s( 'home' ); }
function is_front_page() { return mc_s( 'front' ); }
function is_search() { return mc_s( 'search' ); }
function is_author() { return mc_s( 'author' ); }
function is_404() { return mc_s( '404' ); }
function is_category() { return mc_s( 'category' ); }
function is_tag() { return mc_s( 'tag' ); }
function is_tax( $t = '' ) { return mc_s( 'tax' ); }
function is_user_logged_in() { return mc_s( 'logged_in' ); }
function get_the_ID() { return mc_s( 'post_id' ) ?: false; }
function get_post_type( $p = null ) { return mc_s( 'post_type' ) ?: false; }
function get_queried_object() { return mc_s( 'queried' ); }
function get_query_var( $k, $d = '' ) { $v = mc_s( 'query_vars' ); return isset( $v[ $k ] ) ? $v[ $k ] : $d; }
function get_post_meta( $id, $k = '', $s = false ) { $m = mc_s( 'meta' ); return isset( $m[ $k ] ) ? $m[ $k ] : ( $s ? '' : array() ); }
function current_time( $f ) { return 'timestamp' === $f ? time() : date( 'Y-m-d' === $f ? 'Y-m-d' : 'Y-m-d H:i:s' ); }
function wp_unslash( $v ) { return $v; }
function post_type_exists( $t ) { return in_array( $t, array( 'post', 'page', 'movie', 'series', 'anime', 'episode', 'person', 'collection' ), true ); }
function get_post_stati() { return array( 'publish' => 'publish', 'draft' => 'draft' ); }
function current_user_can( $c ) { return in_array( 'administrator', mc_s( 'roles' ), true ); }
function wp_get_current_user() {
	return new class { public $roles;
		public function __construct() { $this->roles = $GLOBALS['mc_state']['roles']; } };
}
function has_term( $terms, $tax, $post = null ) {
	$all = mc_s( 'terms' );
	if ( empty( $all[ $tax ] ) ) { return false; }
	foreach ( (array) $terms as $t ) { if ( in_array( $t, $all[ $tax ], true ) ) { return true; } }
	return false;
}
function manacore_subs_user_level() { return $GLOBALS['mc_state']['sub_level']; }
function manacore_subs_is_subscriber() { return '' !== $GLOBALS['mc_state']['sub_level']; }

spl_autoload_register( function ( $class ) {
	if ( 0 !== strpos( $class, 'ManaCore\\Core\\' ) ) { return; }
	$rel = strtolower( str_replace( array( 'ManaCore\\Core\\', '\\', '_' ), array( '', '/', '-' ), $class ) );
	foreach ( array( 'class-', 'trait-', 'interface-' ) as $p ) {
		$f = MANACORE_PATH . 'includes/' . $p . $rel . '.php';
		if ( file_exists( $f ) ) { require_once $f; return; }
	}
} );
require_once MANACORE_PATH . 'includes/functions.php';

use ManaCore\Core\Block_Visibility as V;
use ManaCore\Core\Block_Query as Q;

$pass = 0; $fail = 0;
function t( $label, $got, $want ) {
	global $pass, $fail;
	if ( $got === $want ) { $pass++; printf( "  \xE2\x9C\x93 %s\n", $label ); }
	else { $fail++; printf( "  \xE2\x9C\x97 %s  (got %s, want %s)\n", $label, var_export( $got, true ), var_export( $want, true ) ); }
}
function st( $patch ) { $GLOBALS['mc_state'] = array_merge( $GLOBALS['mc_state'], $patch ); }
function vis( $rules, $action = 'show', $relation = 'AND' ) {
	return array( 'visibility' => array( 'enabled' => true, 'action' => $action, 'relation' => $relation, 'rules' => $rules, 'devices' => array() ) );
}

echo "=== 1. غیرفعال / بدون قاعده ===\n";
t( 'disabled -> render', V::should_render( array() ), true );
t( 'enabled but no rules -> render', V::should_render( vis( array() ) ), true );

echo "\n=== 2. قاعده‌ی نوع محتوا (post_type) ===\n";
st( array( 'is_admin' => false, 'singular' => true, 'post_type' => 'movie', 'post_id' => 5 ) );
t( 'movie page, rule=[movie] show', V::should_render( vis( array( array( 'type' => 'post_type', 'operator' => 'is', 'values' => array( 'movie' ) ) ) ) ), true );
t( 'movie page, rule=[series] show', V::should_render( vis( array( array( 'type' => 'post_type', 'operator' => 'is', 'values' => array( 'series' ) ) ) ) ), false );
t( 'movie page, rule=[movie] HIDE', V::should_render( vis( array( array( 'type' => 'post_type', 'operator' => 'is', 'values' => array( 'movie' ) ) ), 'hide' ) ), false );
t( 'movie page, is_not [movie]', V::should_render( vis( array( array( 'type' => 'post_type', 'operator' => 'is_not', 'values' => array( 'movie' ) ) ) ) ), false );

echo "\n=== 3. قاعده‌ی تاکسونومی (دسته/برچسب/ژانر) ===\n";
st( array( 'terms' => array( 'genre' => array( 'action', 'drama' ), 'category' => array( 'news' ) ) ) );
t( 'has genre=action', V::should_render( vis( array( array( 'type' => 'taxonomy', 'operator' => 'is', 'taxonomy' => 'genre', 'values' => array( 'action' ) ) ) ) ), true );
t( 'has genre=comedy', V::should_render( vis( array( array( 'type' => 'taxonomy', 'operator' => 'is', 'taxonomy' => 'genre', 'values' => array( 'comedy' ) ) ) ) ), false );
t( 'has category=news', V::should_render( vis( array( array( 'type' => 'taxonomy', 'operator' => 'is', 'taxonomy' => 'category', 'values' => array( 'news' ) ) ) ) ), true );

echo "\n=== 4. زمینه‌ی صفحه (context) ===\n";
t( 'singular context', V::should_render( vis( array( array( 'type' => 'context', 'operator' => 'is', 'values' => array( 'singular' ) ) ) ) ), true );
t( 'archive context on singular', V::should_render( vis( array( array( 'type' => 'context', 'operator' => 'is', 'values' => array( 'archive' ) ) ) ) ), false );
st( array( 'singular' => false, 'archive' => true, 'tax' => true, 'post_type' => '' ) );
t( 'archive context on archive', V::should_render( vis( array( array( 'type' => 'context', 'operator' => 'is', 'values' => array( 'archive' ) ) ) ) ), true );

echo "\n=== 5. ورود / نقش / اشتراک ===\n";
st( array( 'logged_in' => false, 'roles' => array() ) );
t( 'logged_out rule, guest', V::should_render( vis( array( array( 'type' => 'login', 'operator' => 'is', 'values' => array( 'logged_out' ) ) ) ) ), true );
t( 'logged_in rule, guest', V::should_render( vis( array( array( 'type' => 'login', 'operator' => 'is', 'values' => array( 'logged_in' ) ) ) ) ), false );
st( array( 'logged_in' => true, 'roles' => array( 'subscriber' ) ) );
t( 'logged_in rule, user', V::should_render( vis( array( array( 'type' => 'login', 'operator' => 'is', 'values' => array( 'logged_in' ) ) ) ) ), true );
t( 'role=subscriber', V::should_render( vis( array( array( 'type' => 'user_role', 'operator' => 'is', 'values' => array( 'subscriber' ) ) ) ) ), true );
t( 'role=editor', V::should_render( vis( array( array( 'type' => 'user_role', 'operator' => 'is', 'values' => array( 'editor' ) ) ) ) ), false );
st( array( 'sub_level' => 'vip' ) );
t( 'subscription=vip', V::should_render( vis( array( array( 'type' => 'subscription', 'operator' => 'is', 'values' => array( 'vip' ) ) ) ) ), true );
t( 'subscription=gold', V::should_render( vis( array( array( 'type' => 'subscription', 'operator' => 'is', 'values' => array( 'gold' ) ) ) ) ), false );

echo "\n=== 6. رابطه‌ی AND / OR ===\n";
st( array( 'singular' => true, 'archive' => false, 'tax' => false, 'post_type' => 'movie', 'post_id' => 5 ) );
$r_ok  = array( 'type' => 'post_type', 'operator' => 'is', 'values' => array( 'movie' ) );
$r_bad = array( 'type' => 'post_type', 'operator' => 'is', 'values' => array( 'anime' ) );
t( 'AND ok+bad', V::should_render( vis( array( $r_ok, $r_bad ), 'show', 'AND' ) ), false );
t( 'OR  ok+bad', V::should_render( vis( array( $r_ok, $r_bad ), 'show', 'OR' ) ), true );
t( 'AND ok+ok',  V::should_render( vis( array( $r_ok, $r_ok ), 'show', 'AND' ) ), true );

echo "\n=== 7. پارامتر URL و بازه‌ی تاریخ ===\n";
st( array( 'query_vars' => array( 'promo' => 'summer' ) ) );
t( 'query_var promo=summer', V::should_render( vis( array( array( 'type' => 'query_var', 'operator' => 'is', 'key' => 'promo', 'values' => array( 'summer' ) ) ) ) ), true );
t( 'query_var promo=winter', V::should_render( vis( array( array( 'type' => 'query_var', 'operator' => 'is', 'key' => 'promo', 'values' => array( 'winter' ) ) ) ) ), false );
t( 'date range covering today', V::should_render( vis( array( array( 'type' => 'date_range', 'operator' => 'is', 'from' => '2000-01-01', 'to' => '2099-12-31' ) ) ) ), true );
t( 'date range in the past', V::should_render( vis( array( array( 'type' => 'date_range', 'operator' => 'is', 'from' => '2000-01-01', 'to' => '2000-12-31' ) ) ) ), false );

echo "\n=== 8. پرمیوم / شناسه‌ی نوشته ===\n";
st( array( 'meta' => array( 'manacore_is_premium' => '1' ) ) );
t( 'premium=yes', V::should_render( vis( array( array( 'type' => 'premium', 'operator' => 'is', 'values' => array( 'yes' ) ) ) ) ), true );
t( 'premium=no', V::should_render( vis( array( array( 'type' => 'premium', 'operator' => 'is', 'values' => array( 'no' ) ) ) ) ), false );
t( 'post_ids [5]', V::should_render( vis( array( array( 'type' => 'post_ids', 'operator' => 'is', 'values' => array( 5 ) ) ) ) ), true );
t( 'post_ids [9]', V::should_render( vis( array( array( 'type' => 'post_ids', 'operator' => 'is', 'values' => array( 9 ) ) ) ) ), false );

echo "\n=== 9. ویرایشگر همیشه نمایش می‌دهد ===\n";
st( array( 'is_admin' => true ) );
t( 'admin screen ignores rules', V::should_render( vis( array( $r_bad ) ) ), true );
st( array( 'is_admin' => false ) );

echo "\n=== 10. کلاس‌های دستگاه ===\n";
t( 'no devices', V::device_classes( array() ), '' );
t( 'mobile+tablet', V::device_classes( array( 'visibility' => array( 'enabled' => true, 'devices' => array( 'mobile', 'tablet' ), 'rules' => array() ) ) ), 'manacore-hide-mobile manacore-hide-tablet' );

echo "\n=== 11. سازنده‌ی کوئری (taxQuery / فیلترها) ===\n";
$args = Q::build( array(
	'source'      => 'latest',
	'postTypes'   => array( 'movie', 'series' ),
	'count'       => 12,
	'offset'      => 3,
	'taxQuery'    => array(
		array( 'taxonomy' => 'genre', 'terms' => array( 'action', 'drama' ), 'operator' => 'IN' ),
		array( 'taxonomy' => 'country', 'terms' => array( 'usa' ), 'operator' => 'NOT IN' ),
	),
	'taxRelation' => 'AND',
	'yearFrom'    => 2020,
	'yearTo'      => 2025,
	'minRating'   => 7.5,
	'orderBy'     => 'meta_num',
	'metaOrderKey'=> 'manacore_imdb_rating',
	'order'       => 'DESC',
	'excludeIds'  => '4,7',
) );
t( 'post_type array', $args['post_type'], array( 'movie', 'series' ) );
t( 'posts_per_page', $args['posts_per_page'], 12 );
t( 'offset', $args['offset'], 3 );
t( 'tax_query count (2 rules + relation)', count( $args['tax_query'] ), 3 );
t( 'tax_query relation', $args['tax_query']['relation'], 'AND' );
t( 'tax_query[0] operator', $args['tax_query'][0]['operator'], 'IN' );
t( 'tax_query[1] operator', $args['tax_query'][1]['operator'], 'NOT IN' );
t( 'order DESC', $args['order'], 'DESC' );
t( 'excluded ids', $args['post__not_in'], array( 4, 7 ) );
t( 'meta_query present', isset( $args['meta_query'] ) || isset( $args['manacore_extra_meta_query'] ), true );

printf( "\n---------------------------------------\nPASS: %d   FAIL: %d\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
