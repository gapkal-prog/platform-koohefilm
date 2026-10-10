<?php
/**
 * آزمون سمت سرور برای «باکس دانلود» و «صفحه‌ی پخش».
 *
 * این آزمون سه قابلیت تازه (و سه باگ که دیگر نباید برگردند) را می‌سنجد:
 *
 *   ۱. `Templates::resolve_mode()` — تفکیک حالت فیلم/سریال/قسمت از نوع پست،
 *      تا جدول دانلود فیلم و سریال منطق و ظاهر جدا داشته باشند.
 *   ۲. `Templates::filter_link_seasons()` — پیش‌تر در `links()` صدا زده
 *      می‌شد ولی **تعریف نشده بود**؛ هر بلوکی که فیلتر «نوع» یا «کیفیت»
 *      داشت خطای مرگبار می‌گرفت.
 *   ۳. `Player::resolve_target()` — وقتی لینک‌ها روی قسمت‌ها ثبت شده‌اند و
 *      کاربر از صفحه‌ی سریال «پخش» می‌زند، باید پستِ دارای لینک پیدا شود؛
 *      وگرنه پلیر خالی می‌ماند.
 *
 * اجرا: php wp/tests/test-download-watch.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_URL', 'http://example.test/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );
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
 * پوسته‌ی وردپرس (کمینه و قابل تنظیم)
 * ------------------------------------------------------------ */

/**
 * مخزن پست‌ها/متاها برای آزمون.
 *
 * ساختار هر پست:
 *   type   => movie|series|anime|episode|page
 *   status => publish|draft
 *   meta   => array( key => value )
 */
$GLOBALS['mc_posts'] = array();
$GLOBALS['mc_options'] = array();

function mc_post( $id, $type, $meta = array(), $status = 'publish' ) {
	$GLOBALS['mc_posts'][ $id ] = array( 'type' => $type, 'status' => $status, 'meta' => $meta );
}

function mc_links( $groups ) {
	return wp_json_encode( $groups );
}

/* ---- توابع پایه ---- */
function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_attr__( $t, $d = '' ) { return $t; }
function esc_html_e( $t, $d = '' ) { echo $t; }
function esc_attr_e( $t, $d = '' ) { echo htmlspecialchars( (string) $t, ENT_QUOTES ); }
function _n( $s, $p, $n, $d = '' ) { return 1 === (int) $n ? $s : $p; }
function apply_filters( $tag, $value ) { return $value; }
function add_filter() {}
function add_action() {}
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_\- ]/', '', (string) $v ); }
function is_wp_error( $v ) { return false; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return (string) $v; }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function wp_login_url( $r = '' ) { return 'http://example.test/wp-login.php'; }
function wp_rand( $min = 0, $max = 0 ) { return 12345; }
function wp_slash( $v ) { return $v; }
function wp_parse_url( $url, $component = -1 ) {
	return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
}
function esc_url_raw( $v ) { return (string) $v; }
function is_user_logged_in() { return false; }
function get_current_user_id() { return 0; }
function wp_unslash( $v ) { return $v; }
function wp_doing_ajax() { return false; }
$GLOBALS['mc_is_admin'] = false;
function is_admin() { return (bool) $GLOBALS['mc_is_admin']; }
function get_the_ID() { return 0; }
function get_the_title( $id = 0 ) {
	/* مثل وردپرس: هم شناسه و هم شیء پست را می‌پذیرد. */
	return 'عنوان ' . ( is_object( $id ) ? (int) $id->ID : (int) $id );
}
function get_permalink( $id = 0 ) { return 'http://example.test/?p=' . (int) $id; }
function get_post_field( $f, $id = 0 ) { return ''; }
function get_edit_post_link( $id = 0, $ctx = 'display' ) { return 'http://example.test/wp-admin/post.php?post=' . (int) $id; }
/* دسترسی ویرایش، از بیرون آزمون قابل کنترل است (راهنمای مدیر در باکس خالی). */
$GLOBALS['mc_can_edit'] = false;
function current_user_can( $cap, $id = 0 ) { return (bool) $GLOBALS['mc_can_edit']; }
function add_query_arg( $a, $url = '' ) {
	if ( is_array( $a ) ) {
		$parts = array();
		foreach ( $a as $k => $v ) {
			$parts[] = rawurlencode( $k ) . '=' . $v;
		}
		$qs = implode( '&', $parts );
	} else {
		$qs = rawurlencode( $a ) . '=' . $url;
		$url = func_get_args()[2] ?? '';
	}
	return (string) $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . $qs;
}

/* ---- تنظیمات و متا ---- */
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['mc_options'] ) ? $GLOBALS['mc_options'][ $k ] : $d;
}
function update_option( $k, $v ) { $GLOBALS['mc_options'][ $k ] = $v; return true; }
$GLOBALS['mc_transients'] = array();
function get_transient( $k ) { return $GLOBALS['mc_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['mc_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['mc_transients'][ $k ] ); return true; }
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['mc_posts'][ (int) $id ]['meta'][ $key ] = $value;
	return true;
}
function do_action() {}
function wp_salt( $scheme = '' ) { return 'mc-test-salt'; }
function current_time( $t = 'mysql' ) { return 'mysql' === $t ? '2026-10-08 12:00:00' : '2026-10-08'; }

/**
 * نمونه‌ی کمینه‌ی $wpdb برای ثبت پرس‌وجوهای آماری.
 */
class MC_Watch_Wpdb {
	public $prefix  = 'wp_';
	public $queries = array();

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
	public function query( $sql ) { $this->queries[] = $sql; return 1; }
}

