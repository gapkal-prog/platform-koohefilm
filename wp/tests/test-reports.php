<?php
/**
 * آزمون «گزارش خرابی لینک» و ارتقای پایگاه‌داده.
 *
 * بدون وردپرس اجرا می‌شود: `$wpdb` ساختگی همه‌ی کوئری‌ها را ثبت می‌کند تا
 * بتوان سنجید که پارامترها درست بسته می‌شوند، پاک‌سازی انجام می‌شود و
 * هیچ رشته‌ی خامی داخل SQL نمی‌رود.
 *
 * اجرا: php wp/tests/test-reports.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MANACORE_URL', 'http://example.test/plugins/manacore-core/' );
define( 'MANACORE_PATH', dirname( __DIR__ ) . '/plugins/manacore-core/' );
define( 'MANACORE_VERSION', '1.0.0' );
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

$GLOBALS['mc_options'] = array();
$GLOBALS['mc_posts']   = array( 4 => 'movie', 11 => 'episode' );

function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function esc_attr__( $t, $d = '' ) { return $t; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return (string) $v; }
function esc_url_raw( $v ) { return filter_var( (string) $v, FILTER_SANITIZE_URL ); }
function apply_filters( $tag, $value ) { return $value; }
function add_action() {}
function add_filter() {}
function do_action() {}
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function wp_unslash( $v ) { return $v; }
function current_time( $t = 'mysql' ) { return '2026-10-08 12:00:00'; }
function wp_salt( $s = '' ) { return 'test-salt'; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['mc_options'] ) ? $GLOBALS['mc_options'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['mc_options'][ $k ] = $v; return true; }
function get_current_user_id() { return (int) ( $GLOBALS['mc_user'] ?? 0 ); }
function get_post( $id ) {
	return isset( $GLOBALS['mc_posts'][ (int) $id ] ) ? (object) array( 'ID' => (int) $id, 'post_type' => $GLOBALS['mc_posts'][ (int) $id ] ) : null;
}
function is_admin() { return true; }
function did_action() { return 0; }

/**
 * $wpdb ساختگی: کوئری‌ها و آرگومان‌ها را برای سنجش ثبت می‌کند.
 */
class MC_Wpdb {
	public $prefix   = 'wp_';
	public $insert_id = 77;
	public $queries  = array();
	public $inserts  = array();
	public $updates  = array();
	public $deletes  = array();
	public $rows     = array();
	public $var      = null;
	public $charset  = 'DEFAULT CHARACTER SET utf8mb4';

	public function get_charset_collate() { return $this->charset; }
	public $last_args = array();

	public function prepare( $query, ...$args ) {
		/*
		 * رفتار واقعی `wpdb::prepare()`: اگر تنها آرگومان یک آرایه باشد،
		 * همان آرایه به‌عنوان فهرست پارامترها به کار می‌رود. بدون این
		 * شبیه‌سازی، سنجش «همه‌ی شرط‌ها پارامتر شده‌اند» بی‌معنا می‌شد.
		 */
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$this->last_args = $args;
		$this->queries[] = array( 'sql' => $query . ' /* prepared */', 'args' => $args );

		return $query . ' /* ' . wp_json_encode( $args ) . ' */';
	}

	public function get_var( $query ) { $this->queries[] = array( 'sql' => $query, 'args' => $this->last_args ); return $this->var; }
	public function get_results( $query, $mode = OBJECT ) { $this->queries[] = array( 'sql' => $query, 'args' => $this->last_args ); return $this->rows; }
	public function insert( $table, $data, $format = null ) {
		$this->inserts[] = array( 'table' => $table, 'data' => $data, 'format' => $format );
		return 1;
	}
	public function update( $table, $data, $where, $df = null, $wf = null ) {
		$this->updates[] = array( 'table' => $table, 'data' => $data, 'where' => $where );
		return 1;
	}
	public function delete( $table, $where, $wf = null ) {
		$this->deletes[] = array( 'table' => $table, 'where' => $where );
		return 1;
	}
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
}

function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE ); }
function dbDelta( $sql ) { $GLOBALS['mc_dbdelta'][] = $sql; }

/*
 * `Install::create_tables()` فایل ارتقای وردپرس را `require` می‌کند
 * (همان کاری که خودِ وردپرس می‌کند). اینجا یک فایل جایگزین می‌سازیم تا
 * مسیر واقعی کد اجرا شود، نه نسخه‌ی دست‌کاری‌شده.
 */
$GLOBALS['mc_upgrade_file'] = ABSPATH . 'wp-admin/includes/upgrade.php';

