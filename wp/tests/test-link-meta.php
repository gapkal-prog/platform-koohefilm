<?php
/**
 * آزمون سمت سرور برای «خواندن مشخصات از خود لینک» (`Link_Meta`).
 *
 * چرا لازم است؟ کیفیت، زبان، انکودر و نام فایل از روی نشانی/برچسب خوانده
 * می‌شوند و در ویرایشگر خالی پر می‌شوند. اگر این تجزیه‌گر اشتباه کند (مثلاً
 * «1440p» را کیفیت نامعتبر بپذیرد یا «WEB-DL» را انکودر بداند)، ردیف‌های
 * جدول دانلود همان اطلاعات غلط را به کاربر نشان می‌دهند. سنجش شبکه‌ای
 * (probe) این آزمون را انجام نمی‌دهد؛ فقط منطق خالص را می‌سنجد.
 *
 * اجرا: php wp/tests/test-link-meta.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );
define( 'HOUR_IN_SECONDS', 3600 );

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
 * پوسته‌ی وردپرس (کمینه): فقط آنچه تجزیه‌گرهای خالص لازم دارند.
 * ------------------------------------------------------------ */

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

/* فهرست‌های واقعی `functions.php` (کلیدهای هم‌نام). */
function manacore_qualities() {
	return array(
		'360p' => '360p', '480p' => '480p', '720p' => '720p', '1080p' => '1080p',
		'1080pX' => '1080pX', '2160p' => '2160p', 'HDR' => 'HDR', 'DV' => 'DV',
		'BluRay' => 'BluRay', 'WEBDL' => 'WEB-DL', 'WEBRip' => 'WEBRip', 'HDTV' => 'HDTV', 'CAM' => 'CAM',
	);
}

function manacore_languages() {
	return array(
		'sub_fa' => 'زیرنویس فارسی', 'soft_fa' => 'زیرنویس جدا', 'dub_fa' => 'دوبله فارسی',
		'dual' => 'دو زبانه', 'original' => 'اصلی', 'censored' => 'سانسور شده', 'uncut' => 'بدون سانسور',
	);
}

require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-link-meta.php';

use ManaCore\Core\Link_Meta;

echo "\n[تشخیص کیفیت]\n";
mc_ok( '1080p' === Link_Meta::detect_quality( 'Movie.2019.1080p.WEB-DL' ), 'وضوح 1080p از نام فایل خوانده می‌شود' );
mc_ok( '2160p' === Link_Meta::detect_quality( 'Movie.2019.4K.HDR' ), '4K به 2160p نگاشت می‌شود' );
mc_ok( '' === Link_Meta::detect_quality( 'Movie.1440p.WEB' ), '1440p که در فهرست نیست، کیفیت خالی می‌دهد' );
mc_ok( 'BluRay' === Link_Meta::detect_quality( 'Movie.BluRay.x264' ), 'بدون وضوح، برچسب منبع BluRay خوانده می‌شود' );
mc_ok( 'WEBDL' === Link_Meta::detect_quality( 'Movie.WEB-DL' ), 'WEB-DL به کلید WEBDL نگاشت می‌شود' );
mc_ok( '' === Link_Meta::detect_quality( 'Movie.mkv' ), 'نام بدون نشانه، کیفیت ندارد (حدس زده نمی‌شود)' );

echo "\n[تشخیص زبان و دوبله]\n";
mc_ok( 'dub_fa' === Link_Meta::detect_language( 'Film.1080p.Dubbed' ), 'واژه‌ی Dubbed دوبله‌ی فارسی است' );
mc_ok( 'dub_fa' === Link_Meta::detect_language( 'فیلم دوبله فارسی' ), 'واژه‌ی فارسی «دوبله» شناخته می‌شود' );
mc_ok( 'sub_fa' === Link_Meta::detect_language( 'Film.HardSub' ), 'HardSub زیرنویس چسبیده (sub_fa)' );
mc_ok( 'soft_fa' === Link_Meta::detect_language( 'Film.SoftSub' ), 'SoftSub زیرنویس جدا است' );
mc_ok( 'dual' === Link_Meta::detect_language( 'Film.Dual.Audio' ), 'Dual دو زبانه است' );
mc_ok( '' === Link_Meta::detect_language( 'Film.1080p' ), 'بدون واژه‌ی زبان، زبان خالی است' );

