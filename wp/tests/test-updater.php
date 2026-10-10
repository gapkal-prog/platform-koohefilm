<?php
/**
 * آزمون سازوکار به‌روزرسانی افزونه.
 *
 * چرا این آزمون هست؟ نقطه‌ی پایانی به‌روزرسانی، تنها جایی از افزونه است که
 * کد از سرور شخص ثالث می‌گیرد و بعد «فایل اجرایی» نصب می‌کند. یک اشتباه
 * کوچک (نشانی HTTP، اعتماد بی‌قید به پاسخ، نبود سنجش شکل نسخه) به نفوذ
 * منجر می‌شود. این آزمون همان قواعد را قفل می‌کند:
 *   ۱. بدون تعریف نشانی، هیچ درخواستی زده نمی‌شود و فهرست افزونه‌ها
 *      دست‌نخورده می‌ماند (نصب داخلی/آفلاین بی‌عارض).
 *   ۲. نشانی غیر‌HTTPS یا نامعتبر پذیرفته نمی‌شود.
 *   ۳. پاسخ خام دوردست پاک‌سازی می‌شود: کلید ناشناخته دور ریخته می‌شود،
 *      فایل بسته‌ی HTTP رد می‌شود، نسخه‌ی آغشته به متن بی‌اثر می‌شود و
 *      فهرست تغییرها از تگ خطرناک پاک می‌گردد.
 *   ۴. نسخه‌ی مساوی یا قدیمی‌تر به‌روزرسانی نمی‌سازد؛ نسخه‌ی تازه‌تر
 *      می‌سازد و افزونه از `no_update` برداشته می‌شود.
 *   ۵. کش موفق شش‌ساعته و کش ناموفق پانزده‌دقیقه‌ای است تا سرور خاموش
 *      پشت‌سرهم صدا نشود.
 *
 * اجرا: php wp/tests/test-updater.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_FILE', dirname( __DIR__ ) . '/plugins/manacore-core/manacore-core.php' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );
define( 'HOUR_IN_SECONDS', 3600 );
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

$GLOBALS['mc_transients'] = array();
$GLOBALS['mc_filters']    = array();
$GLOBALS['mc_http']       = array();
$GLOBALS['mc_calls']      = array();

function get_transient( $key ) {
	return isset( $GLOBALS['mc_transients'][ $key ] ) ? $GLOBALS['mc_transients'][ $key ] : false;
}
function set_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['mc_transients'][ $key ]            = $value;
	$GLOBALS['mc_transients'][ $key . '::ttl' ] = $ttl;

	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['mc_transients'][ $key ] );

	return true;
}
function add_filter( $tag, $callback, $priority = 10, $accepted = 1 ) {
	$GLOBALS['mc_filters'][ $tag ][ $priority ][] = $callback;

	return true;
}
function apply_filters( $tag, $value ) {
	if ( empty( $GLOBALS['mc_filters'][ $tag ] ) ) {
		return $value;
	}

	$hooks = $GLOBALS['mc_filters'][ $tag ];
	ksort( $hooks );

	foreach ( $hooks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$value = call_user_func( $callback, $value );
		}
	}

	return $value;
}
function add_action( $tag, $callback, $priority = 10, $accepted = 1 ) {
	return add_filter( $tag, $callback, $priority, $accepted );
}
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}
function plugin_basename( $file ) {
	return 'manacore-core/manacore-core.php';
}
function esc_url_raw( $url ) {
	return filter_var( (string) $url, FILTER_SANITIZE_URL );
}
function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function wp_kses_post( $text ) {
	return preg_replace( '#<(script|iframe)[^>]*>.*?</\1>#is', '', (string) $text );
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function wp_http_validate_url( $url ) {
	$url  = (string) $url;
	$host = wp_parse_url( $url, PHP_URL_HOST );

	if ( ! $host || ! wp_parse_url( $url, PHP_URL_SCHEME ) ) {
		return false;
	}

	if ( preg_match( '/^(localhost|127\.0\.0\.1)$/', $host ) ) {
		return false;
	}

	return $url;
}
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['mc_calls'][] = array( 'url' => $url, 'args' => $args );

	if ( isset( $GLOBALS['mc_http']['error'] ) ) {
		return new WP_Error( 'http', $GLOBALS['mc_http']['error'] );
	}

	return array( 'body' => isset( $GLOBALS['mc_http']['body'] ) ? $GLOBALS['mc_http']['body'] : '' );
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function wp_remote_retrieve_response_code( $response ) {
	return isset( $GLOBALS['mc_http']['code'] ) ? $GLOBALS['mc_http']['code'] : 200;
}
function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}
function __( $text, $domain = '' ) {
	return $text;
}
function esc_html__( $text, $domain = '' ) {
	return $text;
}
function json_fixture( $data ) {
	return json_encode( $data, JSON_UNESCAPED_UNICODE );
}

class WP_Error {
	public $code;
	public $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
}

require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-updater.php';

use ManaCore\Core\Updater;

echo "\nآزمون به‌روزرسان ManaCore\n";
echo str_repeat( '-', 46 ) . "\n";

/* ---------------------------------------------------------------
 * ۱) بدون نشانی: بی‌اثر کامل
 * ------------------------------------------------------------ */