$GLOBALS['wpdb'] = new MC_Watch_Wpdb();
function mc_post_id( $post ) { return is_object( $post ) ? (int) $post->ID : (int) $post; }
function get_post_type( $post = 0 ) { return $GLOBALS['mc_posts'][ mc_post_id( $post ) ]['type'] ?? ''; }
function get_post_status( $post = 0 ) { return $GLOBALS['mc_posts'][ mc_post_id( $post ) ]['status'] ?? ''; }
function get_post_meta( $id, $key = '', $single = false ) {
	$meta = $GLOBALS['mc_posts'][ (int) $id ]['meta'] ?? array();
	$v    = $meta[ $key ] ?? ( $single ? '' : array() );
	return $v;
}
function get_page_by_path( $path, $output = OBJECT, $type = 'page' ) {
	foreach ( $GLOBALS['mc_posts'] as $id => $post ) {
		if ( 'page' !== $post['type'] ) { continue; }
		if ( ( $post['meta']['slug'] ?? '' ) === $path ) {
			$o = new stdClass();
			$o->ID = $id;
			return $o;
		}
	}
	return null;
}

/**
 * get_posts — فقط همان شکلی که `Player::find_episode()` می‌سازد.
 *
 * فیلترهای پشتیبانی‌شده: post_type، meta_query ساده (parent/season/episode).
 */
function get_posts( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['mc_posts'] as $id => $post ) {
		if ( ( $args['post_type'] ?? '' ) && $post['type'] !== $args['post_type'] ) { continue; }
		if ( 'publish' !== $post['status'] ) { continue; }

		$match = true;
		foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
			if ( ! isset( $clause['key'] ) ) { continue; }
			$value = $post['meta'][ $clause['key'] ] ?? null;
			if ( (string) $value !== (string) $clause['value'] ) { $match = false; break; }
		}
		if ( ! $match ) { continue; }

		$obj = new stdClass();
		$obj->ID = $id;
		$out[]   = $obj;
	}

	usort( $out, function ( $a, $b ) {
		$a_num = (int) ( $GLOBALS['mc_posts'][ $a->ID ]['meta']['manacore_episode_number'] ?? 0 );
		$b_num = (int) ( $GLOBALS['mc_posts'][ $b->ID ]['meta']['manacore_episode_number'] ?? 0 );
		return $a_num <=> $b_num;
	} );

	$limit = (int) ( $args['posts_per_page'] ?? 0 );
	if ( $limit > 0 ) { $out = array_slice( $out, 0, $limit ); }

	return 'ids' === ( $args['fields'] ?? '' ) ? wp_list_pluck_ids( $out ) : $out;
}

function wp_list_pluck_ids( $list ) {
	$ids = array();
	foreach ( $list as $item ) { $ids[] = $item->ID; }
	return $ids;
}

/* ---- بارگذاری افزونه ---- */
require_once MANACORE_PATH . 'includes/functions.php';
require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-links.php';
/* راهنمای مدیرِ باکس خالی، لنگر متاباکس را از همین کلاس می‌گیرد. */
require_once MANACORE_PATH . 'includes/class-links-admin.php';
require_once MANACORE_PATH . 'includes/class-block-data.php';
require_once MANACORE_PATH . 'includes/class-block-support.php';
require_once MANACORE_PATH . 'includes/class-templates.php';
require_once MANACORE_PATH . 'includes/class-player.php';
/* شمارش تماشا (بخش ۷) روی همین دو کلاس می‌نشیند. */
require_once MANACORE_PATH . 'includes/class-install.php';
require_once MANACORE_PATH . 'includes/class-ratings.php';

/* ---------------------------------------------------------------
 * داده‌ی آزمون
 * ------------------------------------------------------------ */

$movie_links  = array(
	array(
		'id'      => 'g1',
		'title'   => 'دانلود فیلم',
		'season'  => 0,
		'quality' => '1080p',
		'items'   => array(
			array( 'id' => 'i1', 'label' => 'دانلود', 'url' => 'https://example.test/a.mp4', 'type' => 'direct' ),
			array( 'id' => 'i2', 'label' => 'پخش', 'url' => 'https://example.test/a-stream', 'type' => 'stream' ),
		),
	),
	array(
		'id'      => 'g2',
		'title'   => 'کیفیت کم',
		'season'  => 0,
		'quality' => '480p',
		'items'   => array(
			array( 'id' => 'i3', 'label' => 'پخش', 'url' => 'https://example.test/b-stream', 'type' => 'stream' ),
		),
	),
);

$series_links = array(
	array(
		'id'      => 's1',
		'title'   => 'بسته‌ی کامل فصل ۱',
		'season'  => 1,
		'quality' => '1080p',
		'items'   => array(
			array( 'id' => 'p1', 'label' => 'دانلود کامل', 'url' => 'https://example.test/s1.zip', 'type' => 'direct' ),
		),
	),
	array(
		'id'      => 's2',
		'title'   => 'بسته‌ی کامل فصل ۲',
		'season'  => 2,
		'quality' => '1080p',
		'items'   => array(
			array( 'id' => 'p2', 'label' => 'دانلود کامل', 'url' => 'https://example.test/s2.zip', 'type' => 'direct' ),
		),
	),
);

$episode_links = array(
	array(
		'id'      => 'e1',
		'title'   => 'قسمت دوم',
		'season'  => 1,
		'quality' => '720p',
		'items'   => array(
			array( 'id' => 'x1', 'label' => 'پخش', 'url' => 'https://example.test/ep2-stream', 'type' => 'stream' ),
		),
	),
);

mc_post( 4, 'movie', array( 'manacore_links' => mc_links( $movie_links ) ) );
mc_post( 8, 'series', array( 'manacore_links' => mc_links( $series_links ) ) );
mc_post( 9, 'anime', array( 'manacore_links' => '' ) );
mc_post( 10, 'series', array( 'manacore_links' => '' ) );        // سریال بدون لینک روی خودش
mc_post( 11, 'episode', array(
	'manacore_parent_title'  => 10,
	'manacore_season_number' => 1,
	'manacore_episode_number' => 1,
	'manacore_links'         => mc_links( $episode_links ),
) );
mc_post( 12, 'episode', array(
	'manacore_parent_title'   => 10,
	'manacore_season_number'  => 1,
	'manacore_episode_number' => 2,
	'manacore_links'          => '',
) );
mc_post( 5, 'page', array( 'slug' => 'watch' ) );

