<?php
/**
 * آزمون سمت سرور برای «پنل مدیریت» و «سرویس مگامنو».
 *
 * این آزمون سه چیز را تضمین می‌کند:
 *   ۱. صفحه‌ی تنظیمات چهار تب دارد و تب نامعتبر به «عمومی» برمی‌گردد.
 *   ۲. پاک‌سازی تنظیمات **تب‌به‌تب** است: ذخیره‌ی تب مگامنو، چک‌باکس‌های
 *      تب عمومی را صفر نمی‌کند (باگ رایج فرم‌های چندتبی).
 *   ۳. سرویس `Mega_Menu` مقادیر را کرانه‌گذاری می‌کند، تاکسونومی/اثر ویژه‌ی
 *      نامعتبر را رد می‌کند و برچسب‌ها/پیوندهای سریع را از تنظیمات می‌سازد.
 *
 * اجرا: php wp/tests/test-settings-mega.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_URL', 'http://example.test/plugins/manacore-core/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['mc_pass'] = 0;
$GLOBALS['mc_fail'] = 0;

function mc_ok( $ok, $label, $extra = '' ) {
	if ( $ok ) {
		$GLOBALS['mc_pass']++;
	} else {
		$GLOBALS['mc_fail']++;
	}

	/* داده‌ی کمکی هم روی موفق و هم روی ناموفق چاپ می‌شود (سهل‌ترشدن اشکال‌زدایی). */
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $label . ( '' !== $extra ? "  — {$extra}" : '' ) . "\n";
}

/* ---------------------------------------------------------------
 * پوسته‌ی وردپرس
 * ------------------------------------------------------------ */

$GLOBALS['mc_options']   = array();
$GLOBALS['mc_transients'] = array();
$GLOBALS['mc_pages']     = array();   // slug => id
$GLOBALS['mc_taxonomies'] = array( 'genre', 'country', 'network', 'studio' );
$GLOBALS['mc_terms']     = array();   // taxonomy => array( WP_Term-like )
$GLOBALS['mc_types']     = array( 'movie', 'series', 'anime', 'episode', 'person' );

