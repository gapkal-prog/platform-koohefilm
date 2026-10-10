<?php
/**
 * آزمون ابزارهای گروهی نگه‌داشت لینک‌ها.
 *
 * چرا لازم است؟ این ابزارها «همه‌ی» لینک‌های سایت را تغییر می‌دهند. یک
 * خطای کوچک در تطبیق پیشوند یا در حالت کپی می‌تواند صدها لینک سالم را
 * خراب کند و چون فوری نیست، ماه‌ها بعد دیده شود. پس آزمون باید دقیقاً
 * بپرسد: چه چیزی تغییر می‌کند، چه چیزی دست‌نخورده می‌ماند و پیش‌نمایش
 * هیچ‌وقت نمی‌نویسد.
 *
 * اجرا: php wp/tests/test-link-tools.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.1.0' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

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
		echo "  ✗ {$label}" . ( '' !== $extra ? "  — {$extra}" : '' ) . "\n";
	}
}

/* ---------------------------------------------------------------
 * پوسته‌ی وردپرس (کمینه)
 * ------------------------------------------------------------ */

$GLOBALS['mc_links']   = array(); // post_id => groups
$GLOBALS['mc_types']   = array(); // post_id => post_type
$GLOBALS['mc_titles']  = array();
$GLOBALS['mc_writes']  = array(); // رکورد نوشتن‌ها
$GLOBALS['mc_hooks']   = array();
$GLOBALS['mc_options'] = array();

function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['mc_hooks'][] = 'action:' . $tag;
}

function apply_filters( $tag, $value ) {
	return $value;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}

function absint( $value ) {
	return abs( (int) $value );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_text_field( $value ) {
	return trim( preg_replace( '/[\r\n\t]+/', ' ', (string) $value ) );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_slash( $value ) {
	return $value;
}

function wp_rand( $min = 0, $max = 0 ) {
	return random_int( 1, 9999 );
}

function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}

function wp_parse_url( $url, $component = -1 ) {
	$parts = parse_url( $url );

	if ( -1 === $component ) {
		return $parts;
	}

	if ( PHP_URL_HOST === $component ) {
		return $parts['host'] ?? null;
	}

	if ( PHP_URL_SCHEME === $component ) {
		return $parts['scheme'] ?? null;
	}

	if ( PHP_URL_PATH === $component ) {
		return $parts['path'] ?? null;
	}

	return null;
}

/**
 * پوسته‌ی ساده‌ی `esc_url_raw`: فقط طرح‌های مجاز را می‌گذراند.
 */
function esc_url_raw( $url, $protocols = null ) {
	$url   = trim( (string) $url );
	$parts = parse_url( $url );

	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) ) {
		return '';
	}

	$allowed = null === $protocols ? array( 'http', 'https', 'ftp', 'ftps', 'magnet' ) : (array) $protocols;

	return in_array( strtolower( $parts['scheme'] ), $allowed, true ) ? $url : '';
}

function get_posts( $args = array() ) {
	$ids = array();

	foreach ( $GLOBALS['mc_links'] as $post_id => $groups ) {
		$type = $GLOBALS['mc_types'][ $post_id ] ?? 'movie';

		if ( isset( $args['post_type'] ) && ! in_array( $type, (array) $args['post_type'], true ) ) {
			continue;
		}

		$ids[] = (int) $post_id;
	}

	sort( $ids );

	if ( ! empty( $args['posts_per_page'] ) && (int) $args['posts_per_page'] > 0 ) {
		$ids = array_slice( $ids, 0, (int) $args['posts_per_page'] );
	}

	return $ids;
}

function get_post_type( $post_id ) {
	return $GLOBALS['mc_types'][ $post_id ] ?? false;
}

function get_the_title( $post_id ) {
	return $GLOBALS['mc_titles'][ $post_id ] ?? ( 'نوشته ' . (int) $post_id );
}