mc_ok( '' === Updater::endpoint(), 'بدون تعریف نشانی، نقطه‌ی پایانی خالی است' );

$GLOBALS['mc_calls'] = array();
$before              = (object) array( 'response' => array() );
$after               = Updater::instance()->inject( $before );

mc_ok( 0 === count( $GLOBALS['mc_calls'] ), 'بدون نشانی هیچ درخواست شبکه‌ای زده نمی‌شود', (string) count( $GLOBALS['mc_calls'] ) );
mc_ok( array() === $after->response, 'بدون نشانی، فهرست به‌روزرسانی دست‌نخورده می‌ماند' );

$defaults = Updater::instance()->details( 'پیش‌فرض', 'plugin_information', (object) array( 'slug' => 'manacore-core' ) );
mc_ok( 'پیش‌فرض' === $defaults, 'پنجره‌ی جزئیات بدون داده‌ی دوردست دست‌نخورده می‌ماند' );

/* ---------------------------------------------------------------
 * ۲) پاک‌سازی پاسخ دوردست
 * ------------------------------------------------------------ */

$clean = Updater::sanitize(
	array(
		'version'      => '1.2.0 <script>alert(1)</script>',
		'download_url' => 'http://shop.example.test/pack.zip',
		'details_url'  => 'https://shop.example.test/changelog',
		'requires'     => '6.4',
		'tested'       => '6.8',
		'requires_php' => '7.4',
		'last_updated' => '2026-09-01 10:00:00',
		'changelog'    => '<p>رفع باگ</p><script>evil()</script>',
		'evil_key'     => 'هرچیز',
	)
);

mc_ok( ! isset( $clean['evil_key'] ), 'کلید ناشناخته‌ی پاسخ دور ریخته می‌شود' );
mc_ok( '' === $clean['version'], 'نسخه‌ی آغشته به متن اضافه بی‌اثر می‌شود', $clean['version'] );
mc_ok( '' === $clean['download_url'], 'فایل بسته‌ی HTTP رد می‌شود' );
mc_ok( 'https://shop.example.test/changelog' === $clean['details_url'], 'نشانی جزئیات HTTPS حفظ می‌شود' );
mc_ok( '6.4' === $clean['requires'] && '6.8' === $clean['tested'], 'شرط نسخه‌ی وردپرس حفظ می‌شود' );
mc_ok( false !== strpos( $clean['changelog'], 'رفع باگ' ), 'متن فهرست تغییرها می‌ماند' );
mc_ok( false === strpos( $clean['changelog'], 'evil' ), 'تگ خطرناک از فهرست تغییرها حذف می‌شود' );