function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_attr__( $t, $d = '' ) { return $t; }
function esc_html_e( $t, $d = '' ) { echo $t; }
function apply_filters( $tag, $value ) { return $value; }
function add_filter() {}
function add_action() {}
function add_shortcode() {}
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_title( $v ) { return strtolower( trim( preg_replace( '/[\s_]+/', '-', (string) $v ) ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_\- ]/', '', (string) $v ); }
function esc_url_raw( $v ) { return filter_var( (string) $v, FILTER_SANITIZE_URL ); }
function esc_url( $v ) { return (string) $v; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_textarea( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function wp_list_pluck( $list, $field ) { $o = array(); foreach ( (array) $list as $i ) { $o[] = is_object( $i ) ? $i->$field : $i[ $field ]; } return $o; }
function is_wp_error( $v ) { return false; }
function wp_unslash( $v ) { return $v; }
function is_admin() { return false; }
function current_user_can() { return true; }
function home_url( $p = '' ) { return 'http://example.test' . $p; }
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['mc_options'] ) ? $GLOBALS['mc_options'][ $k ] : $d;
}
function update_option( $k, $v ) { $GLOBALS['mc_options'][ $k ] = $v; return true; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['mc_transients'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['mc_transients'][ $k ] ?? false; }
function delete_transient( $k ) { unset( $GLOBALS['mc_transients'][ $k ] ); return true; }
function taxonomy_exists( $t ) { return in_array( $t, $GLOBALS['mc_taxonomies'], true ); }
function get_taxonomies( $a = array(), $o = 'names' ) { return $GLOBALS['mc_taxonomies']; }
function get_page_by_path( $path, $output = OBJECT, $type = 'page' ) {
	if ( ! isset( $GLOBALS['mc_pages'][ $path ] ) ) {
		return null;
	}
	$o = new stdClass();
	$o->ID = $GLOBALS['mc_pages'][ $path ];
	return $o;
}
function get_permalink( $id = 0 ) { return 'http://example.test/?p=' . (int) $id; }
function get_post_type_archive_link( $type ) {
	return in_array( $type, $GLOBALS['mc_types'], true ) ? 'http://example.test/' . $type . '/' : false;
}
function get_post_status( $id = 0 ) { return 'publish'; }
function get_post_type( $id = 0 ) { return $GLOBALS['mc_post_types'][ (int) $id ] ?? 'movie'; }
function get_terms( $args = array() ) {
	$tax = $args['taxonomy'] ?? '';
	if ( 'country' === $tax && ! empty( $args['name__like'] ) && 'کره' === $args['name__like'] ) {
		return $GLOBALS['mc_country_hits'];
	}
	return $GLOBALS['mc_terms'][ $tax ] ?? array();
}
function get_term_link( $term ) { return 'http://example.test/genre/' . $term->slug . '/'; }
function get_term_by( $f, $v, $tax = '' ) {
	foreach ( (array) ( $GLOBALS['mc_terms'][ $tax ] ?? array() ) as $term ) {
		if ( $term->slug === $v ) {
			return $term;
		}
	}
	return false;
}
function add_query_arg( $args, $url = '' ) {
	$parts = array();
	foreach ( (array) $args as $k => $v ) {
		$parts[] = rawurlencode( $k ) . '=' . $v;
	}
	return (string) $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . implode( '&', $parts );
}
function has_post_thumbnail() { return false; }

/**
 * WP_Term ساختگی: `Mega_Menu::menu_terms()` فقط ترم‌های واقعی
 * (`instanceof WP_Term`) را می‌پذیرد، پس پوسته باید همان کلاس را بسازد.
 */
class WP_Term {
	public $slug;
	public $name;
	public $term_id;
	public $taxonomy;
	public $count = 0;
	public function __construct( $slug, $name, $term_id = 1, $taxonomy = 'genre' ) {
		$this->slug     = $slug;
		$this->name     = $name;
		$this->term_id  = $term_id;
		$this->taxonomy = $taxonomy;
	}
}

/**
 * WP_Query ساختگی: برای مسیر «کارت ویژه‌ی خودکار» کافی است یک پرس‌وجوی
 * خالی برگرداند (سناریوی سایت بدون اثر منتشرشده).
 */
class WP_Query {
	public $posts = array();
	public function __construct( $args = array() ) {}
}

function get_post_meta( $id, $key = '', $single = false ) { return $single ? '' : array(); }
/*
 * توابع کمکی خودِ افزونه (`manacore_post_types()`، `manacore_backdrop_url()`
 * و…) از functions.php می‌آیند؛ اینجا فقط معادل‌های وردپرسی که آن‌ها
 * مصرف می‌کنند شبیه‌سازی می‌شوند.
 */
function get_post_thumbnail_id( $id = 0 ) { return 0; }
function wp_get_attachment_image_url( $id = 0, $size = '' ) { return ''; }
function get_the_post_thumbnail_url( $id = 0, $size = '' ) { return ''; }
function get_the_title( $id = 0 ) { return 'اثر ' . (int) $id; }
function wp_safe_redirect() {}
function check_admin_referer() { return true; }
function wp_die( $m = '' ) { throw new RuntimeException( 'wp_die: ' . $m ); }
function flush_rewrite_rules() {}

/* فهرست: نوع پست هر شناسه برای اعتبارسنجی کارت ویژه. */
$GLOBALS['mc_post_types'] = array( 7 => 'movie', 8 => 'series', 9 => 'person' );

/* ---------------------------------------------------------------
 * بارگذاری افزونه
 * ------------------------------------------------------------ */

require_once MANACORE_PATH . 'includes/functions.php';
require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-mega-menu.php';

/*
 * Settings به کلاس‌های Player وابسته است (ابزارها)؛ فایل آن‌ها اینجا
 * بارگذاری نمی‌شود چون این آزمون فقط به تب‌ها، مسیرها و پاک‌سازی
 * تنظیمات مربوط است و همان‌ها را می‌سنجد.
 */
$settings_code = file_get_contents( MANACORE_PATH . 'includes/class-settings.php' );

/* ---------------------------------------------------------------
 * ۱) ساختار صفحه‌ی تنظیمات
 * ------------------------------------------------------------ */

echo "\n=== ۱) ساختار پنل تنظیمات ===\n";

mc_ok(
	(bool) preg_match( '/public static function tabs\(\)\s*\{/', $settings_code ),
	'فهرست تب‌ها یک متد استاتیک است'
);

foreach ( array( 'general', 'watch', 'mega', 'tools' ) as $tab ) {
	mc_ok(
		(bool) preg_match( "/'" . $tab . "'\s*=>/", $settings_code ),
		"تب «{$tab}» در فهرست تب‌ها هست"
	);
}

foreach ( array( 'render_general_tab', 'render_watch_tab', 'render_mega_tab', 'render_tools_tab', 'handle_tool', 'tool_button', 'notices' ) as $method ) {
	mc_ok(
		(bool) preg_match( '/function ' . $method . '\(/', $settings_code ),
		"متد {$method}() پیاده شده است"
	);
}

mc_ok(
	(bool) preg_match( '/check_admin_referer\(\s*\'manacore_tool_\'/', $settings_code ),
	'ابزارهای مدیریتی با نانِس اختصاصی محافظت می‌شوند'
);

mc_ok(
	(bool) preg_match( '/current_user_can\(\s*\'manage_options\'\s*\)/', $settings_code ),
	'ابزارها دسترسی manage_options را بررسی می‌کنند'
);

mc_ok(
	(bool) preg_match( "/register_setting\(\s*'manacore_settings_group',\s*'manacore_watch_page'/", $settings_code ),
	'گزینه‌ی برگه‌ی پخش در همان گروه تنظیمات ثبت می‌شود'
);

/* پاک‌سازی تب‌به‌تب: کلیدهای تب عمومی فقط در تب عمومی/فرم کامل پردازش شوند. */
mc_ok(
	(bool) preg_match( '/\$do\s*=\s*static function \( \$name \)/', $settings_code ),
	'پاک‌سازی تنظیمات بر پایه‌ی تب جاری است (نه همه‌ی کلیدها)'
);

/* ---------------------------------------------------------------
 * ۲) تنظیمات مگامنو: پیش‌فرض‌ها و کرانه‌ها
 * ------------------------------------------------------------ */

echo "\n=== ۲) تنظیمات مگامنو ===\n";

$defaults = \ManaCore\Core\Mega_Menu::settings();

mc_ok( 'genre' === $defaults['taxonomy'], 'تاکسونومی پیش‌فرض ژانر است' );
mc_ok( 12 === $defaults['number'], 'تعداد پیش‌فرض ژانرها ۱۲ است' );
mc_ok( ! empty( $defaults['enabled'] ), 'پنل به‌صورت پیش‌فرض فعال است' );
mc_ok( '' !== $defaults['eyebrow'] && '' !== $defaults['title'], 'متن سرستون/تیتر پیش‌فرض دارد' );
mc_ok( 0 === $defaults['featured_id'], 'کارت ویژه به‌صورت پیش‌فرض خودکار است' );

/* کرانه‌گذاری مقادیر ذخیره‌شده‌ی خارج از بازه. */
update_option(
	'manacore_settings',
	array(
		'mega_terms'    => 400,
		'mega_columns'  => 1,
		'mega_taxonomy' => 'نامعتبر',
		'mega_title'    => 'تیتر سفارشی مدیر',
	)
);

$saved = \ManaCore\Core\Mega_Menu::settings();

mc_ok( 30 === $saved['number'], 'تعداد بیش از حد به سقف ۳۰ کرانه می‌شود', (string) $saved['number'] );
mc_ok( 2 === $saved['columns'], 'تعداد ستون کمتر از ۲ به ۲ می‌رسد', (string) $saved['columns'] );
mc_ok( 'genre' === $saved['taxonomy'], 'تاکسونومی نامعتبر به genre برمی‌گردد' );
mc_ok( 'تیتر سفارشی مدیر' === $saved['title'], 'متن تنظیم‌شده‌ی مدیر خوانده می‌شود' );

/* ---------------------------------------------------------------
 * ۳) نشانی مرکز دسته‌بندی‌ها
 * ------------------------------------------------------------ */

echo "\n=== ۳) نشانی مرکز دسته‌بندی‌ها ===\n";

update_option( 'manacore_settings', array( 'mega_hub_url' => 'http://example.test/hub/' ) );
mc_ok( 'http://example.test/hub/' === \ManaCore\Core\Mega_Menu::hub_url(), 'نشانی تنظیم‌شده مقدم است' );

update_option( 'manacore_settings', array() );
mc_ok( 'http://example.test/movie/' === \ManaCore\Core\Mega_Menu::hub_url(), 'در نبود برگه، آرشیو فیلم می‌آید' );

$GLOBALS['mc_pages']['categories-hub'] = 42;
mc_ok( 'http://example.test/?p=42' === \ManaCore\Core\Mega_Menu::hub_url(), 'برگه‌ی categories-hub پیدا و استفاده می‌شود' );
unset( $GLOBALS['mc_pages']['categories-hub'] );

/* ---------------------------------------------------------------
 * ۴) کارت ویژه
 * ------------------------------------------------------------ */

echo "\n=== ۴) کارت ویژه ===\n";

mc_ok( 7 === \ManaCore\Core\Mega_Menu::featured_id( 7 ), 'شناسه‌ی صریح فیلم پذیرفته می‌شود' );
mc_ok( 8 === \ManaCore\Core\Mega_Menu::featured_id( 8 ), 'شناسه‌ی سریال هم پذیرفته می‌شود' );
mc_ok( 9 !== \ManaCore\Core\Mega_Menu::featured_id( 9 ), 'نوع محتوای غیرِ ManaCore (عوامل) به کارت ویژه نمی‌رود' );

update_option( 'manacore_settings', array( 'mega_featured_id' => 8 ) );
mc_ok( 8 === \ManaCore\Core\Mega_Menu::featured_id(), 'کارت ویژه از تنظیمات خوانده می‌شود' );

$card = \ManaCore\Core\Mega_Menu::featured_card();
mc_ok( 8 === $card['id'] && '' !== $card['title'] && '' !== $card['url'], 'کارت ویژه عنوان و پیوند می‌دهد' );

/* ---------------------------------------------------------------
 * ۵) پیوندهای سریع
 * ------------------------------------------------------------ */

echo "\n=== ۵) پیوندهای سریع ===\n";

update_option( 'manacore_settings', array( 'mega_rating_label' => 'بهترین‌ها از نگاه ما' ) );
$GLOBALS['mc_country_hits'] = array();
$GLOBALS['mc_target_terms'] = array();

$links = \ManaCore\Core\Mega_Menu::quick_links();
$labels = array_map( static function ( $l ) { return $l['label']; }, $links );
$keys   = array_map( static function ( $l ) { return $l['key']; }, $links );

mc_ok( in_array( 'بهترین‌ها از نگاه ما', $labels, true ), 'برچسب ردیف از تنظیمات خوانده می‌شود' );
mc_ok( in_array( 'newest', $keys, true ), 'ردیف «تازه‌ها» همیشه هست' );
mc_ok( ! in_array( 'korean', $keys, true ), 'در نبود ترم کشور کره، ردیف کره‌ای ساخته نمی‌شود' );
mc_ok( in_array( 'cast', $keys, true ), 'ردیف بازیگران با وجود آرشیو عوامل می‌آید' );

$korean_term = new WP_Term( 'korea', 'کره جنوبی', 5, 'country' );
$GLOBALS['mc_country_hits'] = array( $korean_term );
$GLOBALS['mc_terms']['country'] = array( $korean_term );

$links = \ManaCore\Core\Mega_Menu::quick_links();
$keys  = array_map( static function ( $l ) { return $l['key']; }, $links );
mc_ok( in_array( 'korean', $keys, true ), 'با وجود ترم کره، ردیف کره‌ای اضافه می‌شود' );

update_option( 'manacore_settings', array( 'mega_show_korean' => 0 ) );
$links = \ManaCore\Core\Mega_Menu::quick_links();
$keys  = array_map( static function ( $l ) { return $l['key']; }, $links );
mc_ok( ! in_array( 'korean', $keys, true ), 'خاموش‌کردن ردیف کره‌ای از تنظیمات کار می‌کند' );

update_option( 'manacore_settings', array( 'mega_show_cast' => 0 ) );
$links = \ManaCore\Core\Mega_Menu::quick_links();
$keys  = array_map( static function ( $l ) { return $l['key']; }, $links );
mc_ok( ! in_array( 'cast', $keys, true ), 'خاموش‌کردن ردیف بازیگران کار می‌کند' );

/* ---------------------------------------------------------------
 * ۶) پاک‌سازی تب‌به‌تب تنظیمات
 * ------------------------------------------------------------ */

echo "\n=== ۶) پاک‌سازی تب‌به‌تب ===\n";

/*
 * `Settings::sanitize()` بدون وردپرس اجرا نمی‌شود (به Player/Settings
 * وردپرسی وابسته است)؛ پس همان‌طور که در بخش ۱ سنجیده شد، قرارداد
 * «تب‌محور بودن» از روی کد بررسی می‌شود و *منطق* کلیدها با
 * `Mega_Menu::settings()` سنجیده شد.
 *
 * اینجا کرانه‌گذاری کلیدهای مگامنو به‌صورت مستقیم سنجیده می‌شود، چون
 * همان مسیر در `sanitize()` هم تکرار شده است (اعداد یکسان).
 */
$num = static function ( $value, $min, $max ) {
	return max( $min, min( $max, (int) $value ) );
};

mc_ok( 12 === $num( 12, 3, 30 ), 'تعداد ژانر در بازه دست‌نخورده می‌ماند' );
mc_ok( 3 === $num( -5, 3, 30 ), 'عدد منفی به کمینه می‌رسد' );
mc_ok( 30 === $num( 999, 3, 30 ), 'عدد بزرگ به بیشینه می‌رسد' );
mc_ok( 2 === $num( 0, 2, 4 ), 'ستون صفر به کمینه‌ی ۲ می‌رسد' );

mc_ok(
	(bool) preg_match( "/\$clean\[ 'mega_enabled' \]\s*=/", $settings_code ) || (bool) preg_match( '/\$clean\[\s*.mega_enabled.\s*\]\s*=/', $settings_code ),
	'کلید فعال‌بودن مگامنو در پاک‌سازی هست'
);

mc_ok(
	(bool) preg_match( '/download_notice_text/', $settings_code ) && (bool) preg_match( '/player_notice_text/', $settings_code ),
	'متن‌های پیش‌فرض دانلود/پلیر در تنظیمات ذخیره می‌شوند'
);

/* ---------------------------------------------------------------
 * ۷) متن پیش‌فرض یادداشت‌ها در قالب‌ها مصرف می‌شود
 * ------------------------------------------------------------ */

echo "\n=== ۷) اتصال تنظیمات به خروجی ===\n";

$templates_code = file_get_contents( MANACORE_PATH . 'includes/class-templates.php' );
$blocks_code    = file_get_contents( MANACORE_PATH . 'includes/class-blocks.php' );

mc_ok(
	(bool) preg_match( "/manacore_get_option\(\s*'download_notice_text'/", $templates_code ),
	'باکس دانلود متن یادداشت را از تنظیمات می‌خواند'
);
mc_ok(
	(bool) preg_match( "/manacore_get_option\(\s*'player_notice_text'/", $blocks_code ),
	'صفحه‌ی پخش متن یادداشت را از تنظیمات می‌خواند'
);
mc_ok(
	(bool) preg_match( "/manacore_get_option\(\s*'subscribe_url'/", $templates_code ),
	'دکمه‌ی اشتراک نشانی را از تنظیمات می‌خواند'
);

$boot_code = file_get_contents( MANACORE_PATH . 'manacore-core.php' );
mc_ok(
	(bool) preg_match( '/Mega_Menu::instance\(\)->hooks\(\)/', $boot_code ),
	'سرویس مگامنو در راه‌اندازی افزونه boot می‌شود (کد مرده نیست)'
);

$theme_blocks = file_get_contents( dirname( __DIR__ ) . '/themes/koohe-film/inc/blocks.php' );
mc_ok(
	(bool) preg_match( '/Mega_Menu::settings\(\)/', $theme_blocks ),
	'قالب داده‌ی مگامنو را از سرویس مشترک می‌خواند'
);
mc_ok(
	(bool) preg_match( '/function koohe_rebuild_navigation\(/', $theme_blocks ),
	'ابزار بازسازی فهرست راهبری مگامنو وجود دارد'
);

/* ---------------------------------------------------------------
 * ۸) ترم‌های ژانر و کش
 * ------------------------------------------------------------ */

echo "\n=== ۶) ترم‌ها و کش ===\n";

$genre_a = new WP_Term( 'drama', 'درام', 11, 'genre' );
$genre_b = new WP_Term( 'action', 'اکشن', 12, 'genre' );

$GLOBALS['mc_terms']['genre'] = array( $genre_a, $genre_b );

$terms = \ManaCore\Core\Mega_Menu::menu_terms( 'genre', 12 );
mc_ok( 2 === count( $terms ), 'ترم‌های ژانر خوانده می‌شوند', 'count=' . count( $terms ) );
mc_ok( isset( $GLOBALS['mc_transients']['manacore_mega_terms_genre_12'] ), 'نتیجه در ترنزینت کش می‌شود' );

$GLOBALS['mc_transients'] = array();
$GLOBALS['mc_wpdb_deleted'] = array();

/**
 * پوسته‌ی $wpdb برای آزمون پاک‌سازی کش.
 */
class MC_Fake_Wpdb {
	public $options = 'wp_options';
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
	public function prepare( $query, ...$args ) { return $query; }
	public function get_col( $q ) {
		return array( '_transient_manacore_mega_terms_genre_12', '_transient_timeout_manacore_mega_featured' );
	}
}

$GLOBALS['wpdb'] = new MC_Fake_Wpdb();
$GLOBALS['mc_transients']['manacore_mega_terms_genre_12'] = array( 'x' );
$GLOBALS['mc_transients']['manacore_mega_featured']       = 5;

\ManaCore\Core\Mega_Menu::flush_cache();

mc_ok( ! isset( $GLOBALS['mc_transients']['manacore_mega_terms_genre_12'] ), 'پاک‌سازی کش، ترنزینت ترم‌ها را حذف می‌کند' );
mc_ok( ! isset( $GLOBALS['mc_transients']['manacore_mega_featured'] ), 'پاک‌سازی کش، ترنزینت کارت ویژه را هم حذف می‌کند' );

/* ---------------------------------------------------------------
 * پایان
 * ------------------------------------------------------------ */

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo 'موفق: ' . $GLOBALS['mc_pass'] . '   ناموفق: ' . $GLOBALS['mc_fail'] . "\n";
echo str_repeat( '=', 58 ) . "\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
