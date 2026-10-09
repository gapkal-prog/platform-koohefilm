<?php
/**
 * آزمون رندر صفحه‌ی تنظیمات ManaCore.
 *
 * این آزمون تب‌ها را با پوسته‌ی وردپرس «واقعاً رندر» می‌کند تا خطاهایی که
 * آزمون‌های متنی نمی‌گیرند لو بروند:
 *   ۱. تب بیِ‌متد رندر (صفحه‌ی سفید) یا تبی که در `render()` صدا زده نشده؛
 *   ۲. بخش پنل بازِ‌بی‌بست (HTML ناقص) در یکی از تب‌ها؛
 *   ۳. استایل درون‌خطی/تگ `<style>` در صفحه (ظاهر یک‌دست باید از فایل CSS بیاید)؛
 *   ۴. جدول گزارش‌های خرابی لینک، فیلترها و صفحه‌بندی.
 *
 * اجرا: php wp/tests/test-settings-panel.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_URL', 'http://example.test/plugins/manacore-core/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['mc_pass'] = 0;
$GLOBALS['mc_fail'] = 0;

function mc_ok( $ok, $label, $extra = '' ) {
	if ( $ok ) {
		$GLOBALS['mc_pass']++;
	} else {
		$GLOBALS['mc_fail']++;
	}

	$extra = preg_replace( '/\s+/', ' ', (string) $extra );

	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $label
		. ( '' !== $extra ? '  — ' . substr( $extra, 0, $ok ? 40 : 600 ) : '' )
		. "\n";
}

/* ---------------------------------------------------------------
 * پوسته‌ی وردپرس
 * ------------------------------------------------------------ */

$GLOBALS['mc_options']    = array();
$GLOBALS['mc_transients'] = array();
$GLOBALS['mc_posts']      = array();
$GLOBALS['mc_meta']       = array();
$GLOBALS['mc_terms']      = array();
$GLOBALS['mc_reports']    = array();
$GLOBALS['mc_admin']      = true;

class MC_Wpdb {
	public $prefix    = 'wp_';
	public $options   = 'wp_options';
	public $insert_id = 41;
	public $rows      = array();
	public $vars      = array();
	public $queries   = array();
	public $var       = 0;

	public $args = array();

	public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
	public function prepare( $query, ...$args ) {
		$this->queries[] = $query;

		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$this->args = $args;

		return $query;
	}
	public function get_var( $query, $x = 0, $y = 0 ) {
		$this->queries[] = $query;

		if ( $this->vars ) {
			return array_shift( $this->vars );
		}

		return $this->var;
	}
	public function get_col( $query, $x = 0 ) {
		$this->queries[] = (string) $query;

		/* فقط ستون نام گزینه‌ها برای پاک‌سازی کش مگامنو خوانده می‌شود. */
		return array();
	}
	public function get_results( $query, $mode = OBJECT ) {
		$query = (string) $query;
		$args  = $this->args;
		$this->args = array();

		if ( false !== strpos( $query, 'status, COUNT' ) ) {
			$rows = array();

			foreach ( $GLOBALS['mc_reports'] as $report ) {
				$status = $report['status'];
				$found  = false;

				foreach ( $rows as $index => $row ) {
					if ( $row['status'] === $status ) {
						$rows[ $index ]['total']++;
						$found = true;
					}
				}

				if ( ! $found ) {
					$rows[] = array( 'status' => $status, 'total' => 1 );
				}
			}

			return $rows;
		}

		/* فقط جدول گزارش‌ها داده دارد؛ بقیه‌ی کوئری‌ها خالی برمی‌گردند. */
		if ( false === strpos( $query, 'manacore_reports' ) ) {
			return array();
		}

		$rows = array_values( $GLOBALS['mc_reports'] );

		if ( false !== strpos( $query, 'WHERE status' ) && $args ) {
			$status = (string) array_shift( $args );
			$rows   = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $status ) {
						return $status === $row['status'];
					}
				)
			);
		}

		/* LIMIT/OFFSET واقعی، تا صفحه‌بندی هم سنجیده شود. */
		if ( $args ) {
			$per_page = (int) array_shift( $args );
			$offset   = $args ? (int) array_shift( $args ) : 0;

			if ( $per_page > 0 ) {
				$rows = array_slice( $rows, $offset, $per_page );
			}
		}

		return $rows;
	}
	public function get_row( $query, $mode = OBJECT, $y = 0 ) {
		$this->queries[] = $query;

		/* آزمون داشبورد با <ARRAY_A می‌خواند؛ همان شکل را می‌دهیم. */
		return array( 'total' => 0, 'average' => 0, 'rating' => 0, 'votes' => 0 );
	}
	public function insert( $table, $data, $format = null ) { return 1; }
	public function update( $table, $data, $where, $df = null, $wf = null ) { return 1; }
	public function delete( $table, $where, $wf = null ) { return 1; }
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
}

