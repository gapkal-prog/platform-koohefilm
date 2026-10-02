<?php
/**
 * Plugin Name:       ManaCore Subscriptions
 * Plugin URI:        https://manacore.dev/
 * Description:       مدیریت اشتراک و دسترسی ویژه برای قالب و افزونه‌های ManaCore، با پشتیبانی از WooCommerce Subscriptions و امکان اعطای دستی سطح دسترسی.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            ManaCore
 * Author URI:        https://manacore.dev/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       manacore
 * Domain Path:       /languages
 *
 * @package ManaCore\Subs
 */

defined( 'ABSPATH' ) || exit;

define( 'MANACORE_SUBS_VERSION', '1.0.0' );
define( 'MANACORE_SUBS_FILE', __FILE__ );
define( 'MANACORE_SUBS_PATH', plugin_dir_path( __FILE__ ) );
define( 'MANACORE_SUBS_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-ish autoloader for the ManaCore\Subs namespace.
 *
 * ManaCore\Subs\Access → includes/class-access.php
 * ManaCore\Subs\Singleton → includes/trait-singleton.php
 *
 * @param string $class Fully qualified class name.
 */
function manacore_subs_autoload( $class ) {
	if ( 0 !== strpos( $class, 'ManaCore\\Subs\\' ) ) {
		return;
	}

	$relative = substr( $class, strlen( 'ManaCore\\Subs\\' ) );
	$relative = str_replace( '\\', '/', $relative );
	$slug     = strtolower( str_replace( '_', '-', $relative ) );

	foreach ( array( 'class-', 'trait-', 'interface-', 'abstract-class-' ) as $prefix ) {
		$file = MANACORE_SUBS_PATH . 'includes/' . $prefix . $slug . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}
	}
}

spl_autoload_register( 'manacore_subs_autoload' );

/**
 * Boot the plugin once the core plugin is available.
 */
function manacore_subs_boot() {
	if ( ! function_exists( 'manacore_get_option' ) ) {
		add_action( 'admin_notices', 'manacore_subs_missing_core_notice' );
		return;
	}

	require_once MANACORE_SUBS_PATH . 'includes/functions.php';

	ManaCore\Subs\Plans::instance()->hooks();
	ManaCore\Subs\Access::instance()->hooks();
	ManaCore\Subs\Woo::instance()->hooks();
	ManaCore\Subs\Meta::instance()->hooks();
	ManaCore\Subs\Account::instance()->hooks();
	ManaCore\Subs\Settings::instance()->hooks();
	ManaCore\Subs\Rest::instance()->hooks();
	ManaCore\Subs\Admin::instance()->hooks();
}
add_action( 'plugins_loaded', 'manacore_subs_boot', 20 );

/**
 * Warn when the core plugin is inactive.
 */
function manacore_subs_missing_core_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	esc_html_e( 'افزونه‌ی «ManaCore Subscriptions» برای کار کردن به افزونه‌ی «ManaCore Core» نیاز دارد.', 'manacore' );
	echo '</p></div>';
}

/**
 * Activation: register capability and flush rewrite rules.
 */
function manacore_subs_activate() {
	$role = get_role( 'administrator' );

	if ( $role ) {
		$role->add_cap( 'manacore_bypass_paywall' );
		$role->add_cap( 'manacore_manage_subscriptions' );
	}

	$editor = get_role( 'editor' );

	if ( $editor ) {
		$editor->add_cap( 'manacore_bypass_paywall' );
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'manacore_subs_activate' );

/**
 * Deactivation: clear cached access decisions.
 */
function manacore_subs_deactivate() {
	global $wpdb;

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mcsub_%' OR option_name LIKE '_transient_timeout_mcsub_%'"
	);
}
register_deactivation_hook( __FILE__, 'manacore_subs_deactivate' );