/*
 * گزینه پیش از هر رندر تنظیم می‌شود: `Player::page_id()` نتیجه را در همان
 * درخواست کش می‌کند (یک `get_page_by_path` کمتر در هر جدول دانلود)، پس
 * ترتیب سنجه‌ها باید مثل ترتیب واقعیِ یک درخواست باشد.
 */
update_option( 'manacore_watch_page', 5 );

/* ---------------------------------------------------------------
 * ۱) موتور لینک
 * ------------------------------------------------------------ */

echo "\n=== ۱) موتور لینک (Links) ===\n";

mc_ok( \ManaCore\Core\Links::count( 4 ) > 0, 'شمارش لینک فیلم > صفر است' );
mc_ok( 0 === \ManaCore\Core\Links::count( 9 ), 'پست بدون لینک شمارش صفر می‌دهد' );

$by_season = \ManaCore\Core\Links::by_season( 8 );
mc_ok( isset( $by_season[1], $by_season[2] ), 'گروه‌ها به تفکیک فصل دسته‌بندی می‌شوند', implode( ',', array_keys( $by_season ) ) );

/* ---------------------------------------------------------------
 * ۲) حالت‌های باکس دانلود
 * ------------------------------------------------------------ */

echo "\n=== ۲) حالت باکس دانلود (Templates) ===\n";

mc_ok( 'movie' === \ManaCore\Core\Templates::resolve_mode( 'auto', 4 ), 'فیلم → حالت movie' );
mc_ok( 'series' === \ManaCore\Core\Templates::resolve_mode( 'auto', 8 ), 'سریال → حالت series' );
mc_ok( 'series' === \ManaCore\Core\Templates::resolve_mode( 'auto', 9 ), 'انیمه هم → حالت series' );
mc_ok( 'episode' === \ManaCore\Core\Templates::resolve_mode( 'auto', 11 ), 'قسمت → حالت episode' );
mc_ok( 'series' === \ManaCore\Core\Templates::resolve_mode( 'series', 4 ), 'حالت صریح بر نوع پست مقدم است' );
mc_ok( 'movie' === \ManaCore\Core\Templates::resolve_mode( 'نویسه‌ی نامعتبر', 4 ), 'حالت نامعتبر به auto برمی‌گردد' );

/* فیلم: جدول کیفیت با نشان «بسته‌ی فصل» نباید بیاید. */
$movie_html = \ManaCore\Core\Templates::links( 4 );
mc_ok( false !== strpos( $movie_html, 'class="download-section' ), 'خروجی فیلم بخش .download-section دارد' );
mc_ok( false !== strpos( $movie_html, 'data-mode="movie"' ), 'حالت خروجی فیلم movie است' );
mc_ok( false === strpos( $movie_html, 'is-series' ), 'فیلم کلاس is-series نمی‌گیرد' );
mc_ok( false !== strpos( $movie_html, 'لینک‌های دانلود و پخش' ), 'سرصفحه‌ی پیش‌فرض فیلم درست است' );

/* سریال: بسته‌های فصل با برچسب مناسب و سرصفحه‌ی مخصوص. */
$series_html = \ManaCore\Core\Templates::links( 8 );
mc_ok( false !== strpos( $series_html, 'is-series' ), 'خروجی سریال کلاس is-series دارد' );
mc_ok( false !== strpos( $series_html, 'data-mode="series"' ), 'حالت خروجی سریال series است' );
mc_ok( false !== strpos( $series_html, 'دانلود کامل فصل‌ها' ), 'سرصفحه‌ی پیش‌فرض سریال مخصوص خودش است' );
mc_ok( false !== strpos( $series_html, 'دانلود کامل' ), 'نام خودِ لینک روی سطر بسته می‌آید' );
mc_ok( false === strpos( $series_html, 'بسته‌ی کامل فصل ۱' ), 'عنوان گروه (بی‌نام لینک) روی سطرها نمی‌آید' );

/* قسمت: جدول کیفیت همان قسمت. */
$episode_html = \ManaCore\Core\Templates::links( 11 );
mc_ok( false !== strpos( $episode_html, 'data-mode="episode"' ), 'حالت خروجی قسمت episode است' );
mc_ok( false !== strpos( $episode_html, 'لینک‌های این قسمت' ), 'سرصفحه‌ی پیش‌فرض قسمت درست است' );

/* پست غیرفعال‌شده نباید چیزی چاپ کند. */
mc_post( 13, 'movie', array( 'manacore_links' => mc_links( $movie_links ), 'manacore_disable_links' => '1' ) );
mc_ok( '' === \ManaCore\Core\Templates::links( 13 ), 'manacore_disable_links خروجی را خالی می‌کند' );

/* ---------------------------------------------------------------
 * ۳) فیلتر نوع/کیفیت (باگ متد تعریف‌نشده)
 * ------------------------------------------------------------ */

echo "\n=== ۳) فیلتر نوع و کیفیت (filter_link_seasons) ===\n";

$filtered = \ManaCore\Core\Templates::filter_link_seasons(
	\ManaCore\Core\Links::by_season( 4 ),
	array( 'stream' ),
	array()
);
$row_count = 0;
foreach ( $filtered as $groups ) { $row_count += count( $groups ); }
mc_ok( 2 === $row_count, 'با فیلتر «فقط پخش»، هر دو گروه باقی می‌مانند', 'گروه‌ها: ' . $row_count );

$first_item_type = $filtered[0][0]['items'][0]['type'];
mc_ok( 'stream' === $first_item_type, 'نوع لینک باقی‌مانده stream است' );