/* الگوهای درست نسخه باید دست‌نخورده بمانند. */
foreach ( array( '1.1.0', '2.0', '1.2.3-beta.1', '10.0.0' ) as $sample ) {
	$probe = Updater::sanitize(
		array(
			'version'      => $sample,
			'download_url' => 'https://shop.example.test/p.zip',
		)
	);

	mc_ok( $sample === $probe['version'], 'نسخه‌ی معتبر «' . $sample . '» حفظ می‌شود', $probe['version'] );
}

/* ---------------------------------------------------------------
 * ۳) بررسی نسخه: تازه‌تر / قدیمی‌تر / خطا
 * ------------------------------------------------------------ */

$GLOBALS['mc_http'] = array(
	'code' => 200,
	'body' => json_fixture(
		array(
			'version'      => '1.1.0',
			'download_url' => 'https://shop.example.test/manacore-1.1.0.zip',
			'details_url'  => 'https://shop.example.test/manacore',
			'requires'     => '6.4',
			'tested'       => '6.8',
			'requires_php' => '7.4',
		)
	),
);

/* نشانی آزمون فقط از راه فیلترِ عمومی خود افزونه تعریف می‌شود. */
add_filter(
	'manacore_update_endpoint',
	static function () {
		return 'https://shop.example.test/api/update.json';
	}
);

mc_ok( 'https://shop.example.test/api/update.json' === Updater::endpoint(), 'فیلتر نشانی به‌روزرسانی کار می‌کند' );

$info = Updater::remote( true );
mc_ok( '1.1.0' === $info['version'], 'نسخه‌ی تازه از پاسخ خوانده می‌شود', $info['version'] );
mc_ok( HOUR_IN_SECONDS * 6 === $GLOBALS['mc_transients'][ Updater::CACHE_KEY . '::ttl' ], 'کش موفق شش‌ساعته است', (string) $GLOBALS['mc_transients'][ Updater::CACHE_KEY . '::ttl' ] );

$GLOBALS['mc_calls'] = array();
Updater::remote();
mc_ok( 0 === count( $GLOBALS['mc_calls'] ), 'خواندن بعدی از کش انجام می‌شود، نه شبکه' );

$plugin    = 'manacore-core/manacore-core.php';
$transient = (object) array(
	'response'  => array(),
	'no_update' => array( $plugin => (object) array( 'slug' => 'manacore-core' ) ),
);

$result = Updater::instance()->inject( $transient );

mc_ok( isset( $result->response[ $plugin ] ), 'نسخه‌ی تازه در فهرست به‌روزرسانی می‌نشیند' );
mc_ok( ! isset( $result->no_update[ $plugin ] ), 'افزونه از فهرست no_update برداشته می‌شود' );
mc_ok( '1.1.0' === $result->response[ $plugin ]->new_version, 'شماره‌ی نسخه درست است' );
mc_ok( 'https://shop.example.test/manacore-1.1.0.zip' === $result->response[ $plugin ]->package, 'بسته‌ی HTTPS به وردپرس داده می‌شود' );

$details = Updater::instance()->details( 'پیش‌فرض', 'plugin_information', (object) array( 'slug' => 'manacore-core' ) );
mc_ok( is_object( $details ) && '1.1.0' === $details->version, 'پنجره‌ی جزئیات نسخه‌ی تازه را نشان می‌دهد' );
mc_ok( is_object( $details ) && isset( $details->sections['changelog'] ), 'پنجره‌ی جزئیات بخش فهرست تغییرها دارد' );

/* افزونه‌ی دیگر نباید از پنجره‌ی ما پاسخ بگیرد. */
$other = Updater::instance()->details( null, 'plugin_information', (object) array( 'slug' => 'akismet' ) );
mc_ok( null === $other, 'درخواست جزئیات افزونه‌ی دیگر دست‌کاری نمی‌شود' );

/* نسخه‌ی قدیمی‌تر از نصب‌شده: هیچ به‌روزرسانی‌ای ساخته نمی‌شود. */
$GLOBALS['mc_http']['body'] = json_fixture(
	array(
		'version'      => '0.9.0',
		'download_url' => 'https://shop.example.test/manacore-0.9.0.zip',
	)
);

Updater::flush();

