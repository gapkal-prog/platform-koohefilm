<?php
/**
 * فرمان‌های WP-CLI.
 *
 * چرا لازم است: سایت‌های فیلم در عمل با WP-CLI اداره می‌شوند (نصب خودکار،
 * مهاجرت، پاک‌سازی زمان‌بندی‌شده). پیش‌تر همه‌ی کارها فقط از پنل و با ماوس
 * ممکن بود و هیچ راهی برای «ساخت محتوای نمایشی هنگام تحویل» یا «بازسازی کش
 * پس از مهاجرت» از خط فرمان وجود نداشت.
 *
 * همه‌ی کارهای سنگین به همان کلاس‌های افزونه سپرده می‌شوند (Demo،
 * Portability، Analytics، Mega_Menu) تا منطق یک‌جا بماند و از خط فرمان و پنل
 * رفتار یکسان باشد.
 *
 * نمونه:
 *   wp manacore status
 *   wp manacore export --file=/tmp/site.json
 *   wp manacore import /tmp/site.json --dry-run
 *   wp manacore demo create
 *   wp manacore demo remove
 *   wp manacore rebuild
 *   wp manacore cleanup --yes
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cli
 */
class Cli {

	/**
	 * ثبت فرمان‌ها (فقط وقتی WP-CLI در حال اجراست).
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'manacore', __CLASS__ );
	}

	/**
	 * خواندن یک پرچم (`--flag=value` یا `--flag`).
	 *
	 * عمداً از `WP_CLI\Utils` استفاده نمی‌شود تا این کلاس هیچ وابستگی
	 * بیرونی نداشته باشد و در آزمون‌ها هم ساده بسنجیده شود.
	 *
	 * @param array  $assoc   آرگومان‌های کلیددار.
	 * @param string $key     نام پرچم.
	 * @param mixed  $default مقدار پیش‌فرض.
	 * @return mixed
	 */
	protected static function flag( $assoc, $key, $default = false ) {
		if ( ! is_array( $assoc ) || ! array_key_exists( $key, $assoc ) ) {
			return $default;
		}

		$value = $assoc[ $key ];

		/* `--flag` بدون مقدار در WP-CLI به `true` تبدیل می‌شود. */
		if ( true === $value || 'true' === $value ) {
			return true;
		}

		if ( false === $value || 'false' === $value ) {
			return false;
		}

		return $value;
	}