$filtered_quality = \ManaCore\Core\Templates::filter_link_seasons(
	\ManaCore\Core\Links::by_season( 4 ),
	array(),
	array( '480p' )
);
$quality_rows = 0;
foreach ( $filtered_quality as $groups ) { $quality_rows += count( $groups ); }
mc_ok( 1 === $quality_rows, 'فیلتر کیفیت فقط گروه 480p را نگه می‌دارد' );

$empty = \ManaCore\Core\Templates::filter_link_seasons(
	\ManaCore\Core\Links::by_season( 4 ),
	array( 'torrent' ),
	array()
);
mc_ok( array() === $empty, 'فیلتری که هیچ لینکی ندارد، فصل‌ها را خالی می‌کند' );

/* خروجی زنده با فیلتر: نباید خطا بدهد و باید گروه‌ها را بچیند. */
mc_ok(
	method_exists( 'ManaCore\\Core\\Templates', 'filter_link_seasons' ),
	'متد filter_link_seasons() در کلاس Templates تعریف شده است (باگ «متد تعریف‌نشده»)'
);

$filtered_html = \ManaCore\Core\Templates::links( 4, array( 'types' => array( 'stream' ) ) );
mc_ok( false !== strpos( $filtered_html, 'download-row' ), 'رندر با فیلتر نوع، خطای مرگبار نمی‌دهد' );
mc_ok( false !== strpos( $filtered_html, 'is-secondary' ), 'در فیلتر «فقط پخش» کنش پخش باقی می‌ماند' );

/* ---------------------------------------------------------------
 * ۴) هدف پخش (Player)
 * ------------------------------------------------------------ */

echo "\n=== ۴) هدف پخش و صفحه‌ی پخش (Player) ===\n";

mc_ok( true === \ManaCore\Core\Player::has_sources( 11 ), 'قسمتِ دارای لینک، منبع پخش دارد' );
mc_ok( false === \ManaCore\Core\Player::has_sources( 12 ), 'قسمت بدون لینک، منبع پخش ندارد' );

mc_ok( 4 === \ManaCore\Core\Player::resolve_target( 4 ), 'فیلم با لینک، خودش را برمی‌گرداند' );
mc_ok( 8 === \ManaCore\Core\Player::resolve_target( 8, 1 ), 'سریال با بسته‌ی فصل، خودش را برمی‌گرداند' );
mc_ok( 11 === \ManaCore\Core\Player::resolve_target( 10, 1, 1 ), 'سریال بی‌لینک → قسمتِ دارای لینک پیدا می‌شود' );
mc_ok( 12 === \ManaCore\Core\Player::resolve_target( 12, 0, 0 ), 'قسمت بی‌لینک و سریال بی‌لینک → خودِ قسمت (پیام «لینک ندارد») نه محتوای اشتباه' );
mc_ok( 10 === \ManaCore\Core\Player::resolve_target( 10, 2, 5 ), 'سریال با قسمتِ خواسته‌نشده، نخستین قسمتِ دارای لینک را می‌دهد' );

/* صفحه‌ی پخش: گزینه‌ی ثبت‌شده مقدم است. */
mc_ok( 5 === \ManaCore\Core\Player::page_id(), 'شناسه‌ی برگه‌ی پخش از گزینه خوانده می‌شود' );
mc_ok( false !== strpos( \ManaCore\Core\Player::url_for( 4 ), 'manacore_id=4' ), 'نشانی پخش شناسه‌ی اثر را دارد' );
mc_ok( false !== strpos( \ManaCore\Core\Player::url_for( 11 ), 'manacore_id=11' ), 'نشانی پخش برای قسمت هم ساخته می‌شود' );
mc_ok( false !== strpos( \ManaCore\Core\Player::url_for( 11, array( 'season' => 1 ) ), 'season=1' ), 'پارامترهای افزوده به نشانی پخش می‌چسبند' );
mc_ok( '' === \ManaCore\Core\Player::url_for( 0 ), 'بدون شناسه‌ی اثر، نشانی ساخته نمی‌شود' );

/* وضعیت صفحه برای پنل مدیریت. */
$status = \ManaCore\Core\Player::page_status();
mc_ok( 5 === $status['option'], 'گزارش وضعیت، گزینه‌ی ذخیره‌شده را نشان می‌دهد' );
mc_ok( true === $status['exists'] && true === $status['published'], 'گزارش وضعیت، برگه‌ی موجود/منتشرشده می‌دهد' );

/* `?manacore_id=قسمت` باید پذیرفته شود (پیش‌تر رد می‌شد و پلیر خالی می‌ماند). */
$_GET['manacore_id'] = 11;
mc_ok( 11 === \ManaCore\Core\Player::watched_id(), 'شناسه‌ی قسمت در صفحه‌ی پخش پذیرفته می‌شود' );
$_GET['manacore_id'] = 5;
mc_ok( 0 === \ManaCore\Core\Player::watched_id(), 'برگه‌ی معمولی به‌عنوان اثر پخش پذیرفته نمی‌شود' );
unset( $_GET['manacore_id'] );

/* ---------------------------------------------------------------
 * ۵) منابع HLS و نوع رسانه
 * ------------------------------------------------------------ */

echo "\n=== ۵) منابع HLS و نوع رسانه ===\n";

mc_ok( true === \ManaCore\Core\Player::is_hls_url( 'https://cdn.example/movie/1080/index.m3u8' ), 'نشانی m3u8 فهرست‌پخش شناخته می‌شود' );
mc_ok( true === \ManaCore\Core\Player::is_hls_url( 'https://cdn.example/movie/master.M3U8?token=abc' ), 'پسوند با حروف بزرگ و رشته‌ی پرس‌وجو هم پذیرفته می‌شود' );
mc_ok( false === \ManaCore\Core\Player::is_hls_url( 'https://cdn.example/movie/1080.mp4' ), 'فایل mp4 فهرست‌پخش نیست' );
mc_ok( false === \ManaCore\Core\Player::is_hls_url( 'https://cdn.example/movie/index.m3u8.mp4' ), '«m3u8» میان نام فایل، HLS شمرده نمی‌شود' );
mc_ok( false === \ManaCore\Core\Player::is_hls_url( '' ), 'نشانی خالی HLS نیست' );