if ( ! is_dir( dirname( $GLOBALS['mc_upgrade_file'] ) ) ) {
	mkdir( dirname( $GLOBALS['mc_upgrade_file'] ), 0777, true );
}

file_put_contents( $GLOBALS['mc_upgrade_file'], '<?php /* stub */' );

$GLOBALS['wpdb'] = new MC_Wpdb();

/* ---------------------------------------------------------------
 * بارگذاری کلاس‌ها
 * ------------------------------------------------------------ */

require_once MANACORE_PATH . 'includes/trait-singleton.php';
require_once MANACORE_PATH . 'includes/class-install.php';
require_once MANACORE_PATH . 'includes/class-reports.php';

/* ---------------------------------------------------------------
 * ۱) ارتقای پایگاه‌داده
 * ------------------------------------------------------------ */

echo "\n=== ۱) ارتقای پایگاه‌داده ===\n";

mc_ok( '1.1.0' === \ManaCore\Core\Install::DB_VERSION, 'نسخه‌ی پایگاه‌داده برای جدول گزارش‌ها بالا رفته است' );
mc_ok( 'wp_manacore_reports' === \ManaCore\Core\Install::reports_table(), 'نام جدول گزارش‌ها درست ساخته می‌شود' );

/* نسخه‌ی همانند → هیچ ارتقایی اجرا نمی‌شود. */
update_option( 'manacore_db_version', \ManaCore\Core\Install::DB_VERSION );
$GLOBALS['mc_dbdelta'] = array();
mc_ok( false === \ManaCore\Core\Install::maybe_upgrade(), 'با نسخه‌ی همانند، ارتقا اجرا نمی‌شود' );
mc_ok( empty( $GLOBALS['mc_dbdelta'] ), 'در آن حالت هیچ CREATE TABLE اجرا نمی‌شود' );

/* نسخه‌ی قدیمی → جدول‌ها ساخته می‌شوند. */
update_option( 'manacore_db_version', '1.0.0' );
$GLOBALS['mc_dbdelta'] = array();
mc_ok( true === \ManaCore\Core\Install::maybe_upgrade(), 'با نسخه‌ی قدیمی، ارتقا اجرا می‌شود' );

$created = implode( "\n", $GLOBALS['mc_dbdelta'] );
mc_ok( false !== strpos( $created, 'wp_manacore_reports' ), 'جدول گزارش‌ها ساخته می‌شود' );
mc_ok( false !== strpos( $created, 'UNIQUE KEY unique_report (post_id, reporter_hash, quality)' ), 'کلید یکتای جلوگیری از گزارش تکراری هست' );
mc_ok( false !== strpos( $created, 'KEY status_date (status, created_at)' ), 'کلید فهرست وضعیت/تاریخ برای پیشخوان هست' );
mc_ok( \ManaCore\Core\Install::DB_VERSION === get_option( 'manacore_db_version' ), 'نسخه‌ی پایگاه‌داده پس از ارتقا به‌روز می‌شود' );

/* ---------------------------------------------------------------
 * ۲) درهم‌سازی گزارش‌دهنده
 * ------------------------------------------------------------ */

echo "\n=== ۲) شناسه‌ی گزارش‌دهنده ===\n";

$GLOBALS['mc_user'] = 0;
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$guest_hash = \ManaCore\Core\Reports::reporter_hash();

mc_ok( 0 === strpos( $guest_hash, 'g' ), 'گزارش مهمان با پیشوند g مشخص می‌شود' );
mc_ok( ! str_contains( $guest_hash, '203.0.113.9' ), 'نشانی شبکه‌ی کاربر خام ذخیره نمی‌شود (با نمک سایت درهم‌سازی می‌شود)' );

$_SERVER['REMOTE_ADDR'] = '198.51.100.4';
mc_ok( \ManaCore\Core\Reports::reporter_hash() !== $guest_hash, 'دو کاربر مهمان دو شناسه‌ی متفاوت می‌گیرند' );

$GLOBALS['mc_user'] = 12;
mc_ok( 'u12' === \ManaCore\Core\Reports::reporter_hash(), 'کاربر وارد‌شده با شناسه‌ی کاربری مشخص می‌شود' );
$GLOBALS['mc_user'] = 0;

/* ---------------------------------------------------------------
 * ۳) درج گزارش
 * ------------------------------------------------------------ */

echo "\n=== ۳) درج گزارش ===\n";

$GLOBALS['wpdb']->inserts = array();
$id = \ManaCore\Core\Reports::insert(
	array(
		'post_id'    => 4,
		'link_url'   => 'https://cdn.example/movie.mp4',
		'link_label' => 'دانلود ۱۰۸۰p',
		'quality'    => '1080p',
		'reason'     => 'فایل باز نمی‌شود',
	)
);