function get_post_meta( $post_id, $key, $single = false ) {
	if ( 'manacore_links' !== $key || ! isset( $GLOBALS['mc_links'][ $post_id ] ) ) {
		return $single ? '' : array();
	}

	return wp_json_encode( $GLOBALS['mc_links'][ $post_id ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function update_post_meta( $post_id, $key, $value ) {
	$decoded = json_decode( (string) $value, true );

	$GLOBALS['mc_links'][ $post_id ] = is_array( $decoded ) ? $decoded : array();
	$GLOBALS['mc_writes'][]          = array( 'post_id' => (int) $post_id, 'groups' => $GLOBALS['mc_links'][ $post_id ] );

	return true;
}

function delete_post_meta( $post_id, $key ) {
	unset( $GLOBALS['mc_links'][ $post_id ] );

	return true;
}

function __( $text, $domain = '' ) {
	return $text;
}

/* ---------------------------------------------------------------
 * پوسته‌ی شبکه و زمان‌بندی (برای بخش سلامت لینک‌ها)
 * ------------------------------------------------------------ */

$GLOBALS['mc_http']        = array(); // url => code | array(head=>,get=>) | 'error'
$GLOBALS['mc_probes']      = array(); // درخواست‌های انجام‌شده
$GLOBALS['mc_cron']        = array(); // hook => recurrence
$GLOBALS['mc_cron_calls']  = array();

/**
 * پاسخ ساختگی شبکه بر پایه‌ی نگاشت `mc_http`.
 */
function mc_http_response( $url, $method ) {
	$spec = $GLOBALS['mc_http'][ $url ] ?? null;

	if ( null === $spec ) {
		return new WP_Error( 'connect_error', 'بدون پاسخ ساختگی' );
	}

	if ( is_array( $spec ) && isset( $spec[ $method ] ) ) {
		$spec = $spec[ $method ];
	}

	if ( 'error' === $spec ) {
		return new WP_Error( 'http_request_failed', 'خطای ساختگی' );
	}

	return array( 'response' => array( 'code' => (int) $spec ) );
}

class WP_Error {

	protected $code;
	protected $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function wp_remote_head( $url, $args = array() ) {
	$GLOBALS['mc_probes'][] = array( 'method' => 'HEAD', 'url' => $url, 'args' => $args );

	return mc_http_response( $url, 'head' );
}

function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['mc_probes'][] = array( 'method' => 'GET', 'url' => $url, 'args' => $args );

	return mc_http_response( $url, 'get' );
}

function wp_remote_retrieve_response_code( $response ) {
	return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
}

function home_url( $path = '/' ) {
	return 'https://example.com' . ( '/' === $path ? '/' : '/' . ltrim( (string) $path, '/' ) );
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['mc_options'] ) ? $GLOBALS['mc_options'][ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['mc_options'][ $name ] = $value;

	return true;
}

function wp_next_scheduled( $hook, $args = array() ) {
	return isset( $GLOBALS['mc_cron'][ $hook ] ) ? $GLOBALS['mc_cron'][ $hook ]['time'] : false;
}

function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
	$GLOBALS['mc_cron'][ $hook ] = array( 'time' => $timestamp, 'recurrence' => $recurrence );
	$GLOBALS['mc_cron_calls'][]  = 'schedule:' . $recurrence;

	return true;
}

function wp_clear_scheduled_hook( $hook, $args = array() ) {
	unset( $GLOBALS['mc_cron'][ $hook ] );
	$GLOBALS['mc_cron_calls'][] = 'clear:' . $hook;

	return 0;
}

function wp_get_schedule( $hook, $args = array() ) {
	return isset( $GLOBALS['mc_cron'][ $hook ] ) ? $GLOBALS['mc_cron'][ $hook ]['recurrence'] : false;
}

/**
 * جانشین کلاس گزارش‌ها: آزمون باید ببیند کجا و با چه چیزی صدا زده می‌شود.
 */
class mc_reports_stub {

	public static $open     = array();
	public static $inserts  = array();
	public static $resolved = array();

	public static function has_open( $post_id, $quality = '' ) {
		return ! empty( self::$open[ (int) $post_id . '|' . (string) $quality ] );
	}

	public static function insert( $args ) {
		self::$inserts[] = $args;

		return 1;
	}

	public static function resolve_url( $post_id, $url ) {
		self::$resolved[] = array(
			'post_id' => (int) $post_id,
			'url'     => (string) $url,
		);

		return 1;
	}
}

class_alias( 'mc_reports_stub', 'ManaCore\\Core\\Reports' );

/* ---------------------------------------------------------------
 * بارگذاری کلاس‌های واقعی
 * ------------------------------------------------------------ */

require_once MANACORE_PATH . 'includes/functions.php';
require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-links.php';
require_once MANACORE_PATH . 'includes/class-link-tools.php';

use ManaCore\Core\Link_Tools;
use ManaCore\Core\Links;

/* ---------------------------------------------------------------
 * ۱) جایگزینی پیشوند نشانی
 * ------------------------------------------------------------ */

echo "\n=== ۱) جایگزینی پیشوند نشانی ===\n";

$GLOBALS['mc_links'] = array(
	10 => array(
		array(
			'id'    => 'grp_a',
			'title' => 'گروه اصلی',
			'items' => array(
				array( 'id' => 'lnk_a', 'url' => 'https://old.example/files/movie-a-1080.mkv', 'quality' => '1080p', 'size' => '2GB' ),
				array( 'id' => 'lnk_b', 'url' => 'https://cdn.other.com/movie-a-720.mkv', 'quality' => '720p', 'size' => '1GB' ),
				array( 'id' => 'lnk_c', 'url' => 'magnet:?xt=urn:btih:abc123', 'quality' => '1080p', 'size' => '2GB' ),
			),
		),
	),
	11 => array(
		array(
			'id'    => 'grp_b',
			'title' => 'گروه دوم',
			'items' => array(
				array( 'id' => 'lnk_d', 'url' => 'https://old.example/files/movie-b-1080.mkv', 'quality' => '1080p', 'size' => '3GB' ),
			),
		),
	),
);
$GLOBALS['mc_types']  = array( 10 => 'movie', 11 => 'series' );
$GLOBALS['mc_titles'] = array( 10 => 'فیلم الف', 11 => 'سریال ب' );

$bad = Link_Tools::replace_domain( 'old.example', 'new.example' );
mc_ok( ! $bad['ok'] && 'bad-from' === $bad['reason'], 'پیشوند بی‌طرح رد می‌شود', $bad['reason'] );

$bad = Link_Tools::replace_domain( 'https://old.example/files', 'new.example' );
mc_ok( ! $bad['ok'] && 'bad-to' === $bad['reason'], 'مقصد بی‌طرح رد می‌شود', $bad['reason'] );

$bad = Link_Tools::replace_domain( 'https://old.example/files/', 'https://old.example/files' );
mc_ok( ! $bad['ok'] && 'same-prefix' === $bad['reason'], 'پیشوند یکسان (با اسلش انتهایی) رد می‌شود', $bad['reason'] );

$GLOBALS['mc_writes'] = array();
$dry = Link_Tools::replace_domain( 'https://old.example/files', 'https://new.example/files', true );

mc_ok( $dry['ok'] && true === $dry['dry_run'], 'پیش‌نمایش موفق است' );
mc_ok( 2 === $dry['scanned'], 'همه‌ی نوشته‌های دارای لینک بازرسی شدند', (string) $dry['scanned'] );
mc_ok( 2 === $dry['posts'], 'فقط نوشته‌های دارای لینک منطبق شمرده شدند', (string) $dry['posts'] );
mc_ok( 2 === $dry['links'], 'تعداد لینک‌های منطبق درست است', (string) $dry['links'] );
mc_ok( array() === $GLOBALS['mc_writes'], 'پیش‌نمایش هیچ چیزی نمی‌نویسد' );
mc_ok(
	'https://cdn.other.com/movie-a-720.mkv' === $GLOBALS['mc_links'][10][0]['items'][1]['url'],
	'لینک دامنه‌ی دیگر دست‌نخورده می‌ماند'
);
mc_ok(
	'magnet:?xt=urn:btih:abc123' === $GLOBALS['mc_links'][10][0]['items'][2]['url'],
	'لینک مگنت دست‌نخورده می‌ماند'
);

$apply = Link_Tools::replace_domain( 'https://old.example/files', 'https://new.example/files', false );

mc_ok( $apply['ok'] && false === $apply['dry_run'], 'اجرای واقعی انجام شد' );
mc_ok( 2 === count( $GLOBALS['mc_writes'] ), 'دو نوشته ذخیره شد', (string) count( $GLOBALS['mc_writes'] ) );
mc_ok(
	'https://new.example/files/movie-a-1080.mkv' === $GLOBALS['mc_links'][10][0]['items'][0]['url'],
	'لینک منطبق در نوشته‌ی نخست جایگزین شد',
	$GLOBALS['mc_links'][10][0]['items'][0]['url']
);
mc_ok(
	'https://new.example/files/movie-b-1080.mkv' === $GLOBALS['mc_links'][11][0]['items'][0]['url'],
	'لینک منطبق در نوشته‌ی دوم جایگزین شد'
);
mc_ok(
	'lnk_a' === $GLOBALS['mc_links'][10][0]['items'][0]['id'] && 'grp_a' === $GLOBALS['mc_links'][10][0]['id'],
	'شناسه‌ی گروه و ردیف‌ها در جایگزینی حفظ می‌شود'
);

/* اجرای دوباره چیزی برای تغییر ندارد. */
$again = Link_Tools::replace_domain( 'https://old.example/files', 'https://new.example/files', false );
mc_ok( 0 === $again['links'], 'پس از جایگزینی، دیگر لینکی برای تغییر نمی‌ماند', (string) $again['links'] );

/* سقف نوشته‌ها پرچم «بریده‌شده» را روشن می‌کند. */
$limited = Link_Tools::replace_domain( 'https://new.example/files', 'https://new.example/files2', true, 1 );
mc_ok( ! empty( $limited['truncated'] ) && 1 === $limited['scanned'], 'با پر شدن سقف، گزارش بریده‌شده علامت می‌خورد' );

/* ---------------------------------------------------------------
 * ۲) کپی گروه لینک
 * ------------------------------------------------------------ */

echo "\n=== ۲) کپی گروه لینک ===\n";

$GLOBALS['mc_links'] = array(
	20 => array(
		array(
			'id'    => 'grp_src',
			'title' => 'بسته‌ی کامل فصل',
			'items' => array(
				array( 'id' => 'lnk_src', 'url' => 'https://new.example/files/s01-pack.zip', 'quality' => '1080p', 'size' => '12GB' ),
			),
		),
	),
	21 => array(),
	22 => array(
		array(
			'id'    => 'grp_own',
			'title' => 'گروه خودش',
			'items' => array(
				array( 'id' => 'lnk_own', 'url' => 'https://new.example/files/own.mkv', 'quality' => '720p', 'size' => '1GB' ),
			),
		),
	),
);
$GLOBALS['mc_types']  = array( 20 => 'series', 21 => 'episode', 22 => 'episode' );
$GLOBALS['mc_titles'] = array( 20 => 'سریال مبدأ', 21 => 'قسمت ۱', 22 => 'قسمت ۲' );
$GLOBALS['mc_writes'] = array();

$bad = Link_Tools::copy_links( 999, '21' );
mc_ok( ! $bad['ok'] && 'bad-source' === $bad['reason'], 'مبدأی نامعتبر رد می‌شود', $bad['reason'] );

$bad = Link_Tools::copy_links( 21, '22' );
mc_ok( ! $bad['ok'] && 'empty-source' === $bad['reason'], 'مبدأی بی‌لینک رد می‌شود', $bad['reason'] );

$bad = Link_Tools::copy_links( 20, '' );
mc_ok( ! $bad['ok'] && 'no-targets' === $bad['reason'], 'مقصد خالی رد می‌شود', $bad['reason'] );

$GLOBALS['mc_writes'] = array();
$missing = Link_Tools::copy_links( 20, '21,22', 'missing' );

mc_ok( 1 === $missing['copied'], 'حالت «فقط خالی‌ها» یک مقصد را پر می‌کند', (string) $missing['copied'] );
mc_ok( 1 === $missing['skipped'], 'مقصد پُرردشده شمرده می‌شود', (string) $missing['skipped'] );
mc_ok(
	'https://new.example/files/s01-pack.zip' === $GLOBALS['mc_links'][21][0]['items'][0]['url'],
	'لینک مبدأ روی مقصد خالی نشست'
);
mc_ok( 1 === count( $GLOBALS['mc_links'][22] ), 'مقصد پُر در حالت «فقط خالی‌ها» دست‌نخورده می‌ماند', (string) count( $GLOBALS['mc_links'][22] ) );

$append = Link_Tools::copy_links( 20, '22', 'append' );

mc_ok( 1 === $append['copied'] && 2 === count( $GLOBALS['mc_links'][22] ), 'حالت «افزودن» گروه‌ها را کنار هم می‌گذارد' );
mc_ok(
	'grp_own' === $GLOBALS['mc_links'][22][0]['id'] && 'grp_src' !== $GLOBALS['mc_links'][22][1]['id'],
	'شناسه‌ی گروه کپی‌شده تازه ساخته می‌شود (بدون تکرار شناسه)'
);
mc_ok(
	'lnk_src' !== $GLOBALS['mc_links'][22][1]['items'][0]['id'],
	'شناسه‌ی ردیف کپی‌شده هم تازه است'
);

$replace = Link_Tools::copy_links( 20, '22', 'replace' );

mc_ok( 1 === $replace['copied'] && 1 === count( $GLOBALS['mc_links'][22] ), 'حالت «جایگزینی» گروه‌های مقصد را پاک می‌کند' );

$mixed = Link_Tools::copy_links( 20, '21,120,21', 'missing' );
mc_ok( 1 === $mixed['missing'], 'شناسه‌ی ناموجود در گزارش می‌آید', (string) $mixed['missing'] );
mc_ok( 2 === count( $mixed['details'] ), 'شناسه‌ی تکراری دوباره بررسی نمی‌شود', (string) count( $mixed['details'] ) );

/* ---------------------------------------------------------------
 * ۳) بازرسی ساختاری
 * ------------------------------------------------------------ */

echo "\n=== ۳) بازرسی ساختاری ===\n";

$GLOBALS['mc_links'] = array(
	30 => array(
		array(
			'id'    => 'grp_c',
			'title' => '',
			'items' => array(
				array( 'id' => 'lnk_1', 'label' => 'کیفیت دارد', 'url' => 'https://new.example/files/ok-1080.mkv', 'quality' => '1080p', 'size' => '2GB' ),
				array( 'id' => 'lnk_2', 'label' => 'بی‌کیفیت و بی‌حجم', 'url' => 'https://new.example/files/x-720.mkv', 'quality' => '', 'size' => '' ),
				array( 'id' => 'lnk_3', 'label' => 'بی‌کیفیت', 'url' => 'https://new.example/files/y-720.mkv', 'quality' => '1080p', 'size' => '1GB' ),
				array( 'id' => 'lnk_4', 'label' => 'بی‌پسوند', 'url' => 'https://new.example/files/y-720', 'quality' => '480p', 'size' => '500MB' ),
				array( 'id' => 'lnk_5', 'label' => 'طرح نامعتبر', 'url' => 'ftp://new.example/files/z.mkv', 'quality' => '720p', 'size' => '500MB' ),
			),
		),
	),
);
$GLOBALS['mc_types']  = array( 30 => 'movie' );
$GLOBALS['mc_titles'] = array( 30 => 'فیلم بازرسی' );

$report = Link_Tools::audit();

mc_ok( 1 === $report['scanned'], 'نوشته‌ی بازرسی‌شده شمرده می‌شود', (string) $report['scanned'] );
mc_ok( 1 === $report['totals']['no-quality'], 'ردیف بی‌کیفیت پیدا می‌شود', (string) $report['totals']['no-quality'] );
mc_ok( 1 === $report['totals']['no-size'], 'ردیف بی‌حجم پیدا می‌شود', (string) $report['totals']['no-size'] );
mc_ok( 1 === $report['totals']['dup-quality'], 'کیفیت تکراری در یک گروه پیدا می‌شود', (string) $report['totals']['dup-quality'] );
mc_ok( 1 === $report['totals']['unknown-file'], 'نشانی بدون پسوند شناخته‌شده پیدا می‌شود', (string) $report['totals']['unknown-file'] );
mc_ok( 1 === $report['totals']['bad-url'], 'طرح نامعتبر به‌عنوان نشانی نامعتبر شمرده می‌شود', (string) $report['totals']['bad-url'] );
mc_ok( 5 === count( $report['rows'] ), 'هر ردیف مشکل‌دار در گزارش می‌آید', (string) count( $report['rows'] ) );
mc_ok( false === $report['rows'][0]['issue'] || is_string( $report['rows'][0]['issue'] ), 'هر ردیف کد مشکل دارد' );
mc_ok( ! empty( $report['rows'][0]['title'] ), 'عنوان نوشته در گزارش می‌آید', (string) ( $report['rows'][0]['title'] ?? '' ) );

$clean = array(
	40 => array(
		array(
			'id'    => 'grp_ok',
			'items' => array(
				array( 'id' => 'lnk_ok', 'url' => 'https://new.example/files/ok-1080.mkv', 'quality' => '1080p', 'size' => '2GB' ),
				array( 'id' => 'lnk_ok2', 'url' => 'magnet:?xt=urn:btih:deadbeef', 'quality' => '720p', 'size' => '1GB' ),
			),
		),
	),
);

$GLOBALS['mc_links'] = $clean;
$GLOBALS['mc_types'] = array( 40 => 'movie' );

$clean_report = Link_Tools::audit();

mc_ok( 0 === array_sum( $clean_report['totals'] ), 'مجموعه‌ی سالم هیچ ایرادی نمی‌گیرد', (string) array_sum( $clean_report['totals'] ) );

/* سقف ردیف‌های گزارش (۵۰) با ۶۰ ردیف مشکل‌دار. */
$many_items = array();

for ( $i = 1; $i <= 60; $i++ ) {
	$many_items[] = array(
		'id'      => 'lnk_m' . $i,
		'url'     => 'https://new.example/files/m' . $i . '.mkv',
		'quality' => '',
		'size'    => '1GB',
	);
}

$GLOBALS['mc_links'] = array( 50 => array( array( 'id' => 'grp_m', 'items' => $many_items ) ) );
$GLOBALS['mc_types'] = array( 50 => 'movie' );

$capped = Link_Tools::audit();

mc_ok( 60 === $capped['totals']['no-quality'], 'شمارش کل فراتر از سقف نمایش ادامه می‌یابد', (string) $capped['totals']['no-quality'] );
mc_ok( Link_Tools::MAX_ROWS === count( $capped['rows'] ), 'ردیف‌های نمایشی به سقف می‌رسند', (string) count( $capped['rows'] ) );

/* ---------------------------------------------------------------
 * ۴) اتصال قلاب و برچسب دلایل
 * ------------------------------------------------------------ */

echo "\n=== ۴) اتصال قلاب و پیام‌ها ===\n";

Link_Tools::instance()->hooks();
mc_ok( in_array( 'action:admin_post_manacore_link_tool', $GLOBALS['mc_hooks'], true ), 'کنش پنل روی admin_post بسته می‌شود' );

mc_ok( '' !== Link_Tools::reason_label( 'bad-from' ), 'دلیل bad-from برچسب فارسی دارد' );
mc_ok( '' === Link_Tools::reason_label( 'nope' ), 'دلیل ناشناخته برچسب خالی می‌دهد' );
mc_ok( 'link-tool-ok' === Link_Tools::NOTICE, 'کد پیام نتیجه ثابت است' );

/* ---------------------------------------------------------------
 * ۵) بررسی سلامت لینک‌ها (سنجش، ثبت خودکار و زمان‌بندی)
 * ------------------------------------------------------------ */

echo "\n=== ۵) سلامت لینک‌ها ===\n";

$GLOBALS['mc_links'] = array(
	10 => array(
		array(
			'id'      => 'g1',
			'title'   => 'فیلم تستی',
			'quality' => '1080p',
			'items'   => array(
				array( 'id' => 'l1', 'label' => 'کیفیت ۱۰۸۰', 'quality' => '1080p', 'url' => 'https://93.184.216.34/a.mkv' ),
				array( 'id' => 'l2', 'label' => 'کیفیت ۷۲۰', 'quality' => '720p', 'url' => 'https://93.184.216.34/b.mkv' ),
				array( 'id' => 'l3', 'label' => 'مگنت', 'url' => 'magnet:?xt=urn:btih:abc' ),
			),
		),
	),
	11 => array(
		array(
			'id'      => 'g2',
			'title'   => 'سریال تستی',
			'quality' => '1080p',
			'items'   => array(
				array( 'id' => 'l4', 'label' => 'کیفیت ۱۰۸۰', 'quality' => '1080p', 'url' => 'https://93.184.216.34/a.mkv' ),
				array( 'id' => 'l5', 'label' => 'شبکه‌ی داخلی', 'quality' => '1080p', 'url' => 'https://10.0.0.7/c.mkv' ),
				array( 'id' => 'l6', 'label' => 'روی همین سایت', 'quality' => '480p', 'url' => 'https://example.com/local.mkv' ),
			),
		),
	),
);

$GLOBALS['mc_types']  = array( 10 => 'movie', 11 => 'series' );
$GLOBALS['mc_titles'] = array( 10 => 'فیلم تستی', 11 => 'سریال تستی' );

$GLOBALS['mc_http'] = array(
	'https://93.184.216.34/a.mkv' => 404,
	'https://93.184.216.34/b.mkv' => 200,
);

$GLOBALS['mc_options']                 = array();
$GLOBALS['mc_options']['manacore_settings'] = array(
	'links_check_interval' => 'daily',
	'links_check_batch'    => 10,
	'links_check_timeout'  => 3,
);

mc_reports_stub::$open     = array();
mc_reports_stub::$inserts  = array();
mc_reports_stub::$resolved = array();
$GLOBALS['mc_probes']      = array();

$run = Link_Tools::check( array( 'limit' => 10 ) );

mc_ok( 2 === $run['total'], 'فقط نشانی‌های سنجیدنی شمرده می‌شوند', 'total=' . $run['total'] );
mc_ok( 3 === $run['skipped'], 'مگنت، دامنه‌ی خصوصی و نشانی خود سایت کنار گذاشته می‌شوند', 'skipped=' . $run['skipped'] );
mc_ok( 2 === $run['checked'] && 1 === $run['alive'] && 1 === $run['dead'] && 0 === $run['unknown'], 'شمار سالم/مرده درست است' );
mc_ok( 2 === $run['filed'], 'برای هر نوشته‌ای که نشانی مرده دارد گزارش ثبت می‌شود', 'filed=' . $run['filed'] );
mc_ok( 1 === $run['resolved'], 'گزارش بازِ لینکی که سالم شد بسته می‌شود' );
mc_ok( empty( $run['truncated'] ) && 0 === $run['next'], 'با پوشش کامل، نقطه‌ی ادامه به ابتدا برمی‌گردد' );

$probed = array_column( $GLOBALS['mc_probes'], 'url' );
mc_ok( ! in_array( 'magnet:?xt=urn:btih:abc', $probed, true ), 'لینک مگنت سنجیده نمی‌شود' );
mc_ok( ! in_array( 'https://10.0.0.7/c.mkv', $probed, true ), 'نشانی شبکه‌ی خصوصی سنجیده نمی‌شود' );
mc_ok( ! in_array( 'https://example.com/local.mkv', $probed, true ), 'نشانی همین سایت سنجیده نمی‌شود' );
mc_ok( 2 === count( $probed ), 'نشانی یکسان دو بار سنجیده نمی‌شود', 'probes=' . count( $probed ) );

mc_ok( 2 === count( mc_reports_stub::$inserts ) && 10 === mc_reports_stub::$inserts[0]['post_id'] && 11 === mc_reports_stub::$inserts[1]['post_id'], 'گزارش برای نوشته‌های درست ثبت شد' );
mc_ok( 0 === strpos( (string) mc_reports_stub::$inserts[0]['reason'], 'بررسی خودکار: ' ), 'گزارش خودکار نشانه‌ی «بررسی خودکار» دارد', mc_reports_stub::$inserts[0]['reason'] );
mc_ok( false !== strpos( (string) mc_reports_stub::$inserts[0]['reason'], '۴۰۴' ), 'کد ۴۰۴ در توضیح گزارش آمده است' );
mc_ok( '1080p' === mc_reports_stub::$inserts[0]['quality'] && 'https://93.184.216.34/a.mkv' === mc_reports_stub::$inserts[0]['link_url'], 'کیفیت و نشانی در گزارش یکی است' );
mc_ok( 1 === count( mc_reports_stub::$resolved ) && 'https://93.184.216.34/b.mkv' === mc_reports_stub::$resolved[0]['url'], 'گزارش نشانی سالم بسته می‌شود' );

$dead_rows = array_values(
	array_filter(
		$run['rows'],
		static function ( $row ) {
			return 'dead' === $row['status'];
		}
	)
);

mc_ok( $dead_rows && 'https://93.184.216.34/a.mkv' === $dead_rows[0]['url'], 'ردیف مرده در گزارش اجرا می‌آید' );

$log = $GLOBALS['mc_options'][ Link_Tools::CHECK_LOG ] ?? array();
mc_ok( isset( $log['checked'] ) && 2 === (int) $log['checked'] && 2 === (int) $log['filed'], 'خلاصه‌ی اجرا در گزینه‌ی لاگ ذخیره می‌شود' );

/* گزارش تکراری برای نوشته‌ای که گزارش باز دارد. */
mc_reports_stub::$open    = array( '10|1080p' => true );
mc_reports_stub::$inserts = array();

Link_Tools::check( array( 'limit' => 10 ) );

mc_ok( 1 === count( mc_reports_stub::$inserts ) && 11 === mc_reports_stub::$inserts[0]['post_id'], 'برای نوشته‌ی دارای گزارش باز، گزارش تکراری ثبت نمی‌شود' );

mc_reports_stub::$open = array();

/* اجرای آزمایشی. */
$options_before           = $GLOBALS['mc_options'];
mc_reports_stub::$inserts = array();
mc_reports_stub::$resolved = array();

$dry = Link_Tools::check( array( 'limit' => 10, 'dry_run' => true ) );

mc_ok( 1 === $dry['dead'] && array() === mc_reports_stub::$inserts, 'اجرای آزمایشی گزارشی ثبت نمی‌کند' );
mc_ok( array() === mc_reports_stub::$resolved, 'اجرای آزمایشی گزارش باز را هم نمی‌بندد' );
mc_ok( $options_before === $GLOBALS['mc_options'], 'اجرای آزمایشی لاگ را هم نمی‌نویسد' );

/* سنجش یک نشانی: پاسخ کدها و بازگشت به GET. */
$GLOBALS['mc_http']   = array( 'https://93.184.216.34/b.mkv' => array( 'head' => 405, 'get' => 200 ) );
$GLOBALS['mc_probes'] = array();

$probe = Link_Tools::probe( 'https://93.184.216.34/b.mkv', 3 );

mc_ok( 'alive' === $probe['status'], 'اگر HEAD پاسخ ندهد با GET دوباره سنجیده می‌شود', $probe['detail'] );
mc_ok( 2 === count( $GLOBALS['mc_probes'] ) && 'GET' === $GLOBALS['mc_probes'][1]['method'], 'درخواست دوم از نوع GET است' );
mc_ok( isset( $GLOBALS['mc_probes'][1]['args']['headers']['Range'] ), 'درخواست GET فقط یک بایت می‌خواهد (Range)' );
mc_ok( (int) $GLOBALS['mc_probes'][0]['args']['timeout'] === 3, 'مهلت درخواست از تنظیمات می‌آید' );

$GLOBALS['mc_http'] = array( 'https://93.184.216.34/slow.mkv' => 'error' );
$probe              = Link_Tools::probe( 'https://93.184.216.34/slow.mkv', 3 );

mc_ok( 'dead' === $probe['status'] && false !== strpos( $probe['detail'], 'ارتباط' ), 'خطای شبکه مرده حساب می‌شود', $probe['detail'] );

$GLOBALS['mc_http'] = array( 'https://93.184.216.34/x.mkv' => 503 );
$probe              = Link_Tools::probe( 'https://93.184.216.34/x.mkv', 3 );

mc_ok( 'unknown' === $probe['status'], 'پاسخ ۵۰۳ مرده حساب نمی‌شود (گزارش کاذب نمی‌سازیم)' );
mc_ok( false !== strpos( $probe['detail'], '503' ), 'کد نامشخص در توضیح می‌آید', $probe['detail'] );

$GLOBALS['mc_http'] = array( 'https://93.184.216.34/gone.mkv' => 410 );
$probe              = Link_Tools::probe( 'https://93.184.216.34/gone.mkv', 3 );

mc_ok( 'dead' === $probe['status'] && false !== strpos( $probe['detail'], '۴۱۰' ), 'پاسخ ۴۱۰ مرده حساب می‌شود' );

/* سنجیدنی بودن نشانی. */
mc_ok( false === Link_Tools::is_checkable( 'ftp://93.184.216.34/x.mkv' ), 'طرح غیر http سنجیدنی نیست' );
mc_ok( false === Link_Tools::is_checkable( 'magnet:?xt=urn:btih:abc' ), 'مگنت سنجیدنی نیست' );
mc_ok( false === Link_Tools::is_checkable( '' ), 'نشانی خالی سنجیدنی نیست' );
mc_ok( false === Link_Tools::is_checkable( 'https://127.0.0.1:8080/x.mkv' ), 'نشانی حلقه‌ی محلی سنجیدنی نیست' );
mc_ok( false === Link_Tools::is_checkable( 'https://definitely-not-real.invalid/x.mkv' ), 'دامنه‌ی بی‌IP سنجیدنی نیست' );
mc_ok( true === Link_Tools::is_checkable( 'https://93.184.216.34/x.mkv' ), 'نشانی عمومی سنجیدنی است' );

/* پویش دسته‌ای: هر اجرا از جایی که ماند ادامه می‌دهد. */
$GLOBALS['mc_links'] = array(
	20 => array(
		array(
			'id'      => 'g3',
			'quality' => '1080p',
			'items'   => array(
				array( 'id' => 'm1', 'quality' => '1080p', 'url' => 'https://93.184.216.34/1.mkv' ),
				array( 'id' => 'm2', 'quality' => '720p', 'url' => 'https://93.184.216.34/2.mkv' ),
				array( 'id' => 'm3', 'quality' => '480p', 'url' => 'https://93.184.216.34/3.mkv' ),
			),
		),
	),
);

$GLOBALS['mc_types']  = array( 20 => 'movie' );
$GLOBALS['mc_titles'] = array( 20 => 'نمونه' );
$GLOBALS['mc_http']   = array(
	'https://93.184.216.34/1.mkv' => 200,
	'https://93.184.216.34/2.mkv' => 200,
	'https://93.184.216.34/3.mkv' => 200,
);

$GLOBALS['mc_options'][ Link_Tools::CHECK_LOG ] = array( 'next' => 0 );

$first = Link_Tools::check( array( 'limit' => 2 ) );
mc_ok( 2 === $first['checked'] && ! empty( $first['truncated'] ) && 2 === $first['next'], 'اجرای نخست دو نشانی می‌سنجد و نقطه‌ی ادامه را نگه می‌دارد' );

$second = Link_Tools::check( array( 'limit' => 2 ) );
mc_ok( 1 === $second['checked'] && 0 === $second['next'], 'اجرای دوم از همان‌جا ادامه می‌دهد و در پایان به ابتدا برمی‌گردد' );
mc_ok( false !== strpos( $second['rows'][0]['url'], '/3.mkv' ), 'اجرای دوم از نشانی سوم شروع می‌کند', $second['rows'][0]['url'] );

/* تنظیمات بررسی. */
$GLOBALS['mc_options']['manacore_settings'] = array(
	'links_check_interval' => 'off',
	'links_check_batch'    => 999,
	'links_check_timeout'  => 1,
);

$settings = Link_Tools::check_settings();

mc_ok( 'off' === $settings['interval'] && '' === $settings['recurrence'], 'بازه‌ی خاموش زمان‌بندی ندارد' );
mc_ok( Link_Tools::MAX_CHECKS === $settings['batch'], 'تعداد بیش از سقف به سقف بسته می‌شود' );
mc_ok( Link_Tools::MIN_TIMEOUT === $settings['timeout'], 'مهلت کمتر از کف به کف بسته می‌شود' );

$GLOBALS['mc_options']['manacore_settings'] = array( 'links_check_interval' => 'nonsense' );
mc_ok( 'daily' === Link_Tools::check_settings()['interval'], 'بازه‌ی ناشناخته به پیش‌فرض روزانه برمی‌گردد' );

$GLOBALS['mc_options']['manacore_settings'] = array();
$defaults                                    = Link_Tools::check_settings();
mc_ok( 'daily' === $defaults['interval'] && Link_Tools::DEFAULT_BATCH === $defaults['batch'], 'پیش از نخستین ذخیره، پیش‌فرض‌های امن اعمال می‌شوند' );

/* زمان‌بندی کرون. */
$GLOBALS['mc_cron']       = array();
$GLOBALS['mc_cron_calls'] = array();

Link_Tools::instance()->sync_schedule();
mc_ok( isset( $GLOBALS['mc_cron'][ Link_Tools::CHECK_EVENT ] ), 'رویداد کرون با پیش‌فرض روزانه ساخته می‌شود' );

$GLOBALS['mc_cron_calls'] = array();
Link_Tools::instance()->sync_schedule();
mc_ok( array() === $GLOBALS['mc_cron_calls'], 'هم‌گام‌سازی دوباره زمان‌بندی را دست نمی‌زند' );

$GLOBALS['mc_options']['manacore_settings'] = array( 'links_check_interval' => 'weekly' );
$GLOBALS['mc_cron_calls']                   = array();

Link_Tools::instance()->sync_schedule();
mc_ok( 'weekly' === wp_get_schedule( Link_Tools::CHECK_EVENT ), 'تغییر بازه، زمان‌بندی را جایگزین می‌کند' );
mc_ok( in_array( 'clear:' . Link_Tools::CHECK_EVENT, $GLOBALS['mc_cron_calls'], true ), 'زمان‌بندی پیشین پاک می‌شود' );

$GLOBALS['mc_options']['manacore_settings'] = array( 'links_check_interval' => 'off' );
Link_Tools::instance()->sync_schedule();

mc_ok( ! isset( $GLOBALS['mc_cron'][ Link_Tools::CHECK_EVENT ] ), 'خاموش‌کردن بررسی، رویداد کرون را پاک می‌کند' );

/* قلاب‌ها و بودجه. */
Link_Tools::instance()->hooks();
mc_ok( in_array( 'action:' . Link_Tools::CHECK_EVENT, $GLOBALS['mc_hooks'], true ), 'رویداد کرون به متد cron وصل است' );
mc_ok( in_array( 'action:admin_init', $GLOBALS['mc_hooks'], true ), 'هم‌گام‌سازی زمان‌بندی روی admin_init است' );
mc_ok( Link_Tools::MANUAL_CHECKS <= Link_Tools::MAX_CHECKS && Link_Tools::MANUAL_TIMEOUT <= Link_Tools::MAX_TIMEOUT, 'بودجه‌ی اجرای دستی از سقف‌ها بیرون نمی‌زند' );


/* ---------------------------------------------------------------
 * پایان
 * ------------------------------------------------------------ */

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo 'موفق: ' . $GLOBALS['mc_pass'] . '   ناموفق: ' . $GLOBALS['mc_fail'] . "\n";
echo str_repeat( '=', 58 ) . "\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