echo "\n[تشخیص انکودر]\n";
mc_ok( 'PSA' === Link_Meta::detect_encoder( 'Movie.2019.1080p.WEB-DL.x264-PSA' ), 'انکودر پس از آخرین خط‌تیره خوانده می‌شود' );
mc_ok( '' === Link_Meta::detect_encoder( 'Movie.1080p.WEB-DL.x264' ), 'واژه‌های فنی (x264، WEB-DL) انکودر نمی‌شوند' );
mc_ok( 'Group' === Link_Meta::detect_encoder( 'movie', '[Group] Movie 720p' ), 'انکودر از برچسب داخل [] خوانده می‌شود' );
mc_ok( '' === Link_Meta::detect_encoder( 'movie-2019' ), 'عدد خالص انکودر نیست' );

echo "\n[تجزیه‌ی نشانی و برچسب]\n";
$parsed = Link_Meta::parse( 'https://cdn.example.com/files/Movie.2019.1080p.Dual.WEB-DL-PSA.mkv' );
mc_ok( '1080p' === $parsed['quality'], 'تجزیه: کیفیت 1080p' );
mc_ok( 'dual' === $parsed['language'], 'تجزیه: زبان دو زبانه' );
mc_ok( 'PSA' === $parsed['encoder'], 'تجزیه: انکودر PSA' );
mc_ok( false !== strpos( $parsed['name'], 'Movie 2019 1080p' ), 'تجزیه: نام بدون پسوند و با فاصله', $parsed['name'] );

$encoded = Link_Meta::parse( 'https://example.com/d/Film%20Persian%20720p.mp4' );
mc_ok( 'Film Persian 720p' === $encoded['name'], 'نام درصدکدشده‌ی URL رمزگشایی می‌شود', $encoded['name'] );

$label_only = Link_Meta::parse( 'https://example.com/d/abc123', 'کیفیت 480p - دوبله' );
mc_ok( '480p' === $label_only['quality'] && 'dub_fa' === $label_only['language'], 'کیفیت و زبان از برچسب هم خوانده می‌شوند' );

echo "\n[قالب حجم و نام فایل]\n";
mc_ok( '' === Link_Meta::format_size( 0 ), 'حجم صفر خالی است' );
mc_ok( '350MB' === Link_Meta::format_size( 350 * 1048576 ), 'حجم کمتر از یک گیگ به مگابایت', Link_Meta::format_size( 350 * 1048576 ) );
mc_ok( '1.5GB' === Link_Meta::format_size( 1536 * 1048576 ), 'حجم بیش از یک گیگ به گیگابایت (بی‌صفر انتهایی)', Link_Meta::format_size( 1536 * 1048576 ) );
mc_ok( '2GB' === Link_Meta::format_size( 2 * 1073741824 ), 'عدد صحیح گیگ بدون اعشار', Link_Meta::format_size( 2 * 1073741824 ) );

mc_ok(
	'Movie 2019.mkv' === Link_Meta::filename_from_disposition( "attachment; filename*=UTF-8''Movie%202019.mkv" ),
	'Content-Disposition با filename* رمزگشایی می‌شود'
);
mc_ok(
	'a.mp4' === Link_Meta::filename_from_disposition( 'attachment; filename="a.mp4"' ),
	'Content-Disposition با filename ساده خوانده می‌شود'
);
mc_ok( '' === Link_Meta::filename_from_disposition( 'inline' ), 'بدون نام فایل، تهی است' );

/* ---------------------------------------------------------------
 * مسیر سنجش: قرارداد probe و محدودیت نرخ (بدون شبکه‌ی واقعی)
 * ------------------------------------------------------------ */

class WP_Error {
	public $code;
	public $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code = $code;
		$this->data = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
}