mc_ok( 77 === $id, 'شناسه‌ی گزارش برگردانده می‌شود' );

$insert = $GLOBALS['wpdb']->inserts[0];
mc_ok( 'wp_manacore_reports' === $insert['table'], 'گزارش در جدول اختصاصی درج می‌شود' );
mc_ok( 4 === $insert['data']['post_id'], 'شناسه‌ی اثر ذخیره می‌شود' );
mc_ok( 'new' === $insert['data']['status'], 'وضعیت آغازین «new» است' );
mc_ok( '2026-10-08 12:00:00' === $insert['data']['created_at'], 'تاریخ با زمان وردپرس ذخیره می‌شود' );
mc_ok( 9 === count( $insert['format'] ), 'قالب هر ستون در درج تعیین شده است (بدون رشته‌ی خام)' );

/* پاک‌سازی: رشته‌ی بلند، تگ و نشانی ناسالم. */
$GLOBALS['wpdb']->inserts = array();
\ManaCore\Core\Reports::insert(
	array(
		'post_id'    => 4,
		'link_url'   => 'https://cdn.example/<script>alert(1)</script>.mp4',
		'link_label' => '<b>پ' . str_repeat( 'x', 400 ) . '</b>',
		'quality'    => '1080p' . str_repeat( 'q', 100 ),
		'reason'     => '<script>x</script>',
	)
);

$insert = $GLOBALS['wpdb']->inserts[0];
mc_ok( false === strpos( (string) $insert['data']['link_label'], '<b>' ), 'تگ HTML از برچسب لینک پاک می‌شود' );
mc_ok( 190 >= mb_strlen( (string) $insert['data']['link_label'] ), 'برچسب لینک بلند بریده می‌شود', 'طول: ' . mb_strlen( (string) $insert['data']['link_label'] ) );
mc_ok( 60 >= mb_strlen( (string) $insert['data']['quality'] ), 'کیفیت خیلی بلند بریده می‌شود' );
mc_ok( false === strpos( (string) $insert['data']['reason'], '<' ), 'توضیح کاربر از تگ پاک می‌شود' );
mc_ok( false === strpos( (string) $insert['data']['link_url'], '<' ), 'نشانی ناسالم پاک‌سازی می‌شود' );

/* ردیف ناقص درج نمی‌شود. */
$GLOBALS['wpdb']->inserts = array();
mc_ok( 0 === \ManaCore\Core\Reports::insert( array( 'post_id' => 0, 'link_url' => 'https://cdn.example/a.mp4' ) ), 'گزارش بی‌اثر درج نمی‌شود' );
mc_ok( 0 === \ManaCore\Core\Reports::insert( array( 'post_id' => 4, 'link_url' => '' ) ), 'گزارش بی‌لینک درج نمی‌شود' );
mc_ok( empty( $GLOBALS['wpdb']->inserts ), 'در این دو حالت هیچ درجی انجام نشد' );

/* ---------------------------------------------------------------
 * ۴) گزارش تکراری
 * ------------------------------------------------------------ */

echo "\n=== ۴) گزارش تکراری ===\n";

$GLOBALS['wpdb']->var = null;
mc_ok( false === \ManaCore\Core\Reports::is_duplicate( 4, '1080p' ), 'گزارش تازه تکراری نیست' );

$GLOBALS['wpdb']->var = 55;
mc_ok( true === \ManaCore\Core\Reports::is_duplicate( 4, '1080p' ), 'گزارش موجود تکراری شناخته می‌شود' );

$last = end( $GLOBALS['wpdb']->queries );
mc_ok( false !== strpos( $last['sql'], 'reporter_hash = %s' ), 'کوئری تکراری‌سنجی پارامترشده است' );
mc_ok( 3 === count( $last['args'] ), 'هر سه شرط (اثر، گزارش‌دهنده، کیفیت) با پارامتر بسته می‌شوند', 'پارامترها: ' . wp_json_encode( $last['args'] ) );

/* ---------------------------------------------------------------
 * ۵) فهرست و وضعیت‌ها
 * ------------------------------------------------------------ */

echo "\n=== ۵) فهرست و وضعیت‌ها ===\n";

mc_ok( array( 'new', 'fixed', 'ignored' ) === \ManaCore\Core\Reports::STATUSES, 'وضعیت‌های مجاز محدود و مشخص‌اند' );