$transient = (object) array( 'response' => array(), 'no_update' => array() );
$result    = Updater::instance()->inject( $transient );

mc_ok( array() === $result->response, 'نسخه‌ی قدیمی‌تر به‌عنوان به‌روزرسانی پیشنهاد نمی‌شود' );

/* پاسخ نامعتبر: کش کوتاه و بی‌اثری. */
$GLOBALS['mc_http'] = array( 'code' => 500, 'body' => 'خطای سرور' );
Updater::flush();
$GLOBALS['mc_calls'] = array();

$info = Updater::remote( true );
mc_ok( '' === $info['version'], 'پاسخ خطا نسخه‌ای برنمی‌گرداند' );
mc_ok( MINUTE_IN_SECONDS * 15 === $GLOBALS['mc_transients'][ Updater::CACHE_KEY . '::ttl' ], 'کش ناموفق کوتاه است تا سرور خاموش پشت‌سرهم صدا نشود', (string) $GLOBALS['mc_transients'][ Updater::CACHE_KEY . '::ttl' ] );

mc_ok( 1 === count( $GLOBALS['mc_calls'] ), 'پاسخ نامعتبر دوباره درخواست نمی‌زند', (string) count( $GLOBALS['mc_calls'] ) );

/* ---------------------------------------------------------------
 * ۴) وضعیت نشان‌داده‌شده در پنل
 * ------------------------------------------------------------ */

$status = Updater::status();
mc_ok( '1.0.0' === $status['current'], 'وضعیت، نسخه‌ی نصب‌شده را برمی‌گرداند' );
mc_ok( true === $status['endpoint'], 'وضعیت، تعریف‌شدن نشانی را نشان می‌دهد' );
mc_ok( false === $status['available'], 'پس از پاسخ خطا، «نسخه‌ی تازه» اعلام نمی‌شود' );

/* پس از به‌روزرسانی واقعی، کش باید پاک شود. */
set_transient( Updater::CACHE_KEY, array( 'version' => '9.9.9' ), HOUR_IN_SECONDS );

Updater::instance()->after_upgrade( null, array( 'plugins' => array( $plugin ) ) );
mc_ok( false === get_transient( Updater::CACHE_KEY ), 'به‌روزرسانی افزونه‌ی ما کش نسخه را پاک می‌کند' );

set_transient( Updater::CACHE_KEY, array( 'version' => '9.9.9' ), HOUR_IN_SECONDS );

Updater::instance()->after_upgrade( null, array( 'plugins' => array( 'akismet/akismet.php' ) ) );
mc_ok( false !== get_transient( Updater::CACHE_KEY ), 'به‌روزرسانی افزونه‌ی دیگر کش ما را پاک نمی‌کند' );

/* ---------------------------------------------------------------
 * ۵) نشانی نامعتبر
 * ------------------------------------------------------------ */

$GLOBALS['mc_filters']['manacore_update_endpoint'] = array(
	10 => array(
		static function () {
			return 'http://shop.example.test/api/update.json';
		},
	),
);

mc_ok( '' === Updater::endpoint(), 'نشانی HTTP پذیرفته نمی‌شود' );

$GLOBALS['mc_filters']['manacore_update_endpoint'] = array(
	10 => array(
		static function () {
			return 'https://';
		},
	),
);

mc_ok( '' === Updater::endpoint(), 'نشانی ناقص پذیرفته نمی‌شود' );

$GLOBALS['mc_filters']['manacore_update_endpoint'] = array(
	10 => array(
		static function () {
			return 'https://localhost/api/update.json';
		},
	),
);

mc_ok( '' === Updater::endpoint(), 'نشانی میزبان محلی پذیرفته نمی‌شود' );

/* ---------------------------------------------------------------
 * جمع‌بندی
 * ------------------------------------------------------------ */

echo "\n" . str_repeat( '-', 46 ) . "\n";
echo 'نتیجه: ' . $GLOBALS['mc_pass'] . " موفق، " . $GLOBALS['mc_fail'] . " ناموفق\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