	/**
	 * وضعیت کوتاه سایت: نسخه‌ها، شمارش محتوا، برگه‌های کلیدی.
	 *
	 * ## EXAMPLES
	 *
	 *     wp manacore status
	 *
	 * @param array $args       آرگومان‌های موضعی.
	 * @param array $assoc_args آرگومان‌های کلیددار.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		$counts = array();

		foreach ( array_keys( manacore_post_types() ) as $type ) {
			$posts = wp_count_posts( $type );

			$counts[ $type ] = (int) ( $posts->publish ?? 0 );
		}

		\WP_CLI::line( 'نسخه‌ی افزونه: ' . ( defined( 'MANACORE_VERSION' ) ? MANACORE_VERSION : '—' ) );
		\WP_CLI::line( 'نسخه‌ی پایگاه‌داده: ' . Install::DB_VERSION );
		\WP_CLI::line( 'نشانی سایت: ' . home_url( '/' ) );
		\WP_CLI::line( '' );
		\WP_CLI::line( 'محتوای منتشرشده:' );

		foreach ( $counts as $type => $total ) {
			\WP_CLI::line( sprintf( '  %-12s %d', $type, $total ) );
		}

		\WP_CLI::line( '' );
		\WP_CLI::line( 'برگه‌ها:' );
		\WP_CLI::line( sprintf( '  %-12s %s', 'پخش', self::page_state( 'manacore_watch_page' ) ) );
		\WP_CLI::line( sprintf( '  %-12s %s', 'درخواست‌ها', self::page_state( 'manacore_request_page' ) ) );

		if ( class_exists( __NAMESPACE__ . '\\Demo' ) ) {
			$demo = Demo::status();
			\WP_CLI::line( '' );
			\WP_CLI::line( 'محتوای نمایشی: ' . ( $demo['total'] ? $demo['total'] . ' نوشته' : 'ساخته نشده' ) );
		}
	}

	/**
	 * پشتیبان‌گیری تنظیمات در یک فایل JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : مسیر فایل خروجی. پیش‌فرض: `manacore-settings-<date>.json` در پوشه‌ی جاری.
	 *
	 * ## EXAMPLES
	 *
	 *     wp manacore export --file=/tmp/site.json
	 *
	 * @param array $args       آرگومان‌های موضعی.
	 * @param array $assoc_args آرگومان‌های کلیددار.
	 * @return void
	 */
	public function export( $args, $assoc_args ) {
		$file = (string) self::flag( $assoc_args, 'file', '' );

		if ( '' === $file ) {
			$file = 'manacore-settings-' . gmdate( 'Y-m-d' ) . '.json';
		}

		$json = Portability::to_json();
		$dir  = dirname( $file );

		if ( $dir && ! is_dir( $dir ) ) {
			\WP_CLI::error( sprintf( 'پوشه‌ی مقصد وجود ندارد: %s', $dir ) );
		}

		if ( false === file_put_contents( $file, $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			\WP_CLI::error( sprintf( 'نوشتن فایل ناموفق بود: %s', $file ) );
		}

		\WP_CLI::success( sprintf( 'تنظیمات در %s ذخیره شد (%d بایت).', $file, strlen( $json ) ) );
	}

	/**
	 * بازگردانی تنظیمات از فایل JSON.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : مسیر فایل پشتیبان.
	 *
	 * [--dry-run]
	 * : فقط بررسی کن؛ هیچ چیزی ذخیره نشود.
	 *
	 * ## EXAMPLES
	 *
	 *     wp manacore import /tmp/site.json --dry-run
	 *
	 * @param array $args       آرگومان‌های موضعی.
	 * @param array $assoc_args آرگومان‌های کلیددار.
	 * @return void
	 */
	public function import( $args, $assoc_args ) {
		$file = isset( $args[0] ) ? (string) $args[0] : '';

		if ( '' === $file || ! is_readable( $file ) ) {
			\WP_CLI::error( 'فایل پشتیبان خوانده نشد.' );
		}

		$raw    = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$result = Portability::import( $raw, array( 'dry_run' => (bool) self::flag( $assoc_args, 'dry-run', false ) ) );

		if ( ! empty( $result['errors'] ) ) {
			\WP_CLI::error( 'بازگردانی انجام نشد: ' . implode( ', ', $result['errors'] ) );
		}

		$message = sprintf(
			'%d تنظیم%s اعمال شد.',
			$result['applied'],
			$result['dry_run'] ? ' (حالت آزمایشی)' : ''
		);

		if ( ! empty( $result['skipped'] ) ) {
			\WP_CLI::warning( sprintf( '%d کلید ناشناخته نادیده گرفته شد: %s', count( $result['skipped'] ), implode( ', ', $result['skipped'] ) ) );
		}

		if ( ! empty( $result['pages'] ) ) {
			$message .= sprintf( ' %d برگه هم پیدا و وصل شد.', count( $result['pages'] ) );
		}

		\WP_CLI::success( $message );
	}

	/**
	 * ساخت یا حذف محتوای نمایشی.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : `create` یا `remove`.
	 *
	 * [--force]
	 * : پیش از ساخت، محتوای نمایشی قبلی حذف شود.
	 *
	 * ## EXAMPLES
	 *
	 *     wp manacore demo create
	 *     wp manacore demo remove
	 *
	 * @param array $args       آرگومان‌های موضعی.
	 * @param array $assoc_args آرگومان‌های کلیددار.
	 * @return void
	 */
	public function demo( $args, $assoc_args ) {
		$action = isset( $args[0] ) ? sanitize_key( (string) $args[0] ) : '';

		if ( 'create' === $action ) {
			$result = Demo::seed( array( 'force' => (bool) self::flag( $assoc_args, 'force', false ) ) );

			if ( ! empty( $result['skipped'] ) ) {
				\WP_CLI::warning( 'محتوای نمایشی از قبل ساخته شده است؛ برای ساخت دوباره `--force` بدهید.' );
				return;
			}

			\WP_CLI::success( sprintf( 'محتوای نمایشی ساخته شد: %d نوشته و %d ترم.', $result['created'], $result['terms'] ) );
			return;
		}

		if ( 'remove' === $action ) {
			$deleted = Demo::remove();
			\WP_CLI::success( sprintf( 'محتوای نمایشی حذف شد: %d نوشته.', $deleted ) );
			return;
		}

		\WP_CLI::error( 'کنش نامعتبر است؛ `create` یا `remove` را بدهید.' );
	}

	/**
	 * بازسازی کش‌ها، قواعد پیوند و فهرست راهبری.
	 *
	 * ## EXAMPLES
	 *
	 *     wp manacore rebuild
	 *
	 * @return void
	 */
	public function rebuild() {
		if ( class_exists( __NAMESPACE__ . '\\Mega_Menu' ) ) {
			Mega_Menu::flush_cache();
		}

		delete_transient( 'manacore_home_stats' );

		if ( class_exists( __NAMESPACE__ . '\\Analytics' ) ) {
			Analytics::instance()->flush();
		}

		flush_rewrite_rules( false );

		\WP_CLI::success( 'کش‌ها، قواعد پیوند و فهرست راهبری بازسازی شد.' );
	}

	/**
	 * پاک‌سازی نگه‌داری: ترنزینت‌های منقضی، آمار نوشته‌های حذف‌شده و
	 * گزارش‌های لینکِ بی‌صاحب.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : تأیید اجرای حذف (بدون آن فقط شمارش گزارش می‌شود).
	 *
	 * ## EXAMPLES
	 *
	 *     wp manacore cleanup --yes
	 *
	 * @param array $args       آرگومان‌های موضعی.
	 * @param array $assoc_args آرگومان‌های کلیددار.
	 * @return void
	 */
	public function cleanup( $args, $assoc_args ) {
		global $wpdb;

		$apply   = (bool) self::flag( $assoc_args, 'yes', false );
		$expired = self::count_expired_transients();
		$orphans = self::count_orphan_stats();

		\WP_CLI::line( sprintf( 'ترنزینت‌های منقضی: %d', $expired ) );
		\WP_CLI::line( sprintf( 'ردیف‌های آمار بی‌صاحب: %d', $orphans ) );

		if ( ! $apply ) {
			\WP_CLI::warning( 'چیزی حذف نشد؛ برای اجرا `--yes` بدهید.' );
			return;
		}

		if ( $expired ) {
			delete_expired_transients( true );
		}

		if ( $orphans ) {
			$table = Install::stats_table();

			// حذف ردیف‌هایی که نوشته‌ی مادرشان دیگر وجود ندارد.
			$wpdb->query( "DELETE s FROM {$table} s LEFT JOIN {$wpdb->posts} p ON p.ID = s.post_id WHERE p.ID IS NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		\WP_CLI::success( 'پاک‌سازی انجام شد.' );
	}

	/**
	 * متن وضعیت یک برگه بر پایه‌ی گزینه.
	 *
	 * @param string $option نام گزینه.
	 * @return string
	 */
	protected static function page_state( $option ) {
		$id = (int) get_option( $option, 0 );

		if ( ! $id ) {
			return 'ساخته نشده';
		}

		$status = (string) get_post_status( $id );

		if ( ! $status || 'trash' === $status ) {
			return 'شناسه‌ی نامعتبر';
		}

		return sprintf( '#%d (%s)', $id, 'publish' === $status ? 'منتشرشده' : $status );
	}

	/**
	 * شمارش ترنزینت‌های منقضیِ افزونه.
	 *
	 * @return int
	 */
	protected static function count_expired_transients() {
		global $wpdb;

		$now = time();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_manacore_' ) . '%',
				$now
			)
		);
	}

	/**
	 * شمارش ردیف‌های آمار که نوشته‌ی مادرشان حذف شده است.
	 *
	 * @return int
	 */
	protected static function count_orphan_stats() {
		global $wpdb;

		$table = Install::stats_table();

		if ( ! $table ) {
			return 0;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} s LEFT JOIN {$wpdb->posts} p ON p.ID = s.post_id WHERE p.ID IS NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