mc_ok( 'application/vnd.apple.mpegurl' === \ManaCore\Core\Player::mime_for( 'https://cdn.example/a/index.m3u8' ), 'نوع MIME فهرست‌پخش درست است' );
mc_ok( 'video/mp4' === \ManaCore\Core\Player::mime_for( 'https://cdn.example/a/x.mp4' ), 'نوع MIME فایل mp4 درست است' );
mc_ok( 'video/webm' === \ManaCore\Core\Player::mime_for( 'https://cdn.example/a/x.webm' ), 'نوع MIME فایل webm درست است' );
mc_ok( '' === \ManaCore\Core\Player::mime_for( 'https://cdn.example/a/x.mp4?token=1#t' ) || 'video/mp4' === \ManaCore\Core\Player::mime_for( 'https://cdn.example/a/x.mp4?token=1#t' ), 'نوع MIME فایل mp4 با پارامتر هم درست است' );
mc_ok( '' === \ManaCore\Core\Player::mime_for( 'https://player.example/embed/xyz' ), 'نشانی ناشناخته نوع MIME خالی می‌دهد' );

mc_ok( true === \ManaCore\Core\Player::has_hls( array( '1080p' => 'https://cdn.example/a.mp4', '720p' => 'https://cdn.example/b.m3u8' ) ), 'میان منبع‌ها یک HLS کافی است' );
mc_ok( false === \ManaCore\Core\Player::has_hls( array( '1080p' => 'https://cdn.example/a.mp4' ) ), 'منبع‌های فقط mp4 حالت HLS ندارند' );

mc_ok( false !== strpos( \ManaCore\Core\Player::hls_script_url(), 'assets/vendor/hls/hls.min.js' ), 'کتابخانه‌ی HLS از پوشه‌ی خود افزونه می‌آید' );

/* ---------------------------------------------------------------
 * ۶) باکس دانلود سریالی: لینک قسمت‌های فرزند
 * ------------------------------------------------------------ */

echo "\n=== ۶) باکس سریالی و لینک قسمت‌های فرزند ===\n";

/*
 * دو پست تازه در همین بخش ساخته می‌شوند تا سنجه‌های پیشین دست‌نخورده
 * بمانند: سریالی که هم بسته‌ی فصل دارد و هم لینک روی قسمت‌ها.
 */
$episode_pack_links = array(
	array(
		'id'      => 'ep1',
		'title'   => 'قسمت اول',
		'season'  => 1,
		'quality' => '1080p',
		'items'   => array(
			array( 'id' => 'd1', 'label' => 'دانلود', 'url' => 'https://example.test/ep1.mp4', 'type' => 'direct' ),
			array( 'id' => 's9', 'label' => 'پخش', 'url' => 'https://example.test/ep1-stream', 'type' => 'stream' ),
		),
	),
);

mc_post( 14, 'series', array( 'manacore_links' => mc_links( $series_links ) ) );
mc_post( 15, 'episode', array(
	'manacore_parent_title'   => 14,
	'manacore_season_number'  => 1,
	'manacore_episode_number' => 1,
	'manacore_links'          => mc_links( $episode_pack_links ),
) );

/* ۶.۱ جمع‌آوری لینک قسمت‌ها */
$episode_groups = \ManaCore\Core\Links::episode_groups( 10 );
mc_ok( isset( $episode_groups[1] ), 'گروه‌های قسمت‌ها به تفکیک فصل می‌آیند', 'فصل‌ها: ' . implode( ',', array_keys( $episode_groups ) ) );
mc_ok( 1 === count( $episode_groups[1] ), 'فقط قسمتِ دارای لینک گروه می‌سازد (قسمت ۱۲ لینک ندارد)' );
mc_ok( 11 === (int) $episode_groups[1][0]['owner'], 'مالک گروه شناسه‌ی خودِ قسمت است' );
mc_ok( 1 === (int) $episode_groups[1][0]['episode'], 'شماره‌ی قسمت روی گروه می‌آید' );
mc_ok( false === isset( $episode_groups[2] ), 'فصل بدون لینک ساخته نمی‌شود' );
mc_ok( array() === \ManaCore\Core\Links::episode_groups( 10, array( 'season' => 2 ) ), 'فیلتر فصل روی قسمت‌ها اعمال می‌شود' );
mc_ok( array() === \ManaCore\Core\Links::episode_groups( 0 ), 'شناسه‌ی صفر بدون کوئری، آرایه‌ی خالی می‌دهد' );

mc_ok( 1 === \ManaCore\Core\Links::episode_count( 10 ), 'شمارش لینک قسمت‌های یک سریال' );
mc_ok( 0 === \ManaCore\Core\Links::episode_count( 8 ), 'سریال بدون قسمت لینک‌دار صفر می‌دهد' );

/* ۶.۲ جمع شمارش */
mc_ok( 3 === \ManaCore\Core\Links::total( \ManaCore\Core\Links::by_season( 4 ) ), 'total شمارش لینک‌های یک ساختار فصل‌بندی‌شده را جمع می‌زند' );
mc_ok( 0 === \ManaCore\Core\Links::total( array() ), 'total روی آرایه‌ی خالی صفر می‌دهد' );

/* ۶.۳ انتخاب منبع */
mc_ok( 'both' === \ManaCore\Core\Templates::resolve_source( 'auto', 10, 'series' ), 'خودکار برای سریال، هر دو منبع را می‌آورد' );
mc_ok( 'post' === \ManaCore\Core\Templates::resolve_source( 'auto', 4, 'movie' ), 'خودکار برای فیلم، فقط لینک‌های خود اثر' );
mc_ok( 'episodes' === \ManaCore\Core\Templates::resolve_source( 'episodes', 8, 'series' ), 'منبع صریح «قسمت‌ها» پذیرفته می‌شود' );
mc_ok( 'both' === \ManaCore\Core\Templates::resolve_source( 'چیز نامعتبر', 8, 'series' ), 'منبع نامعتبر به auto برمی‌گردد (و برای سریال both می‌شود)' );

