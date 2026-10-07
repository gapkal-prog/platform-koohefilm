<?php
/**
 * نصب: ساخت جدول‌ها و مقادیر پیش‌فرض.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Install
 */
class Install {

	/**
	 * نسخه‌ی شمای پایگاه داده.
	 */
	const DB_VERSION = '1.1.0';

	/**
	 * نام جدول امتیازها.
	 *
	 * @return string
	 */
	public static function ratings_table() {
		global $wpdb;
		return $wpdb->prefix . 'manacore_ratings';
	}

	/**
	 * نام جدول آمار بازدید/دانلود.
	 *
	 * @return string
	 */
	public static function stats_table() {
		global $wpdb;
		return $wpdb->prefix . 'manacore_stats';
	}

	/**
	 * نام جدول گزارش خرابی لینک.
	 *
	 * @return string
	 */
	public static function reports_table() {
		global $wpdb;
		return $wpdb->prefix . 'manacore_reports';
	}

	/**
	 * ارتقای پایگاه‌داده در صورت قدیمی بودن نسخه.
	 *
	 * پیش‌تر `create_tables()` **فقط** هنگام فعال‌سازی اجرا می‌شد؛ یعنی
	 * سایتی که افزونه را به‌روز می‌کرد هرگز جدول تازه‌ای نمی‌گرفت و
	 * نبودنش تا خطای پایگاه‌داده پیش نمی‌رفت. اکنون نسخه‌ی ذخیره‌شده با
	 * نسخه‌ی کد سنجیده می‌شود و در صورت اختلاف، ساخت/ارتقا اجرا می‌شود
	 * (همان الگوی خودِ وردپرس، و ارزان: یک خواندن گزینه).
	 *
	 * @return bool آیا ارتقایی انجام شد؟
	 */
	public static function maybe_upgrade() {
		$stored = (string) get_option( 'manacore_db_version', '' );

		if ( self::DB_VERSION === $stored ) {
			return false;
		}

		self::create_tables();

		/**
		 * پس از ارتقای پایگاه‌داده.
		 *
		 * @param string $from نسخه‌ی پیشین (خالی اگر تازه باشد).
		 */
		do_action( 'manacore_db_upgraded', $stored );

		return true;
	}

	/**
	 * ساخت جدول‌های موردنیاز.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$ratings = self::ratings_table();
		$stats   = self::stats_table();
		$reports = self::reports_table();

		$sql = array();

		$sql[] = "CREATE TABLE {$ratings} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_hash VARCHAR(64) NOT NULL DEFAULT '',
			rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY user_id (user_id),
			UNIQUE KEY unique_vote (post_id, user_hash)
		) {$charset};";

		$sql[] = "CREATE TABLE {$stats} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			stat_type VARCHAR(20) NOT NULL DEFAULT 'view',
			stat_date DATE NOT NULL,
			stat_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_stat (post_id, stat_type, stat_date),
			KEY stat_lookup (stat_type, stat_date)
		) {$charset};";

		/*
		 * گزارش خرابی لینک: هر ردیف یک گزارش کاربر است. `KEY status_date`
		 * برای فهرست پیشخوان (پیش‌فرض: تازه‌ترین‌ها) و
		 * `UNIQUE KEY unique_report` برای جلوگیری از انبوه گزارش تکراری
		 * از یک کاربر برای یک لینک.
		 */
		$sql[] = "CREATE TABLE {$reports} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			link_url TEXT NOT NULL,
			link_label VARCHAR(190) NOT NULL DEFAULT '',
			quality VARCHAR(60) NOT NULL DEFAULT '',
			reason VARCHAR(190) NOT NULL DEFAULT '',
			reporter_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			reporter_hash VARCHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY status_date (status, created_at),
			UNIQUE KEY unique_report (post_id, reporter_hash, quality)
		) {$charset};";

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		update_option( 'manacore_db_version', self::DB_VERSION );
	}

	/**
	 * مقادیر پیش‌فرض تنظیمات.
	 */
	public static function default_options() {
		$defaults = array(
			'slug_movie'        => 'movie',
			'slug_series'       => 'series',
			'slug_anime'        => 'anime',
			'slug_episode'      => 'episode',
			'slug_person'       => 'person',
			'slug_collection'   => 'collection',
			'slug_channel'      => 'channel',
			'enable_ratings'    => 1,
			'enable_watchlist'  => 1,
			'enable_views'      => 1,
			'links_login_only'  => 0,
			'default_color_mode'=> 'dark',
			'items_per_page'    => 24,
		);

		$existing = get_option( 'manacore_settings', array() );
		update_option( 'manacore_settings', wp_parse_args( $existing, $defaults ) );
	}
}
