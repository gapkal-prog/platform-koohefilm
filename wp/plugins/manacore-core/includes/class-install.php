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
	const DB_VERSION = '1.0.0';

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
	 * ساخت جدول‌های موردنیاز.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$ratings = self::ratings_table();
		$stats   = self::stats_table();

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