/* ۶.۴ باکس سریالی با لینک فقط روی قسمت‌ها (باگ «باکس خالی») */
$series_box = \ManaCore\Core\Templates::links( 10 );
mc_ok( '' !== trim( $series_box ), 'سریالی که فقط لینک قسمت دارد هم باکس دانلود می‌گیرد' );
mc_ok( false !== strpos( $series_box, 'data-mode="series"' ), 'حالت باکس سریال است' );
mc_ok( false !== strpos( $series_box, 'قسمت ۱' ), 'نشان ردیف قسمت روی جدول می‌آید' );
mc_ok( false !== strpos( \ManaCore\Core\Templates::links( 14 ), 'data-manacore-download="15"' ), 'کنش دانلود ردیفِ قسمت، مالک را خودِ قسمت می‌داند (امضا و شمارش روی قسمت)' );
mc_ok( false !== strpos( $series_box, 'manacore_id=11' ), 'دکمه‌ی پخش ردیف به صفحه‌ی پخشِ همان قسمت می‌رود' );
mc_ok( false !== strpos( $series_box, 'season=1' ) && false !== strpos( $series_box, 'episode=1' ), 'فصل و قسمت در نشانی پخش ردیف می‌آید' );
mc_ok( false !== strpos( $series_box, '1 لینک' ), 'شمارش سرصفحه مجموع ردیف‌های رندرشده است (نه فقط لینک‌های سریال)' );

/* ۶.۵ منبع و برچسب دلخواه */
mc_ok( '' === \ManaCore\Core\Templates::links( 10, array( 'source' => 'post' ) ), 'با منبع «فقط خود اثر»، سریالِ بی‌لینک باکس نمی‌سازد' );
$only_episodes = \ManaCore\Core\Templates::links( 10, array( 'source' => 'episodes', 'episode_label' => 'بخش %s' ) );
mc_ok( false !== strpos( $only_episodes, 'بخش ۱' ), 'برچسب دلخواه قسمت از ویژگی بلوک می‌آید' );
$pack_only = \ManaCore\Core\Templates::links( 8, array( 'source' => 'post' ) );
mc_ok( false !== strpos( $pack_only, 'دانلود کامل' ), 'بسته‌های فصل با منبع «خود اثر» دست‌نخورده می‌مانند' );

/* ۶.۶ ادغام بسته‌ها و لینک قسمت‌ها در یک جدول */
$merged = \ManaCore\Core\Templates::links( 14 );
/* هر لینک یک ردیف است: دو بسته‌ی فصل + دو لینکِ قسمت (دانلود و پخش). */
mc_ok( 4 === substr_count( $merged, 'class="download-row"' ), 'باکس سریال، دو بسته‌ی فصل و لینک‌های قسمت را کنار هم می‌آورد', 'ردیف‌ها: ' . substr_count( $merged, 'class="download-row"' ) );
mc_ok( 2 === substr_count( $merged, 'data-season="' ), 'برای هر فصل یک تب ساخته می‌شود' );
mc_ok( 2 === substr_count( $merged, 'data-season-panel=' ), 'هر تب پنل خودش را دارد' );
mc_ok( false !== strpos( $merged, 'دانلود کامل' ) && false !== strpos( $merged, 'قسمت ۱' ), 'نشان بسته و نشان قسمت هر دو در یک جدول‌اند' );
mc_ok( 3 === substr_count( $merged, 'data-manacore-download=' ), 'هر ردیفِ دانلود (دو بسته + یک قسمت) کنش خودش را دارد', 'کنش‌ها: ' . substr_count( $merged, 'data-manacore-download=' ) );

/* ۶.۷ فیلترها روی نتیجه‌ی ادغام‌شده هم می‌نشینند */
mc_ok( '' === \ManaCore\Core\Templates::links( 10, array( 'types' => array( 'direct' ) ) ), 'فیلتری که هیچ لینکی از قسمت نمی‌ماند، باکس را خالی می‌کند' );
$only_stream = \ManaCore\Core\Templates::links( 10, array( 'types' => array( 'stream' ) ) );
mc_ok( false !== strpos( $only_stream, 'is-secondary' ), 'با فیلتر «فقط پخش»، کنش پخش در ردیف قسمت می‌ماند' );

/* ۶.۸·الف قسمت‌های شماره‌دار روی خودِ سریال (ورود گروهی/متاباکس) */
$GLOBALS['mc_can_edit'] = false;
$GLOBALS['mc_is_admin'] = false;

$numbered_links = array(
	array(
		'id'      => 'nb1',
		'title'   => 'فصل ۱',
		'season'  => 1,
		'quality' => '1080p',
		'size'    => '4GB',
		'items'   => array(
			array( 'id' => 'nb1a', 'label' => 'دانلود کامل فصل', 'url' => 'https://example.test/nb-pack.zip', 'type' => 'direct' ),
			array( 'id' => 'nb1b', 'label' => 'قسمت ۱', 'url' => 'https://example.test/nb-s1e1.mp4', 'type' => 'direct', 'episode' => 1, 'quality' => '720p', 'size' => '350MB' ),
			array( 'id' => 'nb1c', 'label' => 'قسمت ۲', 'url' => 'https://example.test/nb-s1e2.mp4', 'type' => 'stream', 'episode' => 2 ),
		),
	),
	array(
		'id'      => 'nb2',
		'title'   => 'فصل ۲',
		'season'  => 2,
		'quality' => '720p',
		'size'    => '2GB',
		'items'   => array(
			array( 'id' => 'nb2a', 'label' => 'قسمت ۱', 'url' => 'https://example.test/nb-s2e1.mp4', 'type' => 'direct', 'episode' => 1 ),
		),
	),
);

