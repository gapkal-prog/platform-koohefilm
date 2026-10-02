<?php
/**
 * Plugin Name:       ManaCore Sources
 * Plugin URI:        https://manacore.dev/plugins/manacore-sources
 * Description:       دریافت خودکار اطلاعات فیلم، سریال و انیمه از منابع آزاد (بدون کلید) و TMDB (با کلید). در صورت ثبت کلید TMDB، به‌صورت پیش‌فرض از آن استفاده می‌شود.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            ManaCore
 * Author URI:        https://manacore.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       manacore
 * Domain Path:       /languages
 *
 * @package ManaCore\Sources
 */

defined( 'ABSPATH' ) || exit;

define( 'MANACORE_SOURCES_VERSION', '1.0.0' );
define( 'MANACORE_SOURCES_FILE', __FILE__ );
define( 'MANACORE_SOURCES_PATH', plugin_dir_path( __FILE__ ) );
define( 'MANACORE_SOURCES_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader for ManaCore\Sources namespace.
 */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'ManaCore\\Sources\\' ) ) {
			return;
		}

		$relative = substr( $class, strlen( 'ManaCore\\Sources\\' ) );
		$relative = strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative ) );

		foreach ( array( 'class-', 'abstract-class-', 'interface-', 'trait-' ) as $prefix ) {
			$file = MANACORE_SOURCES_PATH . 'includes/' . $prefix . $relative . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

/**
 * Show a notice when the core plugin is missing.
 */
function manacore_sources_missing_core_notice() {
	echo '<div class="notice notice-error"><p><strong>ManaCore Sources</strong>: ';
	esc_html_e( 'برای کار کردن این افزونه، ابتدا افزونه «ManaCore Core» را نصب و فعال کنید.', 'manacore' );
	echo '</p></div>';
}

/**
 * Boot the plugin.
 */
function manacore_sources_boot() {
	if ( ! function_exists( 'manacore_get_option' ) ) {
		add_action( 'admin_notices', 'manacore_sources_missing_core_notice' );
		return;
	}

	require_once MANACORE_SOURCES_PATH . 'includes/functions.php';

	\ManaCore\Sources\Registry::instance()->hooks();
	\ManaCore\Sources\Settings::instance()->hooks();
	\ManaCore\Sources\Rest::instance()->hooks();
	\ManaCore\Sources\Metabox::instance()->hooks();
	\ManaCore\Sources\Importer::instance()->hooks();
}
add_action( 'plugins_loaded', 'manacore_sources_boot', 10 );

/**
 * Clear cached remote responses on deactivation.
 */
function manacore_sources_deactivate() {
	global $wpdb;

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mcsrc_%'
		 OR option_name LIKE '_transient_timeout_mcsrc_%'"
	);
}
register_deactivation_hook( __FILE__, 'manacore_sources_deactivate' );