$GLOBALS['wpdb'] = new MC_Wpdb();

function __( $t, $d = '' ) { return $t; }
function _x( $t, $c = '', $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_attr__( $t, $d = '' ) { return $t; }
function esc_html_e( $t, $d = '' ) { echo $t; }
function esc_attr_e( $t, $d = '' ) { echo $t; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_textarea( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return (string) $v; }
function esc_url_raw( $v ) { return filter_var( (string) $v, FILTER_SANITIZE_URL ); }
function esc_js( $v ) { return addslashes( (string) $v ); }
function esc_sql( $v ) { return (string) $v; }
function wp_kses_post( $v ) { return (string) $v; }
function apply_filters( $tag, $value ) { return $value; }
function add_action() {}
function add_filter() {}
function do_action() {}
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_title( $v ) { return strtolower( trim( preg_replace( '/[\s_]+/', '-', (string) $v ) ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_\- ]/', '', (string) $v ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function wp_unslash( $v ) { return $v; }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function is_wp_error( $v ) { return false; }
function current_user_can() { return true; }
function current_time( $t = 'mysql' ) { return '2026-10-08 12:00:00'; }
function home_url( $p = '' ) { return 'http://example.test' . $p; }
function admin_url( $p = '' ) { return 'http://example.test/wp-admin/' . $p; }
function rest_url( $p = '' ) { return 'http://example.test/wp-json/' . $p; }
function wp_login_url( $p = '' ) { return 'http://example.test/wp-login.php'; }
/*
 * امضای دوگانه‌ی وردپرس: (کلید، مقدار، نشانی) یا (آرایه، نشانی).
 * مقدارها کدگذاری نمی‌شوند؛ جای‌نگهدارِ `paginate_links` (`%#%`) به
 * همین رفتار تکیه دارد.
 */
function add_query_arg( $arg1, $arg2 = '', $arg3 = '' ) {
	$count = func_num_args();
	$url   = 'http://example.test/current';

	if ( is_array( $arg1 ) ) {
		$pairs = $arg1;

		if ( $count > 1 && is_string( $arg2 ) ) {
			$url = $arg2;
		}
	} else {
		$pairs = array( $arg1 => (string) $arg2 );

		if ( $count > 2 && is_string( $arg3 ) ) {
			$url = $arg3;
		}
	}

	$parts = array();

	foreach ( $pairs as $key => $value ) {
		$parts[] = $key . '=' . $value;
	}

	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $parts );
}
function wp_nonce_field( $action = '', $name = '_wpnonce', $ref = true, $echo = true ) {
	$html = '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '" />';

	if ( $echo ) {
		echo $html;
	}

	return $html;
}
function wp_nonce_url( $url, $action = '' ) { return $url . '&_wpnonce=nonce-' . $action; }
function check_admin_referer() { return true; }
function settings_fields( $group ) { echo '<input type="hidden" name="option_page" value="' . $group . '" />'; }
function submit_button( $text = 'ذخیره', $type = 'primary', $name = 'submit', $wrap = true, $other = null ) {
	$html = '<button type="submit" class="button button-' . $type . '">' . $text . '</button>';

	if ( $wrap ) {
		echo '<p class="submit">' . $html . '</p>';
	} else {
		echo $html;
	}
}
function checked( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
function selected( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }
function wp_dropdown_pages( $args = array() ) {
	echo '<select name="' . esc_attr( $args['name'] ) . '" id="' . esc_attr( $args['id'] ) . '">';

	if ( isset( $args['show_option_none'] ) ) {
		echo '<option value="' . esc_attr( $args['option_none_value'] ) . '">' . esc_html( $args['show_option_none'] ) . '</option>';
	}

	echo '</select>';
}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['mc_options'] ) ? $GLOBALS['mc_options'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['mc_options'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['mc_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['mc_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['mc_transients'][ $k ] ); return true; }
function wp_count_posts( $type = 'post', $perm = '' ) {
	$counts = (object) array( 'publish' => 0, 'draft' => 0, 'pending' => 0, 'trash' => 0, 'future' => 0, 'private' => 0 );
	$map    = array(
		'manacore_request' => array( 'publish' => 3, 'draft' => 1, 'pending' => 2 ),
		'movie'            => array( 'publish' => 5 ),
		'series'           => array( 'publish' => 2 ),
		'episode'          => array( 'publish' => 9 ),
		'person'           => array( 'publish' => 4 ),
	);

	foreach ( ( $map[ $type ] ?? array() ) as $status => $total ) {
		$counts->$status = $total;
	}

	return $counts;
}
function wp_count_comments() { return (object) array( 'moderated' => 4, 'spam' => 1, 'approved' => 10 ); }
function get_posts( $args = array() ) { return array(); }
function get_post( $id ) { return $GLOBALS['mc_posts'][ (int) $id ] ?? null; }
function get_post_type( $id ) { return $GLOBALS['mc_posts'][ (int) $id ]->post_type ?? ''; }
function get_post_status( $id ) { return $GLOBALS['mc_posts'][ (int) $id ]->post_status ?? ''; }
function get_post_field( $field, $id ) { return $GLOBALS['mc_posts'][ (int) $id ]->$field ?? ''; }
function get_permalink( $id ) { return 'http://example.test/watch/?manacore_id=' . (int) $id; }
function get_edit_post_link( $id, $ctx = '' ) { return 'http://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit'; }
function get_current_user_id() { return 1; }
function get_the_title( $id ) { return $GLOBALS['mc_posts'][ (int) $id ]->post_title ?? ''; }
function get_taxonomies( $args = array(), $out = 'names' ) {
	$taxes = array();

	foreach ( array( 'genre', 'country' ) as $name ) {
		$tax                   = new stdClass();
		$tax->name             = $name;
		$tax->labels           = (object) array( 'name' => $name );
		$taxes[ $name ]        = $tax;
	}

	return $taxes;
}
function taxonomy_exists( $t ) { return in_array( (string) $t, array( 'genre', 'country' ), true ); }
function post_type_exists( $t ) { return in_array( (string) $t, array( 'movie', 'series', 'anime', 'episode', 'person', 'collection', 'manacore_request' ), true ); }
function wp_list_pluck( $list, $field ) {
	$out = array();

	foreach ( (array) $list as $item ) {
		$out[] = is_object( $item ) ? ( $item->$field ?? '' ) : ( $item[ $field ] ?? '' );
	}

	return $out;
}
function get_terms( $args = array() ) {
	$terms = array();

	foreach ( array( 'اکشن', 'درام', 'کمدی' ) as $index => $name ) {
		$term           = new stdClass();
		$term->term_id  = 10 + $index;
		$term->slug     = 'term-' . ( 10 + $index );
		$term->name     = $name;
		$term->count    = 5 - $index;
		$term->taxonomy = $args['taxonomy'] ?? 'genre';
		$terms[]        = $term;
	}

	return $terms;
}
class WP_Term {
	public $term_id  = 0;
	public $slug     = '';
	public $name     = '';
	public $count    = 0;
	public $taxonomy = '';

	public function __construct( $data = array() ) {
		foreach ( (array) $data as $key => $value ) {
			$this->$key = $value;
		}
	}
}
function get_term( $id ) { return null; }
function get_term_by( $field, $value, $taxonomy = '' ) {
	foreach ( get_terms( array( 'taxonomy' => $taxonomy ) ) as $term ) {
		if ( $field === 'slug' && $term->slug === $value ) {
			return new WP_Term( (array) $term );
		}
	}

	return false;
}function get_term_link( $term ) { return 'http://example.test/genre/x/'; }
function sanitize_title_with_dashes( $v ) { return sanitize_title( $v ); }function wp_get_object_terms() { return array(); }
function wp_count_terms() { return 0; }
function get_page_by_path( $path, $out = OBJECT, $type = 'page' ) { return null; }
function wp_safe_redirect() {}
function wp_redirect() {}
function paginate_links( $args = array() ) {
	$total = isset( $args['total'] ) ? (int) $args['total'] : 1;

	if ( $total < 2 ) {
		return '';
	}

	$out = '<span class="page-numbers current">۱</span>';

	for ( $i = 2; $i <= $total; $i++ ) {
		$out .= ' <a class="page-numbers" href="' . str_replace( '%#%', (string) $i, (string) $args['base'] ) . '">' . $i . '</a>';
	}

	return $out;
}
function wp_nonce_field_name() { return '_wpnonce'; }
function is_admin() { return (bool) $GLOBALS['mc_admin']; }
function did_action() { return 0; }
function doing_action() { return false; }
function wp_die( $m = '' ) { throw new RuntimeException( 'wp_die: ' . $m ); }
function wp_salt( $s = '' ) { return 'test-salt'; }
function wp_rand( $min = 0, $max = 0 ) { return $min; }
function wp_get_current_user() { return (object) array( 'ID' => 1, 'roles' => array( 'administrator' ) ); }
function get_avatar_url() { return ''; }
function get_userdata( $id ) { return (object) array( 'display_name' => 'مدیر', 'user_login' => 'admin' ); }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) { return array_merge( $pairs, (array) $atts ); }

/* ---------------------------------------------------------------
 * بارگذاری کلاس‌ها
 * ------------------------------------------------------------ */

require_once MANACORE_PATH . 'includes/functions.php';
require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-install.php';
require_once MANACORE_PATH . 'includes/class-downloads.php';
require_once MANACORE_PATH . 'includes/class-player.php';
require_once MANACORE_PATH . 'includes/class-requests.php';
require_once MANACORE_PATH . 'includes/class-reports.php';
require_once MANACORE_PATH . 'includes/class-demo.php';
require_once MANACORE_PATH . 'includes/class-portability.php';
require_once MANACORE_PATH . 'includes/class-analytics.php';
require_once MANACORE_PATH . 'includes/class-link-tools.php';
require_once MANACORE_PATH . 'includes/class-settings.php';

use ManaCore\Core\Settings;

/* ---------------------------------------------------------------
 * ۱) تب‌ها
 * ------------------------------------------------------------ */

echo "\n=== ۱) تب‌ها و ناوبری ===\n";

/*
 * شش تب، به همین ترتیب. تب «تحلیل و آمار» عمداً نیست: داده‌ی اساسی‌اش در
 * کارت «نگاه کلی» تب عمومی آمده و بقیه به تب‌های مرتبط خودش رفته است.
 */
$expected = array( 'general', 'appearance', 'watch', 'requests', 'reports', 'tools' );
mc_ok( $expected === array_keys( Settings::tabs() ), 'ترتیب تب‌ها همان ترتیب مستندشده است', implode( ',', array_keys( Settings::tabs() ) ) );
mc_ok( ! in_array( 'ads', array_keys( Settings::tabs() ), true ), 'تب تبلیغات از پنل برداشته شده است' );
mc_ok( ! in_array( 'analytics', array_keys( Settings::tabs() ), true ), 'تب تحلیل و آمار از پنل برداشته شده است' );


$src = (string) file_get_contents( MANACORE_PATH . 'includes/class-settings.php' );

mc_ok( false === strpos( $src, '<style' ), 'هیچ تگ <style> در صفحه نیست (استایل‌ها در admin.css)' );
mc_ok( false === strpos( $src, 'style="' ), 'هیچ استایل درون‌خطی در صفحه نیست' );

/* هر تب باید متد رندر داشته باشد و در `render()` هم صدا زده شود. */
$tabs   = Settings::tabs();
$panels = array();

foreach ( $tabs as $key => $label ) {
	$method = 'render_' . $key . '_tab';

	if ( 'general' === $key ) {
		$has = (bool) preg_match( '/case \'general\'|default:/', $src );
	} else {
		$has = (bool) preg_match( "/case '" . $key . "':/", $src );
	}

	mc_ok( $has, "تب «{$key}» در سوییچ render() صدا زده می‌شود" );
	mc_ok( (bool) preg_match( '/function ' . $method . '\(/', $src ), "متد {$method}() وجود دارد" );

	$_GET['tab'] = $key;

	ob_start();
	Settings::instance()->render();
	$html = (string) ob_get_clean();

	$panels[ $key ] = $html;

	mc_ok( '' !== trim( $html ), "تب «{$key}» خروجی می‌دهد" );
	mc_ok(
		substr_count( $html, '<section class="manacore-panel">' ) === substr_count( $html, '</section>' ),
		"بخش‌های پنل تب «{$key}» باز و بسته‌ی متوازن دارند",
		'ob=' . substr_count( $html, '<section class="manacore-panel">' ) . ' close=' . substr_count( $html, '</section>' )
	);
	mc_ok( false === strpos( $html, 'style="' ), "تب «{$key}» استایل درون‌خطی ندارد" );
	mc_ok( false !== strpos( $html, 'nav-tab-wrapper' ), "نوار تب در «{$key}» هست" );
	mc_ok( false !== strpos( $html, 'manacore-panel' ), "تب «{$key}» دست‌کم یک بخش پنل دارد" );
}

/*
 * کارت «نگاه کلی»: جای تب تحلیل را گرفته و بیرون از فرم تنظیمات است.
 */
mc_ok( false !== strpos( $panels['general'], 'نگاه کلی' ), 'کارت «نگاه کلی» در تب عمومی هست' );
mc_ok( false !== strpos( $panels['general'], 'manacore-stats' ), 'شمارنده‌های نگاه کلی رندر می‌شوند' );
mc_ok( false !== strpos( $panels['general'], 'تماشا ۷ روز' ) && false !== strpos( $panels['general'], 'دانلود ۷ روز' ), 'عددهای واقعی تماشا و دانلود می‌آیند' );

/* ---------------------------------------------------------------
 * ۲) فرم‌ها و تب ابزارها
 * ------------------------------------------------------------ */

echo "\n=== ۲) فرم‌ها ===\n";

mc_ok( false !== strpos( $panels['general'], 'action="options.php"' ), 'تب عمومی فرم استاندارد options.php دارد' );
mc_ok( false !== strpos( $panels['general'], 'name="manacore_settings[slug_movie]"' ), 'فیلد نشانی یکتا در فرم هست' );
mc_ok( false !== strpos( $panels['watch'], 'name="manacore_settings[download_ttl]"' ), 'فیلد اعتبار لینک دانلود در تب پخش هست' );
mc_ok( false !== strpos( $panels['requests'], 'name="manacore_settings[requests_enabled]"' ), 'فیلد فعال‌بودن درخواست‌ها در تب درخواست‌ها هست' );
mc_ok( ! isset( $panels['mega'] ), 'تب مگامنو از پیشخوان حذف شده است (مدیریت از فهرست راهبری)' );
mc_ok( array() === array_intersect( Settings::retired_keys(), Settings::option_keys() ), 'کلیدهای منسوخ مگامنو در هیچ تبی نیستند' );

/*
 * تب ابزارها هم فرم تنظیمات دارد (کارت «سلامت لینک‌ها») هم فرم‌های
 * admin-post؛ مهم این است که هیچ فرمی درون فرم دیگر نیفتد، وگرنه
 * مرورگر فرم درونی را دور می‌ریزد و دکمه‌ها بی‌اثر می‌شوند.
 */
$form_depth = 0;
$form_max   = 0;
$cursor     = 0;

while ( true ) {
	$open  = strpos( $panels['tools'], '<form', $cursor );
	$close = strpos( $panels['tools'], '</form>', $cursor );

	if ( false === $open && false === $close ) {
		break;
	}

	if ( false !== $open && ( false === $close || $open < $close ) ) {
		$form_depth++;
		$form_max = max( $form_max, $form_depth );
		$cursor   = $open + 5;
	} else {
		$form_depth--;
		$cursor = $close + 7;
	}
}

mc_ok( 1 === $form_max && 0 === $form_depth, 'فرم‌های تب ابزارها تودرتو نیستند' );
mc_ok( false !== strpos( $panels['tools'], 'name="manacore_settings[links_check_interval]"' ), 'تنظیم زمان‌بندی بررسی در تب ابزارها هست' );
mc_ok( false !== strpos( $panels['tools'], 'name="manacore_settings[links_check_batch]"' ) && false !== strpos( $panels['tools'], 'name="manacore_settings[links_check_timeout]"' ), 'فیلدهای تعداد و مهلت بررسی هم آمده‌اند' );
mc_ok( false !== strpos( $panels['tools'], 'value="check-links"' ), 'دکمه‌ی اجرای دستی بررسی لینک‌ها هست' );
mc_ok( false !== strpos( $panels['tools'], '«سلامت لینک‌ها»' ) || false !== strpos( $panels['tools'], 'سلامت لینک‌ها' ), 'کارت «سلامت لینک‌ها» رندر می‌شود' );
mc_ok( false !== strpos( $panels['tools'], 'value="off"' ) && false !== strpos( $panels['tools'], 'value="weekly"' ), 'گزینه‌های بازه‌ی بررسی (خاموش تا هفتگی) آمده‌اند' );
mc_ok( false !== strpos( $panels['tools'], 'name="action" value="manacore_tool"' ), 'دکمه‌های ابزار از admin-post می‌آیند' );
mc_ok( false !== strpos( $panels['tools'], 'name="action" value="manacore_export"' ), 'کارت پشتیبان‌گیری در تب ابزارها هست' );
mc_ok( false !== strpos( $panels['tools'], '[manacore_my_requests]' ), 'شورت‌کد «درخواست‌های من» در فهرست شورت‌کدها آمده است' );

/* فرم تودرتو ممنوع: تعداد <form> باز و بسته باید برابر باشد و هیچ فرمی داخل فرم نباشد. */
foreach ( $panels as $key => $html ) {
	mc_ok(
		substr_count( $html, '<form' ) === substr_count( $html, '</form>' ),
		"فرم‌های تب «{$key}» بسته شده‌اند",
		'ob=' . substr_count( $html, '<form' ) . ' close=' . substr_count( $html, '</form>' )
	);
}

$tools_forms = substr_count( $panels['tools'], '<form' );
mc_ok( $tools_forms >= 4, 'همه‌ی کنش‌های تب ابزارها فرم جدا دارند', (string) $tools_forms );

/* ---------------------------------------------------------------
 * ۳) جدول گزارش‌های خرابی لینک
 * ------------------------------------------------------------ */

echo "\n=== ۳) گزارش‌های خرابی لینک ===\n";

$GLOBALS['mc_reports'] = array(
	array(
		'id'         => 7,
		'post_id'    => 11,
		'quality'    => '1080p',
		'link_url'   => 'https://example.test/a',
		'link_label' => 'دانلود ۱۰۸۰',
		'reason'     => 'لینک باز نمی‌شود',
		'status'     => 'new',
		'created_at' => '2026-10-01 10:00:00',
	),
	array(
		'id'         => 8,
		'post_id'    => 44,
		'quality'    => '720p',
		'link_url'   => 'https://example.test/b',
		'link_label' => 'دانلود ۷۲۰',
		'reason'     => 'فایل حذف شده',
		'status'     => 'fixed',
		'created_at' => '2026-09-28 09:00:00',
	),
);

$GLOBALS['mc_posts'][11] = (object) array( 'ID' => 11, 'post_type' => 'movie', 'post_status' => 'publish', 'post_title' => 'فیلم تستی' );
$GLOBALS['mc_posts'][44] = (object) array( 'ID' => 44, 'post_type' => 'series', 'post_status' => 'publish', 'post_title' => 'سریال تستی' );

$_GET['tab'] = 'reports';
unset( $_GET['report_status'], $_GET['report_page'] );

ob_start();
Settings::instance()->render();
$reports_html = (string) ob_get_clean();

mc_ok( false !== strpos( $reports_html, 'گزارش‌های خرابی لینک' ), 'عنوان بخش گزارش‌ها در تب هست' );
mc_ok( false !== strpos( $reports_html, 'class="manacore-filters"' ), 'فیلتر وضعیت‌ها بالای جدول هست' );
mc_ok( false !== strpos( $reports_html, 'manacore-filter__count' ), 'شمار هر وضعیت روی چیپ فیلتر می‌آید' );
mc_ok( false !== strpos( $reports_html, 'class="widefat striped manacore-reports"' ), 'جدول گزارش‌ها با کلاس اختصاصی رندر می‌شود' );
mc_ok( false !== strpos( $reports_html, 'فیلم تستی' ) && false !== strpos( $reports_html, 'سریال تستی' ), 'هر دو گزارش فهرست شده‌اند' );
mc_ok( false !== strpos( $reports_html, 'لینک باز نمی‌شود' ), 'توضیح کاربر در ستون خودش می‌آید' );
mc_ok( 6 === substr_count( $reports_html, 'report_action=' ), 'هر ردیف سه کنش (اصلاح/نادیده/حذف) دارد', (string) substr_count( $reports_html, 'report_action=' ) );
mc_ok( false !== strpos( $reports_html, 'manacore_report_7' ), 'کنش‌ها با نانِس اختصاصی ردیف محافظت می‌شوند' );

/* فیلتر وضعیت: وقتی «اصلاح‌شده» انتخاب شود، فقط همان ردیف می‌آید. */
$_GET['report_status'] = 'fixed';
$GLOBALS['wpdb']->queries = array();

ob_start();
Settings::instance()->render();
$fixed_html = (string) ob_get_clean();

mc_ok( false !== strpos( $fixed_html, 'سریال تستی' ), 'با فیلتر «اصلاح‌شده» گزارش همان وضعیت می‌آید' );
mc_ok( false === strpos( $fixed_html, 'فیلم تستی' ), 'گزارش وضعیت دیگر در نمای فیلترشده نمی‌آید' );
mc_ok( false !== strpos( $fixed_html, 'manacore-filter is-active' ), 'فیلتر فعال نشانه‌گذاری می‌شود' );
mc_ok( false !== strpos( $fixed_html, 'aria-current="page"' ), 'فیلتر فعال برای صفحه‌خوان هم نشانه‌گذاری شده است' );
mc_ok( false !== strpos( $fixed_html, 'report_status=fixed' ), 'کنش‌ها وضعیت فیلتر را برای بازگشت نگه می‌دارند' );

unset( $_GET['report_status'] );

/* صفحه‌بندی: با بیش از ۲۰ گزارش باید لینک صفحه‌ی دوم بیاید. */
$many = array();

for ( $i = 1; $i <= 25; $i++ ) {
	$many[] = array(
		'id'         => 100 + $i,
		'post_id'    => 11,
		'quality'    => '1080p',
		'link_url'   => 'https://example.test/x',
		'link_label' => 'لینک',
		'reason'     => 'خراب',
		'status'     => 'new',
		'created_at' => '2026-10-01 10:00:00',
	);
}

$GLOBALS['mc_reports'] = $many;

ob_start();
Settings::instance()->render();
$paged_html = (string) ob_get_clean();

mc_ok( false !== strpos( $paged_html, 'tablenav-pages' ), 'با بیش از یک صفحه، صفحه‌بندی رندر می‌شود' );
mc_ok( false !== strpos( $paged_html, 'report_page=2' ), 'پیوند صفحه‌ی دوم با پارامتر خودش ساخته می‌شود' );

/* ---------------------------------------------------------------
 * ۴) نگهبان حذف ماژول بنر
 * ------------------------------------------------------------ */

echo "\n=== ۴) نگه‌بان حذف ماژول بنر ===\n";

mc_ok( ! file_exists( MANACORE_PATH . 'includes/class-ads.php' ), 'فایل کلاس تبلیغات نیست' );
mc_ok( ! file_exists( MANACORE_PATH . 'assets/js/admin-ads.js' ), 'اسکریپت پیشخوان تبلیغات نیست' );
mc_ok( false === strpos( $src, 'manacore_ad' ) && false === strpos( $src, 'ads_positions' ), 'کلید یا شورت‌کد تبلیغاتی در پنل نمانده است' );

/* ---------------------------------------------------------------
 * ۵) ابزارهای گروهی لینک در تب «وضعیت و ابزارها»
 * ------------------------------------------------------------ */

echo "\n=== ۵) ابزارهای نگه‌داشت لینک ===\n";

$_GET['tab'] = 'tools';

mc_ok( false !== strpos( $panels['tools'], 'نگه‌داشت لینک‌ها' ), 'بخش «نگه‌داشت لینک‌ها» در تب ابزارها هست' );
mc_ok( 3 === substr_count( $panels['tools'], 'value="manacore_link_tool"' ), 'سه فرم ابزار لینک رندر می‌شود', (string) substr_count( $panels['tools'], 'value="manacore_link_tool"' ) );

foreach ( array( 'domain', 'copy', 'audit' ) as $tool ) {
	mc_ok(
		false !== strpos( $panels['tools'], 'manacore_link_tool_' . $tool ),
		"نانِس ابزار «{$tool}» در فرم هست"
	);
}

mc_ok( false !== strpos( $panels['tools'], 'value="append"' ) && false !== strpos( $panels['tools'], 'value="replace"' ), 'حالت‌های کپی گروه لینک در فرم هستند' );
mc_ok( false !== strpos( $panels['tools'], 'name="apply"' ), 'گزینه‌ی «اعمال کن» برای جایگزینی پیشوند هست' );

/* نتیجه‌ی پیش‌نمایش جایگزینی: باید کارت آمار و پیام «هیچ چیزی نوشته نشد» بیاید. */
$GLOBALS['mc_transients']['manacore_link_tool_result_1'] = array(
	'tool'      => 'domain',
	'ok'        => true,
	'reason'    => '',
	'dry_run'   => true,
	'scanned'   => 12,
	'posts'     => 3,
	'links'     => 5,
	'truncated' => false,
	'samples'   => array(
		array( 'post_id' => 11, 'title' => 'فیلم تستی', 'label' => 'دانلود', 'from' => 'https://old.example/a.mkv', 'to' => 'https://new.example/a.mkv' ),
	),
);

ob_start();
Settings::instance()->render();
$result_html = (string) ob_get_clean();

mc_ok( false !== strpos( $result_html, 'لینک تغییرکرده' ), 'کارت نتیجه‌ی ابزار لینک رندر می‌شود' );
mc_ok( false !== strpos( $result_html, 'هیچ چیزی نوشته نشد' ), 'پیام «پیش‌نمایش بود» دیده می‌شود' );
mc_ok( false !== strpos( $result_html, 'manacore-link-report' ), 'جدول ردیف‌های تغییر در گزارش می‌آید' );
mc_ok( false !== strpos( $result_html, 'فیلم تستی' ), 'عنوان نوشته در گزارش می‌آید' );

/* نتیجه پس از نمایش پاک می‌شود (رندر دوباره نباید همان را نشان دهد). */
ob_start();
Settings::instance()->render();
$second_html = (string) ob_get_clean();

mc_ok( false === strpos( $second_html, 'manacore-link-report' ), 'نتیجه پس از نمایش یک‌بار پاک می‌شود' );

/* نتیجه‌ی بازرسی: برچسب فارسی ایرادها و پیام دلیل خطا. */
$GLOBALS['mc_transients']['manacore_link_tool_result_1'] = array(
	'tool'      => 'audit',
	'ok'        => true,
	'reason'    => '',
	'scanned'   => 4,
	'truncated' => false,
	'totals'    => array( 'bad-url' => 1, 'no-quality' => 2, 'no-size' => 0, 'dup-quality' => 0, 'unknown-file' => 0 ),
	'rows'      => array(
		array( 'post_id' => 11, 'title' => 'فیلم تستی', 'issue' => 'no-quality', 'label' => 'دانلود', 'quality' => '', 'url' => 'https://new.example/a.mkv' ),
	),
);

ob_start();
Settings::instance()->render();
$audit_html = (string) ob_get_clean();

mc_ok( false !== strpos( $audit_html, 'بی‌کیفیت' ), 'برچسب فارسی ایراد در گزارش بازرسی می‌آید' );
mc_ok( false !== strpos( $audit_html, 'نشانی نامعتبر' ), 'همه‌ی دسته‌های ایراد در کارت آمار می‌آیند' );

/* دلیل خطا: باید نشان هشدار با متن فارسی بیاید، نه پیام خام. */
$GLOBALS['mc_transients']['manacore_link_tool_result_1'] = array(
	'tool'   => 'domain',
	'ok'     => false,
	'reason' => 'bad-from',
);

ob_start();
Settings::instance()->render();
$error_html = (string) ob_get_clean();

mc_ok( false !== strpos( $error_html, 'معتبر نیست' ), 'دلیل خطای ابزار لینک به فارسی نمایش داده می‌شود' );
mc_ok( false !== strpos( $error_html, 'is-warn' ), 'خطا با نشان هشدار نمایش داده می‌شود' );

/* ---------------------------------------------------------------
 * هم‌ترازی کلید‌ها: هر کلیدی که در `keys_by_tab()` ثبت شده باید در
 * `sanitize()` هم پاک‌سازی شود؛ وگرنه فیلدی در فرم ذخیره نمی‌شود.
 * ------------------------------------------------------------ */

$tab_keys = Settings::keys_by_tab();
$all_keys = Settings::option_keys();

mc_ok( count( $all_keys ) === count( array_unique( $all_keys ) ), 'هیچ کلید تنظیماتی دو بار در تب‌ها ثبت نشده است', (string) count( $all_keys ) );

foreach ( $tab_keys as $tab_slug => $keys ) {
	/* ورودی ساختگی: همه‌ی کلیدهای همان تب با مقدار ۱. */
	$dummy = array( '_tab' => $tab_slug );

	foreach ( $keys as $key ) {
		$dummy[ $key ] = '1';
	}

	$clean = Settings::instance()->sanitize( $dummy );

	$missing = array();

	foreach ( $keys as $key ) {
		if ( ! array_key_exists( $key, $clean ) ) {
			$missing[] = $key;
		}
	}

	mc_ok( array() === $missing, 'هر کلید تب «' . $tab_slug . '» در sanitize() پاک‌سازی می‌شود', implode( '، ', $missing ) );
}

/* پاک‌سازی یک تب نباید کلید تب دیگر را بازنویسی کند. */
$watch_only = Settings::instance()->sanitize(
	array(
		'_tab'               => 'watch',
		'download_notice_text' => 'یادداشت',
		'items_per_page'     => '99',
	)
);

mc_ok( ! array_key_exists( 'items_per_page', $watch_only ), 'ذخیره‌ی یک تب، تنظیمات تب دیگر را بازنویسی نمی‌کند' );

/* ---------------------------------------------------------------
 * جمع‌بندی
 * ------------------------------------------------------------ */

echo "\n" . str_repeat( '-', 46 ) . "\n";
echo 'نتیجه: ' . $GLOBALS['mc_pass'] . " موفق، " . $GLOBALS['mc_fail'] . " ناموفق\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