class WP_REST_Request implements ArrayAccess {
	private $params;
	public function __construct( $params ) {
		$this->params = $params;
	}
	public function offsetExists( $key ): bool {
		return isset( $this->params[ $key ] );
	}
	public function offsetGet( $key ): mixed {
		return $this->params[ $key ] ?? null;
	}
	public function offsetSet( $key, $value ): void {
		$this->params[ $key ] = $value;
	}
	public function offsetUnset( $key ): void {
		unset( $this->params[ $key ] );
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function rest_ensure_response( $data ) {
	return $data;
}
function current_user_can( $cap ) {
	return true;
}
function get_current_user_id() {
	return 7;
}
function get_transient( $key ) {
	return $GLOBALS['mc_tr'][ $key ] ?? false;
}
function set_transient( $key, $value, $expiry = 0 ) {
	$GLOBALS['mc_tr'][ $key ] = $value;
	return true;
}
function wp_safe_remote_head( $url, $args = array() ) {
	$GLOBALS['mc_http'][] = 'HEAD ' . $url;
	return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-length' => '1048576' ) );
}
function wp_safe_remote_get( $url, $args = array() ) {
	$GLOBALS['mc_http'][] = 'GET ' . $url;
	return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-length' => '1048576' ) );
}
function wp_remote_retrieve_response_code( $response ) {
	return $response['response']['code'] ?? 0;
}
function wp_remote_retrieve_header( $response, $header ) {
	return $response['headers'][ $header ] ?? '';
}
function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}
function __( $text, $domain = '' ) {
	return $text;
}

echo "\n[مسیر سنجش: probe=0 بدون شبکه و بدون محدودیت نرخ]\n";
$GLOBALS['mc_tr']   = array();
$GLOBALS['mc_http'] = array();
$meta = Link_Meta::instance();
$url  = 'https://example.com/d/Movie.2019.1080p.WEB-DL.x264-PSA.mkv';

// سقف سنجش این کاربر پر است؛ probe=0 باید همچنان پاسخ بدهد.
$GLOBALS['mc_tr']['manacore_inspect_7'] = Link_Meta::RATE_MAX;

$parse_only = $meta->inspect_route( new WP_REST_Request( array( 'url' => $url, 'label' => '', 'probe' => false ) ) );
mc_ok( ! is_wp_error( $parse_only ), 'probe=0 با سقف پرِ نرخ هم پاسخ می‌دهد (خطای 429 ندارد)' );
mc_ok( '1080p' === ( $parse_only['quality'] ?? '' ), 'probe=0 کیفیت را از نشانی می‌خواند', $parse_only['quality'] ?? '' );
mc_ok( 'PSA' === ( $parse_only['encoder'] ?? '' ), 'probe=0 انکودر را از نشانی می‌خواند', $parse_only['encoder'] ?? '' );
mc_ok( '' === ( $parse_only['size'] ?? 'x' ), 'probe=0 حجم نمی‌سنجد (حجم خالی)' );
mc_ok( array() === $GLOBALS['mc_http'], 'probe=0 هیچ درخواست خروجی نمی‌سازد', implode( ', ', $GLOBALS['mc_http'] ) );
mc_ok( Link_Meta::RATE_MAX === $GLOBALS['mc_tr']['manacore_inspect_7'], 'probe=0 شمارنده‌ی نرخ را بالا نمی‌برد' );

$size_probe = $meta->inspect_route( new WP_REST_Request( array( 'url' => $url, 'label' => '', 'probe' => true ) ) );
mc_ok( is_wp_error( $size_probe ) && 'manacore_rate_limited' === $size_probe->get_error_code(), 'probe=1 با سقف پر، خطای نرخ (429) می‌دهد' );
mc_ok( array() === $GLOBALS['mc_http'], 'probe=1 پس از رد شدن، شبکه را صدا نمی‌زند' );

$GLOBALS['mc_tr'] = array();
$GLOBALS['mc_http'] = array();
$size_probe = $meta->inspect_route( new WP_REST_Request( array( 'url' => $url, 'label' => '', 'probe' => true ) ) );
mc_ok( ! is_wp_error( $size_probe ) && '1MB' === $size_probe['size'], 'probe=1 حجم را از سرآیند می‌سنجد', $size_probe['size'] ?? '' );
mc_ok( 1 === count( $GLOBALS['mc_http'] ), 'probe=1 یک درخواست خروجی می‌سازد' );
mc_ok( 1 === ( $GLOBALS['mc_tr']['manacore_inspect_7'] ?? 0 ), 'probe=1 شمارنده‌ی نرخ را یکی بالا می‌برد' );

$bad = $meta->inspect_route( new WP_REST_Request( array( 'url' => 'ftp://example.com/a.mkv', 'label' => '', 'probe' => false ) ) );
mc_ok( is_wp_error( $bad ) && 'manacore_invalid_url' === $bad->get_error_code(), 'نشانی غیر http(s) حتی در probe=0 رد می‌شود' );

echo "\n==========================================================\n";
echo "موفق: {$GLOBALS['mc_pass']}   ناموفق: {$GLOBALS['mc_fail']}\n";
echo "==========================================================\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