mc_post( 16, 'series', array( 'manacore_links' => mc_links( $numbered_links ) ) );
/* قسمت ۱ فصل ۱ خودِ پست قسمت لینک دارد؛ پس ردیف شماره‌دار سریال برایش تکرار نمی‌شود. */
mc_post( 18, 'episode', array(
	'manacore_parent_title'   => 16,
	'manacore_season_number'  => 1,
	'manacore_episode_number' => 1,
	'manacore_links'          => mc_links( array( array(
		'id'      => 'nb18',
		'title'   => 'قسمت یک',
		'season'  => 1,
		'quality' => '1080p',
		'items'   => array( array( 'id' => 'nb18a', 'label' => 'دانلود', 'url' => 'https://example.test/nb18.mp4', 'type' => 'direct' ) ),
	) ) ),
) );

$own_numbered = \ManaCore\Core\Links::numbered_groups( 16 );
mc_ok( 2 === count( $own_numbered[1] ) && 1 === count( $own_numbered[2] ), 'هر آیتم شماره‌دار یک ردیف قسمت می‌شود (فصل ۱: ۲، فصل ۲: ۱)' );
mc_ok( 16 === (int) $own_numbered[1][0]['owner'] && 1 === (int) $own_numbered[1][0]['episode'], 'مالک ردیف شماره‌دار خودِ سریال است و شماره روی ردیف می‌آید' );
mc_ok( '720p' === $own_numbered[1][0]['quality'] && '350MB' === $own_numbered[1][0]['size'], 'کیفیت و حجم خودِ آیتم بر مقادیر گروه مقدم‌اند' );
mc_ok( '4GB' === $own_numbered[1][1]['size'] && '1080p' === $own_numbered[1][1]['quality'], 'آیتم بدون کیفیت/حجم، از گروهش ارث می‌برد' );
mc_ok( '2GB' === $own_numbered[2][0]['size'], 'ارث‌بری حجم از گروه فصل ۲' );

$packs_16 = \ManaCore\Core\Links::season_packs( 16 );
mc_ok( 1 === count( $packs_16[1] ) && 1 === count( $packs_16[1][0]['items'] ), 'بسته‌ی فصل فقط آیتم‌های بی‌شماره را نگه می‌دارد' );
mc_ok( ! isset( $packs_16[2] ), 'گروهی که همه‌اش شماره‌دار است، از فهرست بسته‌ها کنار می‌رود' );

$both_html = \ManaCore\Core\Templates::links( 16, array( 'source' => 'both' ) );
mc_ok( 3 === substr_count( $both_html, 'class="episode-card' ), 'منبع both: قسمت ۱ فصل ۱ از پست قسمت، قسمت ۲ فصل ۱ و قسمت ۱ فصل ۲ از سریال', 'کارت‌ها: ' . substr_count( $both_html, 'class="episode-card' ) );
mc_ok( 2 === substr_count( $both_html, 'class="episode-card expanded"' ), 'اولین قسمت هر فصل به‌صورت پیش‌فرض باز است' );
mc_ok( false !== strpos( $both_html, 'data-manacore-episodes="16"' ), 'جعبه‌ی سریال به رفتار آکاردئون مرجع وصل می‌شود' );
mc_ok( false !== strpos( $both_html, 'class="episode-toggle"' ) && false !== strpos( $both_html, 'aria-expanded="true"' ), 'دکمه‌ی باز/بسته‌شدن با aria-expanded' );
mc_ok( 2 === substr_count( $both_html, 'data-season="' ), 'برای هر فصل یک تب' );

$post_html = \ManaCore\Core\Templates::links( 16, array( 'source' => 'post' ) );
mc_ok( 2 === substr_count( $post_html, 'class="episode-card' ), 'منبع post: ردیف قسمتی که لینکش روی پست قسمت است، تکرار نمی‌شود', 'کارت‌ها: ' . substr_count( $post_html, 'class="episode-card' ) );
mc_ok( false !== strpos( $post_html, 'دانلود کامل فصل' ), 'بسته‌ی فصل کنار کارت‌های قسمت می‌آید' );

$s2_html = \ManaCore\Core\Templates::links( 16, array( 'source' => 'both', 'season' => 2 ) );
mc_ok( false !== strpos( $s2_html, 'data-season-panel="2"' ) && false === strpos( $s2_html, 'data-season-panel="1"' ), 'فیلتر فصل فقط همان فصل را نشان می‌دهد' );
mc_ok( '' === \ManaCore\Core\Templates::links( 16, array( 'source' => 'both', 'season' => 9 ) ), 'فصلی که داده ندارد، باکس با فصل‌های دیگر پر نمی‌شود' );
mc_ok( false !== strpos( $both_html, 'download-size dl-col-size">2GB<' ) && false !== strpos( $both_html, 'download-size dl-col-size">4GB<' ), 'حجم هر قسمت در جدول کیفیتش می‌آید (ارث‌بری از گروه وقتی آیتم حجم ندارد)' );

$movie_again = \ManaCore\Core\Templates::links( 4 );
mc_ok( false === strpos( $movie_again, 'episode-card' ) && false === strpos( $movie_again, 'is-series' ), 'فیلم هیچ کارت قسمتی نمی‌گیرد' );

/* ۶.۸ راهنمای مدیر در باکس خالی (تنها در بافت مدیریت/پیش‌نمایش) */
$GLOBALS['mc_can_edit'] = true;
$GLOBALS['mc_is_admin'] = true;
$hint = \ManaCore\Core\Templates::links( 12 );
mc_ok( false !== strpos( $hint, 'افزودن لینک‌ها' ), 'مدیر در ویرایشگر، جای خالی باکس را با پیوند افزودن لینک می‌بیند' );
mc_ok( false !== strpos( $hint, '#manacore-links' ), 'پیوند راهنما مستقیم متاباکس لینک‌ها را باز می‌کند' );