$GLOBALS['wpdb']->queries = array();
$GLOBALS['wpdb']->last_args = array();
\ManaCore\Core\Reports::query( array( 'status' => 'new', 'per_page' => 500, 'page' => 2 ) );
$q = end( $GLOBALS['wpdb']->queries );

mc_ok( false !== strpos( $q['sql'], 'WHERE status = %s' ), 'فیلتر وضعیت در کوئری هست' );
mc_ok( false !== strpos( $q['sql'], 'ORDER BY created_at DESC' ), 'تازه‌ترین گزارش‌ها اول می‌آیند' );
mc_ok( 200 === $q['args'][1], 'تعداد در صفحه به ۲۰۰ کرانه می‌شود', 'پارامترها: ' . wp_json_encode( $q['args'] ) );
mc_ok( 200 === $q['args'][2], 'صفحه‌ی دوم با OFFSET درست حساب می‌شود' );

$GLOBALS['wpdb']->queries = array();
$GLOBALS['wpdb']->last_args = array();
\ManaCore\Core\Reports::query( array( 'status' => 'نامعتبر' ) );
$q = end( $GLOBALS['wpdb']->queries );
mc_ok( false === strpos( $q['sql'], 'WHERE' ), 'وضعیت نامعتبر فیلتری اضافه نمی‌کند' );
mc_ok( 2 === count( $q['args'] ), 'در نبود فیلتر فقط LIMIT/OFFSET پارامتر می‌شوند' );

$GLOBALS['wpdb']->rows = array(
	array( 'status' => 'new', 'total' => '3' ),
	array( 'status' => 'fixed', 'total' => '1' ),
	array( 'status' => 'weird', 'total' => '9' ),
);
$counts = \ManaCore\Core\Reports::counts();
mc_ok( 3 === $counts['new'] && 1 === $counts['fixed'], 'شمارش به تفکیک وضعیت درست است' );
mc_ok( 0 === $counts['ignored'], 'وضعیت‌های بی‌گزارش صفر می‌مانند' );
mc_ok( ! isset( $counts['weird'] ), 'وضعیت ناشناخته‌ی پایگاه‌داده وارد گزارش نمی‌شود' );

/* وضعیت نامعتبر پذیرفته نمی‌شود. */
$GLOBALS['wpdb']->updates = array();
mc_ok( false === \ManaCore\Core\Reports::set_status( 5, 'hacked' ), 'وضعیت نامعتبر رد می‌شود' );
mc_ok( empty( $GLOBALS['wpdb']->updates ), 'در آن حالت هیچ به‌روزرسانی‌ای اجرا نمی‌شود' );

mc_ok( true === \ManaCore\Core\Reports::set_status( 5, 'fixed' ), 'وضعیت معتبر ثبت می‌شود' );
$update = $GLOBALS['wpdb']->updates[0];
mc_ok( array( 'id' => 5 ) === $update['where'], 'به‌روزرسانی فقط همان ردیف را هدف می‌گیرد' );

mc_ok( true === \ManaCore\Core\Reports::delete( 5 ), 'حذف گزارش کار می‌کند' );
mc_ok( array( 'id' => 5 ) === $GLOBALS['wpdb']->deletes[0]['where'], 'حذف با شرط شناسه انجام می‌شود' );

/* ---------------------------------------------------------------
 * ۶) دکمه‌ی سمت کاربر
 * ------------------------------------------------------------ */

echo "\n=== ۶) دکمه‌ی گزارش (سمت کاربر) ===\n";

$button = \ManaCore\Core\Reports::button( 4, 'https://cdn.example/movie.mp4', '1080p' );

mc_ok( false !== strpos( $button, 'data-manacore-report' ), 'قلاب جاوااسکریپت روی دکمه هست' );
mc_ok( false !== strpos( $button, 'data-post-id="4"' ), 'شناسه‌ی اثر روی دکمه هست' );
mc_ok( false !== strpos( $button, 'data-quality="1080p"' ), 'کیفیت روی دکمه هست' );
mc_ok( false !== strpos( $button, 'type="button"' ), 'دکمه از نوع button است (فرم ناخواسته نمی‌فرستد)' );
mc_ok( false !== strpos( $button, 'aria-label=' ), 'برچسب دسترس‌پذیری دارد' );

/* ---------------------------------------------------------------
 * پایان
 * ------------------------------------------------------------ */

echo "\n" . str_repeat( '=', 58 ) . "\n";
echo 'موفق: ' . $GLOBALS['mc_pass'] . '   ناموفق: ' . $GLOBALS['mc_fail'] . "\n";
echo str_repeat( '=', 58 ) . "\n";

exit( $GLOBALS['mc_fail'] > 0 ? 1 : 0 );
