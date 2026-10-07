<?php
/**
 * آزمون «درخواست فیلم/سریال» و پنل «درخواست‌های من».
 *
 * هر دو ماژول بدون وردپرس اجرا می‌شوند: پوسته‌های ساختگی همان چیزی را
 * برمی‌گردانند که کوئری/متا می‌دهد و همه‌ی درج/به‌روزرسانی‌ها ثبت می‌شوند
 * تا بتوان سنجید منطق درست است، ورودی کاربر پاک‌سازی می‌شود و هیچ رشته‌ی
 * خامی به SQL نمی‌رسد.
 *
 * اجرا: php wp/tests/test-community.php
 */

define( 'ABSPATH', '/tmp/fake-wp-community/' );
define( 'MANACORE_URL', 'http://example.test/plugins/manacore-core/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['mc_pass'] = 0;
$GLOBALS['mc_fail'] = 0;

function mc_ok( $ok, $label, $extra = '' ) {
	$GLOBALS[ $ok ? 'mc_pass' : 'mc_fail' ]++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $label . ( '' !== $extra ? '  — ' . $extra : '' ) . "\n";
}

/* ---------------------------------------------------------------
 * پوسته‌ی وردپرس
 * ------------------------------------------------------------ */

$GLOBALS['mc_options']   = array();
$GLOBALS['mc_meta']      = array();
$GLOBALS['mc_posts']     = array();
$GLOBALS['mc_transients'] = array();
$GLOBALS['mc_actions']   = array();
$GLOBALS['mc_user']      = 0;

function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_attr__( $t, $d = '' ) { return $t; }
function esc_html_e( $t, $d = '' ) { echo $t; }
function esc_attr_e( $t, $d = '' ) { echo $t; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_textarea( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return (string) $v; }
function esc_url_raw( $v ) { return filter_var( (string) $v, FILTER_SANITIZE_URL ); }
function apply_filters( $tag, $value ) { return $value; }
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function do_action( $tag, ...$args ) { $GLOBALS['mc_actions'][] = array( $tag, $args ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_rand( $min, $max ) { return $min; }
function sanitize_text_field( $v ) { return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $v ) ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_title( $v ) { return sanitize_key( $v ); }
function wp_unslash( $v ) { return $v; }
function current_time( $t = 'mysql' ) { return 'mysql' === $t ? '2026-10-08 12:00:00' : '2026-10-08'; }
function wp_salt( $s = '' ) { return 'test-salt'; }
function get_current_user_id() { return (int) $GLOBALS['mc_user']; }
function is_user_logged_in() { return (int) $GLOBALS['mc_user'] > 0; }
function is_admin() { return false; }
function is_feed() { return false; }
function is_singular() { return true; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
function home_url( $p = '/' ) { return 'http://example.test' . $p; }
function get_permalink( $id = 0 ) { return 'http://example.test/?p=' . (int) $id; }
function wp_login_url( $r = '' ) { return 'http://example.test/wp-login.php'; }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	return array_merge( (array) $pairs, array_intersect_key( (array) $atts, (array) $pairs ) );
}
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function wp_trim_words( $v, $n = 55 ) { return implode( ' ', array_slice( preg_split( '/\s+/u', (string) $v ), 0, $n ) ); }
function number_format_i18n( $n, $dec = 0 ) { return number_format( (float) $n, (int) $dec ); }
function manacore_fa_digits( $v ) { return (string) $v; }
function manacore_fa_date( $v ) { return '۱۴۰۵/۰۷/۱۶'; }
function wp_die( $m, $c = 0 ) { throw new RuntimeException( (string) $m ); }
function wp_safe_redirect( $u ) { $GLOBALS['mc_redirect'] = $u; }
function check_admin_referer( $a = '' ) { return true; }
function current_user_can( $c, $id = 0 ) { return true; }
function wp_nonce_url( $u, $a = '' ) { return $u . '&_wpnonce=' . $a; }

/**
 * پوسته‌ی WP_Error: کلاس واقعی وردپرس در این محیط نیست و ماژول‌ها برای
 * پیام خطای فارسی به آن تکیه می‌کنند.
 */
class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags | JSON_UNESCAPED_UNICODE ); }

function manacore_get_option( $key, $default = '' ) {
	$options = get_option( 'manacore_settings', array() );

	return isset( $options[ $key ] ) && '' !== $options[ $key ] ? $options[ $key ] : $default;
}

function manacore_post_types() {
	return array( 'movie' => 'فیلم', 'series' => 'سریال', 'anime' => 'انیمه', 'episode' => 'قسمت' );
}

function manacore_title_post_types() {
	return array( 'movie', 'series', 'anime' );
}

function manacore_serial_post_types() {
	return array( 'series', 'anime' );
}

function manacore_visitor_hash( $context = 'visitor' ) {
	$user_id = get_current_user_id();

	if ( $user_id ) {
		return 'u' . $user_id;
	}

	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';

	return 'g' . substr( hash( 'sha256', $context . '|' . $ip . wp_salt( 'nonce' ) ), 0, 32 );
}

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['mc_options'] ) ? $GLOBALS['mc_options'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['mc_options'][ $k ] = $v; return true; }
function get_transient( $k ) { return isset( $GLOBALS['mc_transients'][ $k ] ) ? $GLOBALS['mc_transients'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['mc_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['mc_transients'][ $k ] ); }

function get_post_meta( $id, $key, $single = false ) {
	$value = isset( $GLOBALS['mc_meta'][ $id ][ $key ] ) ? $GLOBALS['mc_meta'][ $id ][ $key ] : '';

	if ( $single ) {
		return is_array( $value ) ? $value : $value;
	}

	return '' === $value ? array() : array( $value );
}

function update_post_meta( $id, $key, $value ) { $GLOBALS['mc_meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['mc_meta'][ $id ][ $key ] ); return true; }

function get_post( $id ) {
	$id = (int) $id;

	return isset( $GLOBALS['mc_posts'][ $id ] ) ? $GLOBALS['mc_posts'][ $id ] : null;
}

function get_post_type( $id ) {
	$post = get_post( $id );

	return $post ? $post->post_type : false;
}

function get_post_status( $id ) {
	$post = get_post( $id );

	return $post ? $post->post_status : false;
}

function get_the_title( $post = 0 ) {
	if ( is_object( $post ) ) {
		return isset( $post->post_title ) ? $post->post_title : '';
	}

	$found = get_post( $post );

	return $found ? $found->post_title : '';
}
function get_post_field( $field, $id ) { return 'menu_order' === $field ? (int) get_post_meta( $id, 'mc_menu_order', true ) : ''; }
function get_the_post_thumbnail_url( $id, $size = '' ) { return (string) get_post_meta( $id, 'mc_thumb', true ); }
function get_edit_post_link( $id ) { return 'http://example.test/wp-admin/post.php?post=' . (int) $id; }
function wp_insert_post( $data, $wp_error = false ) {
	$id = isset( $GLOBALS['mc_next_id'] ) ? $GLOBALS['mc_next_id'] : 500;
	$GLOBALS['mc_next_id'] = $id + 1;

	$post              = new stdClass();
	$post->ID          = $id;
	$post->post_type   = isset( $data['post_type'] ) ? $data['post_type'] : 'post';
	$post->post_title  = isset( $data['post_title'] ) ? $data['post_title'] : '';
	$post->post_status = isset( $data['post_status'] ) ? $data['post_status'] : 'draft';
	$post->post_name   = isset( $data['post_name'] ) ? $data['post_name'] : '';
	$post->post_content= isset( $data['post_content'] ) ? $data['post_content'] : '';

	$GLOBALS['mc_posts'][ $id ] = $post;
	$GLOBALS['mc_inserted'][]   = $data;

	if ( ! empty( $data['meta_input'] ) ) {
		foreach ( $data['meta_input'] as $key => $value ) {
			$GLOBALS['mc_meta'][ $id ][ $key ] = $value;
		}
	}

	return $id;
}

function wp_update_post( $data ) {
	if ( isset( $GLOBALS['mc_posts'][ (int) $data['ID'] ] ) && isset( $data['post_status'] ) ) {
		$GLOBALS['mc_posts'][ (int) $data['ID'] ]->post_status = $data['post_status'];
	}

	return (int) $data['ID'];
}

function wp_count_posts( $type ) {
	$counts = (object) array( 'publish' => 0, 'pending' => 0, 'draft' => 0, 'trash' => 0 );

	foreach ( $GLOBALS['mc_posts'] as $post ) {
		if ( $type === $post->post_type && isset( $counts->{ $post->post_status } ) ) {
			$counts->{ $post->post_status }++;
		}
	}

	return $counts;
}

function get_page_by_path( $path, $output = OBJECT, $type = 'page' ) {
	foreach ( $GLOBALS['mc_posts'] as $post ) {
		if ( $type === $post->post_type && $path === $post->post_name ) {
			return $post;
		}
	}

	return null;
}

function get_posts( $args ) {
	$type    = isset( $args['post_type'] ) ? $args['post_type'] : 'post';
	$status  = isset( $args['post_status'] ) ? (array) $args['post_status'] : array( 'publish' );
	$matched = array();

	foreach ( $GLOBALS['mc_posts'] as $id => $post ) {
		if ( $type !== $post->post_type || ! in_array( $post->post_status, $status, true ) ) {
			continue;
		}

		if ( isset( $args['meta_query'] ) ) {
			$ok = true;

			foreach ( $args['meta_query'] as $clause ) {
				if ( (string) get_post_meta( $id, $clause['key'], true ) !== (string) $clause['value'] ) {
					$ok = false;
				}
			}

			if ( ! $ok ) {
				continue;
			}
		}

		$matched[] = isset( $args['fields'] ) && 'ids' === $args['fields'] ? $id : $post;
	}

	return $matched;
}

/**
 * WP_Query ساختگی: آنچه در `$GLOBALS['mc_query']` بگذاریم برمی‌گرداند.
 */
class WP_Query {
	public $posts       = array();
	public $found_posts = 0;
	public $args        = array();
	private $index      = 0;

	public function __construct( $args = array() ) {
		$this->args = $args;
		$GLOBALS['mc_queries'][] = $args;

		$fixture = isset( $GLOBALS['mc_query'] ) ? $GLOBALS['mc_query'] : array();

		$this->posts       = isset( $fixture['posts'] ) ? $fixture['posts'] : array();
		$this->found_posts = isset( $fixture['found'] ) ? $fixture['found'] : count( $this->posts );
	}

	public function have_posts() { return $this->index < count( $this->posts ); }

	public function the_post() {
		$post = $this->posts[ $this->index ];
		$this->index++;
		$GLOBALS['mc_current_post'] = $post;
	}

	public function reset_postdata() {}
}

function get_the_ID() { return isset( $GLOBALS['mc_current_post'] ) ? $GLOBALS['mc_current_post']->ID : 0; }
function the_title() { echo esc_html( get_the_title( get_the_ID() ) ); }
function get_the_content() { $p = get_post( get_the_ID() ); return $p ? $p->post_content : ''; }
function get_the_date( $f = '' ) { return '2026-10-01 09:00:00'; }
function wp_reset_postdata() {}

class MC_Wpdb {
	public $prefix    = 'wp_';
	public $insert_id = 41;
	public $queries   = array();
	public $inserts   = array();
	public $deletes   = array();
	public $rows      = array();
	public $var       = null;

	public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$this->queries[] = array( 'sql' => $query, 'args' => $args );

		return $query;
	}
	public function get_var( $query, $x = 0, $y = 0 ) { $this->queries[] = array( 'sql' => $query, 'args' => array() ); return $this->var; }
	public function get_results( $query, $mode = OBJECT ) { return $this->rows; }
	public function insert( $table, $data, $format = null ) {
		if ( $GLOBALS['mc_duplicate_insert'] ) {
			return false;
		}

		$this->inserts[] = array( 'table' => $table, 'data' => $data, 'format' => $format );
		return 1;
	}
	public function delete( $table, $where, $wf = null ) { $this->deletes[] = array( 'table' => $table, 'where' => $where ); return 1; }
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
}

$GLOBALS['mc_duplicate_insert'] = false;
$GLOBALS['wpdb']                = new MC_Wpdb();

/* ---------------------------------------------------------------
 * بارگذاری کلاس‌ها
 * ------------------------------------------------------------ */

require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-install.php';
require_once MANACORE_PATH . 'includes/class-requests.php';

/* ---------------------------------------------------------------
 * ۱) نوع محتوا و تنظیمات
 * ------------------------------------------------------------ */

echo "\n=== ۱) نوع محتوا و تنظیمات ===\n";

mc_ok( class_exists( 'ManaCore\\Core\\Requests' ), 'کلاس درخواست‌ها بارگذاری شد' );
mc_ok( 'manacore_request' === ManaCore\Core\Requests::POST_TYPE, 'نوع محتوای درخواست‌ها همان انتظار است' );

$types = ManaCore\Core\Requests::types();
mc_ok( array( 'movie', 'series', 'anime' ) === array_keys( $types ), 'انواع درخواست‌شدنی همان انواع اثر است (بدون قسمت)', implode( ',', array_keys( $types ) ) );

$settings = ManaCore\Core\Requests::settings();
mc_ok( true === $settings['enabled'], 'درخواست‌ها به‌صورت پیش‌فرض روشن است' );
mc_ok( 12 === $settings['per_page'], 'تعداد پیش‌فرض هر صفحه ۱۲ است' );

update_option( 'manacore_settings', array( 'requests_per_page' => 500 ) );
mc_ok( 60 === ManaCore\Core\Requests::settings()['per_page'], 'تعداد بیش از حد به ۶۰ کرانه می‌شود' );
update_option( 'manacore_settings', array() );


/* ---------------------------------------------------------------
 * ۲) یکسان‌سازی عنوان
 * ------------------------------------------------------------ */

echo "\n=== ۲) یکسان‌سازی عنوان ===\n";

mc_ok(
	ManaCore\Core\Requests::normalize_title( 'Dune: Part Two' ) === ManaCore\Core\Requests::normalize_title( 'dune   part   two!' ),
	'فاصله و نشانه‌گذاری در مقایسه‌ی عنوان اثر ندارد'
);
mc_ok(
	ManaCore\Core\Requests::normalize_title( 'سریال يک' ) === ManaCore\Core\Requests::normalize_title( 'سریال یک' ),
	'ی عربی و فارسی یکسان گرفته می‌شوند'
);
mc_ok(
	ManaCore\Core\Requests::normalize_title( 'کوه‌نشین' ) === ManaCore\Core\Requests::normalize_title( 'کوهنشین' ),
	'نیم‌فاصله در مقایسه نادیده گرفته می‌شود'
);
mc_ok( '' === ManaCore\Core\Requests::normalize_title( '   ...   ' ), 'عنوان بی‌حرف و رقم به رشته‌ی خالی می‌رسد' );

/* ---------------------------------------------------------------
 * ۳) ثبت درخواست
 * ------------------------------------------------------------ */

echo "\n=== ۳) ثبت درخواست ===\n";

$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
$GLOBALS['mc_next_id']  = 700;

$result = ManaCore\Core\Requests::submit(
	array(
		'title' => '  سریال تازه  ',
		'type'  => 'series',
		'year'  => '2024',
		'link'  => 'https://example.test/source',
		'note'  => '<b>دوبله</b> می‌خواهم',
	)
);

mc_ok( is_array( $result ) && true === $result['ok'], 'ثبت درخواست موفق است' );
mc_ok( 700 === $result['id'], 'شناسه‌ی درخواست تازه برگردانده می‌شود' );
mc_ok( false === $result['duplicate'], 'درخواست تازه «تکراری» علامت نمی‌خورد' );

$inserted = end( $GLOBALS['mc_inserted'] );
mc_ok( 'manacore_request' === $inserted['post_type'], 'درخواست در نوع محتوای درست ساخته می‌شود' );
mc_ok( 'pending' === $inserted['post_status'], 'درخواست تازه «در انتظار بازبینی» است (نه منتشرشده)' );
mc_ok( 'سریال تازه' === $inserted['post_title'], 'عنوان trim می‌شود' );
mc_ok( 'series' === $inserted['meta_input']['manacore_request_type'], 'نوع اثر ذخیره می‌شود' );
mc_ok( 2024 === $inserted['meta_input']['manacore_request_year'], 'سال ذخیره می‌شود' );
mc_ok( false === strpos( $inserted['meta_input']['manacore_request_hash'], '203.0.113.5' ), 'نشانی شبکه‌ی کاربر خام ذخیره نمی‌شود' );
mc_ok( false === strpos( (string) $inserted['post_content'], '<b>' ), 'تگ HTML از توضیح کاربر پاک می‌شود' );
mc_ok( 1 === count( $GLOBALS['wpdb']->inserts ), 'ثبت‌کننده خودش اولین رأی را می‌دهد' );

/* کرانه‌ی سال */
$GLOBALS['mc_inserted'] = array();
$GLOBALS['mc_transients'] = array();
ManaCore\Core\Requests::submit( array( 'title' => 'اثر با سال نادرست', 'year' => '1200' ) );
$inserted = end( $GLOBALS['mc_inserted'] );
mc_ok( 0 === $inserted['meta_input']['manacore_request_year'], 'سال بیرون از بازه پذیرفته نمی‌شود' );
mc_ok( 'movie' === $inserted['meta_input']['manacore_request_type'], 'نوع پیش‌فرض نخستین نوع فهرست است' );

/* اعتبارسنجی ورودی */
$GLOBALS['mc_transients'] = array();
$short = ManaCore\Core\Requests::submit( array( 'title' => 'x' ) );
mc_ok( is_wp_error( $short ) && 'manacore_request_title' === $short->get_error_code(), 'عنوان کوتاه رد می‌شود' );

$spam = ManaCore\Core\Requests::submit( array( 'title' => 'عنوان درست', 'hp' => 'spam' ) );
mc_ok( is_wp_error( $spam ) && 'manacore_request_spam' === $spam->get_error_code(), 'تله‌ی ربات کار می‌کند' );

/* نرخ */
$GLOBALS['mc_transients'] = array();
ManaCore\Core\Requests::submit( array( 'title' => 'اثر نخست' ) );
$GLOBALS['mc_duplicate_insert'] = false;
$slow = ManaCore\Core\Requests::submit( array( 'title' => 'اثر دوم' ) );
mc_ok( is_wp_error( $slow ) && 'manacore_request_slow' === $slow->get_error_code(), 'ثبت پشت‌سرهم محدود می‌شود' );

/* خاموش بودن و مهمان */
update_option( 'manacore_settings', array( 'requests_enabled' => 0 ) );
$off = ManaCore\Core\Requests::submit( array( 'title' => 'هرچی' ) );
mc_ok( is_wp_error( $off ) && 'manacore_request_disabled' === $off->get_error_code(), 'با خاموش بودن ماژول، ثبت رد می‌شود' );

update_option( 'manacore_settings', array( 'requests_guests' => 0 ) );
$GLOBALS['mc_transients'] = array();
$login = ManaCore\Core\Requests::submit( array( 'title' => 'هرچی' ) );
mc_ok( is_wp_error( $login ) && 'manacore_request_login' === $login->get_error_code(), 'با بستن ثبت مهمان، کاربر وارد‌نشده رد می‌شود' );
update_option( 'manacore_settings', array() );

/* ---------------------------------------------------------------
 * ۴) درخواست تکراری
 * ------------------------------------------------------------ */

echo "\n=== ۴) درخواست تکراری ===\n";

$GLOBALS['mc_posts'][ 710 ] = (object) array(
	'ID'          => 710,
	'post_type'   => 'manacore_request',
	'post_title'  => 'سریال يک',
	'post_status' => 'publish',
);
$GLOBALS['mc_meta'][710]['manacore_request_type'] = 'series';
$GLOBALS['mc_meta'][710][ ManaCore\Core\Requests::COUNT_META ] = 3;

mc_ok( 710 === ManaCore\Core\Requests::find_existing( 'سریال یک', 'series' ), 'درخواست موجود با نگارش دیگر یافتن می‌شود' );
mc_ok( 0 === ManaCore\Core\Requests::find_existing( 'سریال دو', 'series' ), 'عنوان نبوده صفر برمی‌گرداند' );

$GLOBALS['mc_transients'] = array();
$GLOBALS['wpdb']->inserts = array();
$dup = ManaCore\Core\Requests::submit( array( 'title' => 'سریال یک', 'type' => 'series' ) );

mc_ok( is_array( $dup ) && true === $dup['duplicate'], 'ثبت دوباره، درخواست تازه نمی‌سازد' );
mc_ok( 710 === $dup['id'], 'رأی کاربر به درخواست موجود اضافه می‌شود' );
mc_ok( 1 === count( $GLOBALS['wpdb']->inserts ), 'فقط یک رأی تازه درج شد' );
$GLOBALS['mc_duplicate_insert'] = false;

/* ---------------------------------------------------------------
 * ۵) رأی‌گیری
 * ------------------------------------------------------------ */

echo "\n=== ۵) رأی‌گیری ===\n";

$GLOBALS['mc_duplicate_insert'] = false;
$GLOBALS['wpdb']->inserts = array();
$GLOBALS['wpdb']->var     = 7;

$vote = ManaCore\Core\Requests::vote( 710 );
mc_ok( is_array( $vote ) && 7 === $vote['votes'], 'شمار رأی از جدول خوانده می‌شود (نه از متا)' );
mc_ok( false === $vote['duplicate'], 'رأی تازه تکراری نیست' );
mc_ok( 7 === (int) get_post_meta( 710, ManaCore\Core\Requests::COUNT_META, true ), 'شمار رأی در متا آینه می‌شود تا مرتب‌سازی ارزان باشد' );

$GLOBALS['wpdb']->inserts = array();
$GLOBALS['mc_duplicate_insert'] = true;
$again = ManaCore\Core\Requests::vote( 710 );
mc_ok( is_array( $again ) && true === $again['duplicate'], 'رأی تکراری تشخیص داده می‌شود' );
mc_ok( false !== strpos( $again['message'], 'پیش‌تر' ), 'پیام «قبلاً رأی داده‌اید» برگردانده می‌شود' );
$GLOBALS['mc_duplicate_insert'] = false;

$missing = ManaCore\Core\Requests::vote( 999999 );
mc_ok( is_wp_error( $missing ) && 'manacore_request_missing' === $missing->get_error_code(), 'رأی به درخواست ناموجود رد می‌شود' );

mc_ok( 3 === ManaCore\Core\Requests::votes( 710 ) || 7 === ManaCore\Core\Requests::votes( 710 ), 'شمار رأی از متا خوانده می‌شود' );

$GLOBALS['wpdb']->queries = array();
ManaCore\Core\Requests::recount( 710 );
$last = $GLOBALS['wpdb']->queries[0] ?? array( 'sql' => '', 'args' => array() );
mc_ok( false !== strpos( $last['sql'], 'COUNT(*)' ), 'بازشماری با COUNT(*) انجام می‌شود' );
mc_ok( in_array( 710, array_map( 'intval', $last['args'] ), true ), 'شناسه در بازشماری پارامتر شده است', wp_json_encode( $last['args'] ) );

/* پاک‌سازی رأی‌ها با حذف درخواست */
$GLOBALS['wpdb']->deletes = array();
( ManaCore\Core\Requests::instance() )->delete_votes( 710 );
mc_ok( 1 === count( $GLOBALS['wpdb']->deletes ), 'حذف درخواست، رأی‌هایش را هم پاک می‌کند' );
mc_ok( 'wp_manacore_request_votes' === $GLOBALS['wpdb']->deletes[0]['table'], 'جدول رأی‌ها همان انتظار است' );

/* ---------------------------------------------------------------
 * ۶) تخته‌ی درخواست‌ها
 * ------------------------------------------------------------ */

echo "\n=== ۶) تخته‌ی درخواست‌ها ===\n";

$GLOBALS['mc_queries'] = array();
ManaCore\Core\Requests::query( array( 'orderby' => 'votes', 'per_page' => 500, 'status' => 'all' ) );
$q = end( $GLOBALS['mc_queries'] );

mc_ok( array( 'publish', 'pending' ) === $q['post_status'], 'حالت «همه» هر دو وضعیت تأییدشده و در انتظار را می‌آورد' );
mc_ok( ManaCore\Core\Requests::COUNT_META === $q['meta_key'], 'مرتب‌سازی بر پایه‌ی متای شمار رأی است' );
mc_ok( 60 === $q['posts_per_page'], 'تعداد بیش از حد کرانه می‌شود' );

ManaCore\Core\Requests::query( array( 'status' => 'pending' ) );
$q = end( $GLOBALS['mc_queries'] );
mc_ok( array( 'pending' ) === $q['post_status'], 'حالت «در انتظار» فقط پیش‌نویس‌های بازبینی را می‌آورد' );

ManaCore\Core\Requests::query( array( 'type' => 'series' ) );
$q = end( $GLOBALS['mc_queries'] );
mc_ok( isset( $q['meta_query'][0]['value'] ) && 'series' === $q['meta_query'][0]['value'], 'پالایش بر پایه‌ی نوع به کوئری می‌رود' );

/* رندر تخته */
$GLOBALS['mc_query'] = array(
	'posts' => array(
		(object) array( 'ID' => 720, 'post_type' => 'manacore_request', 'post_status' => 'publish', 'post_title' => 'اثر تستی', 'post_content' => 'توضیح' ),
		(object) array( 'ID' => 721, 'post_type' => 'manacore_request', 'post_status' => 'pending', 'post_title' => 'در انتظار', 'post_content' => '' ),
	),
	'found' => 2,
);
$GLOBALS['mc_posts'][720] = $GLOBALS['mc_query']['posts'][0];
$GLOBALS['mc_posts'][721] = $GLOBALS['mc_query']['posts'][1];
$GLOBALS['mc_meta'][720]['manacore_request_type'] = 'movie';
$GLOBALS['mc_meta'][720]['manacore_request_year'] = 2025;
$GLOBALS['mc_meta'][720][ ManaCore\Core\Requests::COUNT_META ] = 12;
$GLOBALS['mc_meta'][721][ ManaCore\Core\Requests::COUNT_META ] = 1;

$board = ManaCore\Core\Requests::instance()->render_board( array( 'showPending' => true ) );

mc_ok( false !== strpos( $board, 'data-manacore-requests' ), 'تخته ریشه‌ی داده‌ای درست دارد' );
mc_ok( false !== strpos( $board, 'data-manacore-vote' ), 'دکمه‌ی رأی‌گیری رندر می‌شود' );
mc_ok( false !== strpos( $board, 'data-request-count' ), 'شمار رأی جای مشخصی برای تازه‌سازی دارد' );
mc_ok( false !== strpos( $board, 'is-pending' ), 'درخواست در انتظار نشان می‌گیرد' );
mc_ok( false !== strpos( $board, 'در انتظار تأیید' ), 'برچسب «در انتظار تأیید» دیده می‌شود' );
mc_ok( false !== strpos( $board, 'اثر تستی' ), 'عنوان درخواست در تخته می‌آید' );

/* با پنهان‌کردن «در انتظار»ها، کوئری فقط منتشرشده‌ها را می‌خواهد. */
$GLOBALS['mc_queries'] = array();
ManaCore\Core\Requests::instance()->render_board( array( 'showPending' => false ) );
$q = end( $GLOBALS['mc_queries'] );
mc_ok( array( 'publish' ) === $q['post_status'], 'با پنهان‌کردن «در انتظار»، فقط تأییدشده‌ها خوانده می‌شوند' );

/* ---------------------------------------------------------------
 * ۷) فرم درخواست
 * ------------------------------------------------------------ */

echo "\n=== ۷) فرم درخواست ===\n";

$form = ManaCore\Core\Requests::instance()->render_form();

mc_ok( false !== strpos( $form, 'data-manacore-request-form' ), 'فرم قلاب جاوااسکریپت دارد' );
mc_ok( false !== strpos( $form, 'name="hp"' ), 'تله‌ی ربات در فرم هست' );
mc_ok( false !== strpos( $form, 'name="title"' ) && false !== strpos( $form, 'required' ), 'فیلد عنوان اجباری است' );
mc_ok( false !== strpos( $form, 'data-manacore-request-status' ), 'ناحیه‌ی پیام فرم رندر می‌شود' );
mc_ok( false !== strpos( $form, 'manacore-request-form__field' ), 'کلاس‌های چیدمان فرم تولید می‌شوند' );

update_option( 'manacore_settings', array( 'requests_enabled' => 0 ) );
mc_ok( '' === ManaCore\Core\Requests::instance()->render_form(), 'با خاموش بودن ماژول، فرم رندر نمی‌شود' );
update_option( 'manacore_settings', array() );

update_option( 'manacore_settings', array( 'requests_guests' => 0 ) );
$gate = ManaCore\Core\Requests::instance()->render_form();
mc_ok( false !== strpos( $gate, 'wp-login.php' ), 'برای مهمان، دعوت به ورود با پیوند نمایش داده می‌شود' );
mc_ok( false === strpos( $gate, 'data-manacore-request-form' ), 'فرم برای مهمان رندر نمی‌شود' );
update_option( 'manacore_settings', array() );

/* برگه‌ی درخواست‌ها */
$page_id = ManaCore\Core\Requests::create_page();
mc_ok( $page_id > 0, 'برگه‌ی درخواست‌ها ساخته می‌شود' );
mc_ok( false !== strpos( $GLOBALS['mc_posts'][ $page_id ]->post_content, 'manacore/request-form' ), 'برگه بلوک فرم را در محتوا دارد' );
mc_ok( false !== strpos( $GLOBALS['mc_posts'][ $page_id ]->post_content, 'manacore/requests' ), 'برگه بلوک تخته را در محتوا دارد' );
mc_ok( $page_id === ManaCore\Core\Requests::create_page(), 'بار دوم همان برگه برگردانده می‌شود (تکراری ساخته نمی‌شود)' );
mc_ok( $page_id === (int) get_option( 'manacore_request_page' ), 'شناسه‌ی برگه ذخیره می‌شود' );

/* ---------------------------------------------------------------
 * ۸) تبدیل درخواست به پیش‌نویس اثر
 * ------------------------------------------------------------ */

echo "\n=== ۸) تبدیل به پیش‌نویس اثر ===\n";

$GLOBALS['mc_posts'][730] = (object) array(
	'ID'          => 730,
	'post_type'   => 'manacore_request',
	'post_title'  => 'فیلم قابل تبدیل',
	'post_status' => 'pending',
);
$GLOBALS['mc_meta'][730]['manacore_request_type'] = 'movie';
$GLOBALS['mc_meta'][730]['manacore_request_year'] = 2022;
$GLOBALS['mc_meta'][730]['manacore_request_link'] = 'https://example.test/src';

$draft = ManaCore\Core\Requests::convert_to_draft( 730 );
mc_ok( $draft > 0, 'پیش‌نویس اثر ساخته می‌شود' );
mc_ok( 'movie' === $GLOBALS['mc_posts'][ $draft ]->post_type, 'نوع پیش‌نویس از نوع درخواست می‌آید' );
mc_ok( 'draft' === $GLOBALS['mc_posts'][ $draft ]->post_status, 'پیش‌نویس منتشر نمی‌شود (مدیر کاملش می‌کند)' );
mc_ok( 2022 === (int) $GLOBALS['mc_meta'][ $draft ]['manacore_year'], 'سال ساخت منتقل می‌شود' );
mc_ok( 730 === (int) $GLOBALS['mc_meta'][ $draft ]['manacore_request_source'], 'پیوند بازگشت به درخواست ذخیره می‌شود' );
mc_ok( $draft === (int) get_post_meta( 730, 'manacore_request_draft_id', true ), 'شناسه‌ی پیش‌نویس روی درخواست ثبت می‌شود' );
mc_ok( 'publish' === $GLOBALS['mc_posts'][730]->post_status, 'درخواست پس از تبدیل، تأییدشده می‌شود' );
mc_ok( 0 === ManaCore\Core\Requests::convert_to_draft( 999999 ), 'تبدیل درخواست ناموجود صفر برمی‌گرداند' );

/* ---------------------------------------------------------------
 * ۹) مسیرها و بلوک‌ها
 * ------------------------------------------------------------ */

echo "\n=== ۹) مسیرها و بلوک‌ها ===\n";

$requests = file_get_contents( MANACORE_PATH . 'includes/class-requests.php' );
$blocks   = file_get_contents( MANACORE_PATH . 'includes/class-blocks.php' );
$boot     = file_get_contents( MANACORE_PATH . 'manacore-core.php' );

mc_ok( false !== strpos( $requests, "'/request'" ), 'مسیر ثبت درخواست ثبت شده است' );
mc_ok( false !== strpos( $requests, "'/request-vote'" ), 'مسیر رأی ثبت شده است' );
mc_ok( false !== strpos( $requests, "'/requests'" ), 'مسیر فهرست درخواست‌ها ثبت شده است' );
mc_ok( 2 === substr_count( $requests, 'verify_public_write' ), 'هر دو مسیر نوشتنِ درخواست پشت دروازه‌بان نانِس‌اند' );
mc_ok( false !== strpos( $blocks, 'manacore/request-form' ), 'بلوک فرم درخواست تعریف شده است' );
mc_ok( false !== strpos( $blocks, 'manacore/requests' ), 'بلوک تخته‌ی درخواست‌ها تعریف شده است' );
mc_ok( false !== strpos( $blocks, "do_action( 'manacore_before_player'" ), 'قلاب «پیش از پلیر» در رندر پلیر صدا زده می‌شود' );

mc_ok( false !== strpos( $boot, 'Requests::instance()->hooks();' ), 'ماژول درخواست‌ها در راه‌اندازی ثبت شده است' );
/*
 * نگهبان حذف: ماژول بنر/تبلیغات به‌خواست کاربر از محصول برداشته شد؛
 * اگر روزی ناخواسته برگردد (یا فایل مرده بماند) این‌جا لو می‌رود.
 */
mc_ok( ! file_exists( MANACORE_PATH . 'includes/class-ads.php' ), 'کلاس تبلیغات از پروژه حذف شده است' );
mc_ok( false === strpos( $blocks, 'manacore/ad-slot' ), 'بلوک جایگاه تبلیغاتی حذف شده است' );
mc_ok( false === strpos( $boot, 'Ads::' ), 'هیچ اشاره‌ای به ماژول تبلیغات در راه‌اندازی نمانده است' );
mc_ok( false === strpos( $requests . $blocks, 'ad-event' ), 'مسیر REST تبلیغات حذف شده است' );

$install = file_get_contents( MANACORE_PATH . 'includes/class-install.php' );
mc_ok( false !== strpos( $install, 'request_votes_table' ), 'جدول رأی‌ها در نصب‌کننده هست' );
mc_ok( false !== strpos( $install, "const DB_VERSION = '1.2.0'" ), 'نسخه‌ی پایگاه‌داده برای جدول رأی‌ها بالا رفته است' );
mc_ok( false !== strpos( $install, 'UNIQUE KEY unique_vote (request_id, voter_hash)' ), 'کلید یکتای رأی تکراری هست' );

$settings = file_get_contents( MANACORE_PATH . 'includes/class-settings.php' );
mc_ok(
	(bool) preg_match( "/'requests'\s*=>/", $settings ) && (bool) preg_match( "/'reports'\s*=>/", $settings ),
	'تب‌های «درخواست‌ها» و «گزارش‌ها» در فهرست تب‌ها هستند'
);
mc_ok( false !== strpos( $settings, "case 'request-page':" ), 'ابزار ساخت برگه‌ی درخواست‌ها ثبت شده است' );

/* ---------------------------------------------------------------
 * ۱۰) درخواست‌های من (پنل کاربری)
 * ------------------------------------------------------------ */

echo "\n=== ۱۰) درخواست‌های من (پنل کاربری) ===\n";

/*
 * کوئری: کاربر واردشده فقط درخواست‌های خودش را می‌گیرد — با هر سه
 * وضعیت. تخته‌ی عمومی «در انتظار» را نشان نمی‌دهد، پس کاربر باید جای
 * دیگری وضعیت درخواست خودش را ببیند؛ همین پنل.
 */
$GLOBALS['mc_queries'] = array();
$GLOBALS['mc_user']    = 7;
$GLOBALS['mc_query']   = array( 'posts' => array(), 'found' => 0 );

ManaCore\Core\Requests::mine( 0, array( 'per_page' => 500 ) );
$q = end( $GLOBALS['mc_queries'] );

mc_ok( 7 === $q['author'], 'فهرست «درخواست‌های من» به نویسنده‌ی جاری بسته می‌شود' );
mc_ok( array( 'publish', 'pending', 'draft' ) === $q['post_status'], 'هر سه وضعیت (تأییدشده، در انتظار، ردشده) در فهرست کاربر می‌آید' );
mc_ok( 60 === $q['posts_per_page'], 'تعداد ردیف‌های فهرست کاربر هم کرانه می‌شود' );
mc_ok( 'date' === $q['orderby'], 'فهرست کاربر بر پایه‌ی تاریخ مرتب می‌شود' );

/* مهمان هرگز نباید کوئری «همه‌ی نویسندگان» بگیرد. */
$GLOBALS['mc_queries'] = array();
$GLOBALS['mc_user']    = 0;

ManaCore\Core\Requests::mine( 0 );
$q = end( $GLOBALS['mc_queries'] );

mc_ok( ! isset( $q['author'] ), 'کاربر وارد‌نشده کوئری نویسنده‌محور نمی‌گیرد' );
mc_ok( array( 0 ) === $q['post__in'], 'مهمان با کوئری بسته می‌شود (نه فهرست همه‌ی درخواست‌ها)' );
mc_ok( 0 === ManaCore\Core\Requests::mine_total(), 'شمار درخواست‌ها برای مهمان صفر است' );

$GLOBALS['mc_query'] = array( 'posts' => array(), 'found' => 4 );
mc_ok( 4 === ManaCore\Core\Requests::mine_total( 7 ), 'شمار کل از found_posts کوئری خودِ کاربر می‌آید' );

/* برچسب و کلاس وضعیت */
mc_ok( 'تأییدشده' === ManaCore\Core\Requests::status_label( 'publish' ), 'وضعیت منتشرشده «تأییدشده» است' );
mc_ok( 'در انتظار تأیید' === ManaCore\Core\Requests::status_label( 'pending' ), 'وضعیت بازبینی «در انتظار تأیید» است' );
mc_ok( 'ردشده' === ManaCore\Core\Requests::status_label( 'draft' ), 'پیش‌نویسْ همان «ردشده»ی درخواست است' );
mc_ok( 'is-pending' === ManaCore\Core\Requests::status_class( 'چیز نامعتبر' ), 'وضعیت ناشناخته به کلاس «در انتظار» می‌افتد' );

/* رندر فهرست: حالت خالی */
$mine = ManaCore\Core\Requests::instance()->mine_html();

mc_ok( false !== strpos( $mine, 'data-manacore-mine' ), 'فهرست «درخواست‌های من» لنگر تازه‌سازی دارد' );
mc_ok( false !== strpos( $mine, 'هنوز درخواستی ثبت نکرده‌ای' ), 'حالت خالی متن راهنما دارد' );

/* رندر فهرست: یک درخواست در انتظار */
$GLOBALS['mc_posts'][710]->post_type    = 'manacore_request';
$GLOBALS['mc_posts'][710]->post_status  = 'pending';
$GLOBALS['mc_posts'][710]->post_title   = 'سریال در انتظار';
$GLOBALS['mc_posts'][710]->post_content = 'توضیح نمونه';
$GLOBALS['mc_meta'][710]                = array(
	'manacore_request_type'            => 'series',
	'manacore_request_year'            => 2024,
	'manacore_request_link'            => 'https://example.test/source',
	ManaCore\Core\Requests::COUNT_META => 3,
);

$GLOBALS['mc_query'] = array(
	'posts' => array( $GLOBALS['mc_posts'][710] ),
	'found' => 1,
);

$mine = ManaCore\Core\Requests::instance()->mine_html();

mc_ok( false !== strpos( $mine, 'manacore-request is-pending' ), 'ردیف در انتظار کلاس وضعیت می‌گیرد' );
mc_ok( false !== strpos( $mine, 'در انتظار تأیید' ), 'برچسب وضعیت روی ردیف می‌آید' );
mc_ok( false !== strpos( $mine, '>سریال<' ), 'نوع درخواست روی ردیف می‌آید' );
mc_ok( false !== strpos( $mine, '>2024<' ), 'سال درخواست روی ردیف می‌آید' );
mc_ok( false !== strpos( $mine, 'data-request-id="710"' ), 'شناسه‌ی درخواست برای تازه‌سازی زنده روی ردیف هست' );
mc_ok( false !== strpos( $mine, '>3<' ), 'شمار رأی از متا خوانده می‌شود' );
mc_ok( false !== strpos( $mine, 'https://example.test/source' ), 'پیوند منبع در ردیف کاربر می‌آید' );

/* مهمان: دروازه‌ی ورود */
$GLOBALS['mc_user'] = 0;
$gate               = ManaCore\Core\Requests::instance()->render_mine();

mc_ok( false !== strpos( $gate, 'manacore-request-gate' ), 'مهمان به‌جای پنل، دروازه‌ی ورود می‌بیند' );
mc_ok( false !== strpos( $gate, 'wp-login.php' ), 'دروازه‌ی ورود به صفحه‌ی ورود وصل است' );

/* کاربر: فرم + فهرست، و خاموش‌کردن فرم */
$GLOBALS['mc_user'] = 7;
$panel              = ManaCore\Core\Requests::instance()->render_mine();

mc_ok( false !== strpos( $panel, 'data-manacore-request-form' ), 'پنل، فرم ثبت درخواست را هم می‌آورد' );
mc_ok( false !== strpos( $panel, 'data-manacore-mine' ), 'پنل، فهرست درخواست‌های کاربر را می‌آورد' );

$list_only = ManaCore\Core\Requests::instance()->render_mine( array( 'showForm' => false ) );
mc_ok( false === strpos( $list_only, 'data-manacore-request-form' ), 'با خاموش‌کردن فرم، فقط فهرست می‌ماند' );

$short = ManaCore\Core\Requests::instance()->mine_shortcode( array( 'show_form' => '0' ) );
mc_ok(
	false === strpos( $short, 'data-manacore-request-form' ) && false !== strpos( $short, 'data-manacore-mine' ),
	'شورت‌کد پنل، همان رفتار بلوک را دارد'
);

/* خاموش بودن ماژول: هیچ‌کس پنل نمی‌بیند */
update_option( 'manacore_settings', array( 'requests_enabled' => 0 ) );
mc_ok( '' === ManaCore\Core\Requests::instance()->render_mine(), 'با خاموش بودن ماژول، پنل رندر نمی‌شود' );
update_option( 'manacore_settings', array( 'requests_enabled' => 1 ) );

/* میدان دید: مسیر، دسترسی، بلوک، تب و قالب */
mc_ok( false !== strpos( $requests, "'/my-requests'" ), 'مسیر «درخواست‌های من» ثبت شده است' );
mc_ok( false !== strpos( $requests, 'permission_mine' ), 'مسیر فقط برای کاربر واردشده باز است' );
mc_ok( true === ManaCore\Core\Requests::instance()->permission_mine(), 'دسترسی مسیر برای کاربر واردشده برقرار است' );

$GLOBALS['mc_user'] = 0;
mc_ok( false === ManaCore\Core\Requests::instance()->permission_mine(), 'دسترسی مسیر برای مهمان بسته است' );

$account = file_get_contents( MANACORE_PATH . 'includes/class-account.php' );
$theme   = dirname( __DIR__ ) . '/themes/koohe-film/templates/page-account.html';
$page    = file_exists( $theme ) ? file_get_contents( $theme ) : '';
$front   = file_get_contents( MANACORE_PATH . 'assets/js/front.js' );

mc_ok( false !== strpos( $account, "'requests'" ) && false !== strpos( $account, 'Requests::mine_total()' ), 'تب «درخواست‌های من» با نشان شمارشی به فهرست تب‌ها اضافه شده است' );
mc_ok( false !== strpos( $blocks, 'manacore/account-requests' ) && false !== strpos( $blocks, 'render_account_requests' ), 'بلوک «درخواست‌های من» در افزونه تعریف شده است' );
mc_ok( false !== strpos( $page, 'manacore/account-requests' ), 'قالب برگه‌ی حساب، پنل درخواست‌ها را در تب خودش می‌آورد' );
mc_ok( false !== strpos( $front, 'data-manacore-mine' ) && false !== strpos( $front, "'my-requests'" ), 'مرورگر فهرست درخواست‌های کاربر را از REST تازه می‌کند' );

/* ---------------------------------------------------------------
 * پایان
 * ------------------------------------------------------------ */

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo 'موفق: ' . $GLOBALS['mc_pass'] . '   ناموفق: ' . $GLOBALS['mc_fail'] . "\n";
echo str_repeat( '=', 58 ) . "\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