$GLOBALS['mc_is_admin'] = false;
mc_ok( '' === \ManaCore\Core\Templates::links( 12 ), 'همین مدیر در نمای همگانی هیچ مارک‌آپی نمی‌بیند (هندسه‌ی صفحه دست‌نخورده)' );

$GLOBALS['mc_is_admin'] = true;
$GLOBALS['mc_can_edit'] = false;
mc_ok( '' === \ManaCore\Core\Templates::links( 12 ), 'کاربر بی‌دسترسی (حتی در پیشخوان) راهنما را نمی‌بیند' );
$GLOBALS['mc_is_admin'] = false;

/* ۶.۹ قالب سریال: باکس دانلود همان‌جا که باید باشد */
$series_tpl = file_exists( dirname( __DIR__ ) . '/themes/koohe-film/templates/single-series.html' )
	? file_get_contents( dirname( __DIR__ ) . '/themes/koohe-film/templates/single-series.html' )
	: '';

mc_ok( false !== strpos( $series_tpl, 'wp:manacore/download-links' ), 'قالب سریال، باکس دانلود را می‌آورد (پیش‌تر هیچ باکسی نداشت)' );
mc_ok( false !== strpos( $series_tpl, '"linkSource":"post"' ), 'باکس سریال فقط بسته‌های خودِ سریال را می‌آورد تا با جدول قسمت‌ها تکرار نشود' );
mc_ok( false !== strpos( $series_tpl, 'data-koohe-target="#episodes"' ), 'تب «فصل‌ها و قسمت‌ها» به فهرست قسمت‌ها می‌رود، نه به باکس دانلود' );

/* ---------------------------------------------------------------
 * ۷) شمارش تماشا: رویداد پخش، نه بازشدن صفحه
 * ------------------------------------------------------------ */

echo "\n=== ۷) شمارش تماشا (رویداد پخش، نه بازشدن صفحه) ===\n";

/*
 * پیش‌تر شمارش روی قلاب `wp` بود: هر بازشدن صفحه‌ی تکی یک «بازدید»
 * ثبت می‌کرد و رفرش/خزنده آن را باد می‌کرد. اکنون سنجه فقط با شروع
 * واقعی پخش از مرورگر می‌آید. این بخش همان تفاوت را قفل می‌کند.
 */
$ratings_src = file_get_contents( MANACORE_PATH . 'includes/class-ratings.php' );
$rest_src    = file_get_contents( MANACORE_PATH . 'includes/class-rest-api.php' );
$front_src   = file_get_contents( MANACORE_PATH . 'assets/js/front.js' );

mc_ok( false === strpos( $ratings_src, 'maybe_count_view' ), 'بازشماری هر بازشدن صفحه حذف شده است' );
mc_ok( false === strpos( $ratings_src, 'manacore_v_' ), 'کوکی/متای شمارشِ رفرشی دیگر ساخته نمی‌شود' );
mc_ok( false !== strpos( $rest_src, "'/track-view'" ), 'مسیر REST شمارش تماشا ثبت می‌شود' );
mc_ok( false !== strpos( $front_src, "'track-view'" ), 'مرورگر رویداد تماشا را به مسیر تازه می‌فرستد' );

/* ۷.۱ پایه: گزینه روشن، تماشای تازه */
$GLOBALS['mc_options']['manacore_settings'] = array( 'enable_views' => 1 );
$GLOBALS['mc_posts'][4]['meta']['manacore_views'] = 0;

mc_ok( true === \ManaCore\Core\Ratings::instance()->count_watch( 4 ), 'نخستین تماشا شمرده می‌شود' );
mc_ok( 1 === (int) $GLOBALS['mc_posts'][4]['meta']['manacore_views'], 'شمارش روی متای اثر ذخیره می‌شود' );
mc_ok( false === \ManaCore\Core\Ratings::instance()->count_watch( 4 ), 'تماشای پشت‌سرهم داخل پنجره‌ی ضدرعدّ‌سازی دوباره شمرده نمی‌شود' );
mc_ok( 1 === (int) $GLOBALS['mc_posts'][4]['meta']['manacore_views'], 'شمارش متا در کلیک تکرارشده بالا نمی‌رود' );

$stats_hits = 0;
foreach ( $GLOBALS['wpdb']->queries as $sql ) {
	if ( false !== strpos( $sql, 'manacore_stats' ) && false !== strpos( $sql, "'view'" ) ) {
		$stats_hits++;
	}
}
mc_ok( 1 === $stats_hits, 'آمار روزانه فقط یک بار برای همان تماشا ثبت می‌شود', 'ردیف‌ها: ' . $stats_hits );

/* ۷.۲ خاموش‌کردن گزینه، شمارش را کامل می‌بندد */
$GLOBALS['mc_options']['manacore_settings'] = array( 'enable_views' => 0 );
$GLOBALS['mc_posts'][5]['meta']['manacore_views'] = 0;

mc_ok( false === \ManaCore\Core\Ratings::instance()->count_watch( 5 ), 'با خاموش‌بودن گزینه‌ی «شمارش تماشا»، رویداد شمرده نمی‌شود' );
mc_ok( 0 === (int) $GLOBALS['mc_posts'][5]['meta']['manacore_views'], 'در حالت خاموش، متای شمارش دست‌نخورده می‌ماند' );

/* ۷.۳ نگهبان ورودی */
$GLOBALS['mc_options']['manacore_settings'] = array( 'enable_views' => 1 );
mc_ok( false === \ManaCore\Core\Ratings::instance()->count_watch( 0 ), 'شناسه‌ی صفر شمرده نمی‌شود' );

/* ---------------------------------------------------------------
 * پایان
 * ------------------------------------------------------------ */

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo 'موفق: ' . $GLOBALS['mc_pass'] . '   ناموفق: ' . $GLOBALS['mc_fail'] . "\n";
echo str_repeat( '=', 58 ) . "\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
