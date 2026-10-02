<?php
/**
 * پوسته‌ی ساختگی وردپرس برای استخراج داده‌های ویرایشگر بدون نصب وردپرس.
 * خروجی: /tmp/payload.json و /tmp/registry.json
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_URL', 'http://example.test/wp-content/plugins/manacore-core/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );

/* ---------- توابع پایه‌ی وردپرس ---------- */

function __( $text, $domain = '' ) { return $text; }
function _x( $text, $ctx = '', $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_attr__( $text, $domain = '' ) { return $text; }
function translate_user_role( $role ) { return $role; }
function apply_filters( $tag, $value ) { return $value; }
function add_filter() {}
function add_action() {}
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function is_wp_error( $v ) { return false; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function get_option( $key, $default = false ) { return $default; }
function update_option( $key, $value ) { return true; }
function get_post_meta( $id, $key = '', $single = false ) { return $single ? '' : array(); }
function get_current_user_id() { return 0; }
function is_user_logged_in() { return false; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return (string) $v; }

/* ---------- نوع‌های محتوا ---------- */

class MC_Fake_Labels {
	public $singular_name;
	public $name;
	public function __construct( $s, $p ) { $this->singular_name = $s; $this->name = $p; }
}
class MC_Fake_Type {
	public $name;
	public $labels;
	public function __construct( $n, $s, $p ) { $this->name = $n; $this->labels = new MC_Fake_Labels( $s, $p ); }
}
class MC_Fake_Tax {
	public $name;
	public $labels;
	public $hierarchical;
	public $object_type;
	public function __construct( $n, $s, $p, $h, $ot ) {
		$this->name = $n; $this->labels = new MC_Fake_Labels( $s, $p );
		$this->hierarchical = $h; $this->object_type = $ot;
	}
}
class MC_Fake_Term {
	public $slug; public $name; public $term_id; public $count;
	public function __construct( $s, $n, $i, $c ) { $this->slug = $s; $this->name = $n; $this->term_id = $i; $this->count = $c; }
}
class MC_Fake_Roles {
	public function get_names() {
		return array(
			'administrator' => 'Administrator',
			'editor'        => 'Editor',
			'author'        => 'Author',
			'subscriber'    => 'Subscriber',
		);
	}
}
function wp_roles() { return new MC_Fake_Roles(); }

function get_post_types( $args = array(), $output = 'names' ) {
	$types = array(
		'post'       => new MC_Fake_Type( 'post', 'نوشته', 'نوشته‌ها' ),
		'page'       => new MC_Fake_Type( 'page', 'برگه', 'برگه‌ها' ),
		'movie'      => new MC_Fake_Type( 'movie', 'فیلم', 'فیلم‌ها' ),
		'series'     => new MC_Fake_Type( 'series', 'سریال', 'سریال‌ها' ),
		'anime'      => new MC_Fake_Type( 'anime', 'انیمه', 'انیمه‌ها' ),
		'episode'    => new MC_Fake_Type( 'episode', 'قسمت', 'قسمت‌ها' ),
		'person'     => new MC_Fake_Type( 'person', 'عوامل', 'عوامل' ),
		'collection' => new MC_Fake_Type( 'collection', 'مجموعه', 'مجموعه‌ها' ),
		'attachment' => new MC_Fake_Type( 'attachment', 'پیوست', 'پیوست‌ها' ),
	);
	return 'objects' === $output ? $types : array_keys( $types );
}

function get_taxonomies( $args = array(), $output = 'names' ) {
	$mc = array( 'movie', 'series', 'anime' );
	$taxes = array(
		'category'     => new MC_Fake_Tax( 'category', 'دسته', 'دسته‌ها', true, array( 'post' ) ),
		'post_tag'     => new MC_Fake_Tax( 'post_tag', 'برچسب', 'برچسب‌ها', false, array( 'post' ) ),
		'genre'        => new MC_Fake_Tax( 'genre', 'ژانر', 'ژانرها', false, $mc ),
		'country'      => new MC_Fake_Tax( 'country', 'کشور', 'کشورها', false, $mc ),
		'release_year' => new MC_Fake_Tax( 'release_year', 'سال انتشار', 'سال‌های انتشار', false, $mc ),
		'network'      => new MC_Fake_Tax( 'network', 'شبکه', 'شبکه‌ها', false, array( 'series', 'anime' ) ),
		'studio'       => new MC_Fake_Tax( 'studio', 'استودیو', 'استودیوها', false, $mc ),
		'quality'      => new MC_Fake_Tax( 'quality', 'کیفیت', 'کیفیت‌ها', false, $mc ),
		'language'     => new MC_Fake_Tax( 'language', 'زبان', 'زبان‌ها', false, $mc ),
		'post_format'  => new MC_Fake_Tax( 'post_format', 'قالب', 'قالب‌ها', false, array( 'post' ) ),
	);
	return 'objects' === $output ? $taxes : array_keys( $taxes );
}

function get_terms( $args = array() ) {
	$tax  = isset( $args['taxonomy'] ) ? $args['taxonomy'] : '';
	$data = array(
		'genre'        => array( 'action' => 'اکشن', 'drama' => 'درام', 'comedy' => 'کمدی', 'sci-fi' => 'علمی-تخیلی' ),
		'country'      => array( 'usa' => 'آمریکا', 'iran' => 'ایران', 'japan' => 'ژاپن' ),
		'release_year' => array( '2024' => '۲۰۲۴', '2025' => '۲۰۲۵' ),
		'network'      => array( 'netflix' => 'نتفلیکس', 'hbo' => 'اچ‌بی‌او' ),
		'studio'       => array( 'ghibli' => 'جیبلی', 'a24' => 'A24' ),
		'quality'      => array( 'bluray' => 'BluRay', 'web-dl' => 'WEB-DL' ),
		'language'     => array( 'fa' => 'فارسی', 'en' => 'انگلیسی' ),
		'category'     => array( 'news' => 'اخبار', 'reviews' => 'نقد' ),
		'post_tag'     => array( 'top' => 'برتر', 'new' => 'جدید' ),
	);
	if ( empty( $data[ $tax ] ) ) { return array(); }
	$out = array(); $i = 10;
	foreach ( $data[ $tax ] as $slug => $name ) { $out[] = new MC_Fake_Term( $slug, $name, $i++, 5 ); }
	return $out;
}

/* ---------- بارگذاری کلاس‌های افزونه ---------- */

spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'ManaCore\\Core\\' ) ) { return; }
		$rel  = strtolower( str_replace( array( 'ManaCore\\Core\\', '\\', '_' ), array( '', '/', '-' ), $class ) );
		foreach ( array( 'class-', 'trait-', 'interface-' ) as $prefix ) {
			$file = MANACORE_PATH . 'includes/' . $prefix . $rel . '.php';
			if ( file_exists( $file ) ) { require_once $file; return; }
		}
	}
);

require_once MANACORE_PATH . 'includes/functions.php';

/* ---------- خروجی ---------- */

$payload  = \ManaCore\Core\Block_Data::payload();
$registry = \ManaCore\Core\Blocks::instance()->editor_registry();

file_put_contents( __DIR__ . '/fixtures/payload.json', wp_json_encode( $payload, JSON_PRETTY_PRINT ) );
file_put_contents( __DIR__ . '/fixtures/registry.json', wp_json_encode( $registry, JSON_PRETTY_PRINT ) );

printf(
	"payload keys: %d\nregistry blocks: %d\n",
	count( $payload ),
	count( $registry )
);
foreach ( $registry as $name => $block ) {
	printf( "  %-28s attrs=%d\n", $name, count( $block['attributes'] ) );
}
