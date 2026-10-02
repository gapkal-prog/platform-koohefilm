<?php
/**
 * Plugin Name:       ManaCore Core
 * Plugin URI:        https://manacore.dev/manacore-core
 * Description:       موتور اصلی سایت فیلم و سریال: نوع‌های محتوا (فیلم، سریال، انیمه، قسمت)، تاکسونومی‌ها، متاباکس‌های کامل، مدیریت لینک دانلود و پخش، REST API و بلوک‌های ویرایشگر.
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
 * @package ManaCore\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'MANACORE_VERSION', '1.0.0' );
define( 'MANACORE_FILE', __FILE__ );
define( 'MANACORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MANACORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader ساده بر پایه‌ی نام کلاس.
 *
 * ManaCore\Core\Post_Types  =>  includes/class-post-types.php
 */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'ManaCore\\Core\\' ) ) {
			return;
		}
		$relative = substr( $class, strlen( 'ManaCore\\Core\\' ) );
		$relative = strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative ) );
		foreach ( array( 'class-', 'trait-', 'interface-' ) as $prefix ) {
			$file = MANACORE_PATH . 'includes/' . $prefix . $relative . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

require_once MANACORE_PATH . 'includes/functions.php';

/**
 * راه‌اندازی افزونه.
 */
function manacore_boot() {
	ManaCore\Core\Post_Types::instance()->hooks();
	ManaCore\Core\Taxonomies::instance()->hooks();
	ManaCore\Core\Meta::instance()->hooks();
	ManaCore\Core\Metaboxes::instance()->hooks();
	ManaCore\Core\Rest_Api::instance()->hooks();
	ManaCore\Core\Blocks::instance()->hooks();
	ManaCore\Core\Query::instance()->hooks();
	ManaCore\Core\Assets::instance()->hooks();
	ManaCore\Core\Settings::instance()->hooks();
	ManaCore\Core\Ratings::instance()->hooks();
	ManaCore\Core\Watchlist::instance()->hooks();		ManaCore\Core\Player::instance()->hooks();
		ManaCore\Core\Mega_Menu::instance()->hooks();
		ManaCore\Core\Seo::instance()->hooks();
}
add_action( 'plugins_loaded', 'manacore_boot', 5 );

/**
 * بارگذاری فایل ترجمه.
 */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'manacore', false, dirname( plugin_basename( MANACORE_FILE ) ) . '/languages' );
	}
);

/**
 * فعال‌سازی: ثبت انواع محتوا، ساخت جدول‌ها و پاک‌سازی بازنویسی لینک‌ها.
 */
register_activation_hook(
	MANACORE_FILE,
	static function () {
		ManaCore\Core\Post_Types::instance()->register();
		ManaCore\Core\Taxonomies::instance()->register();
		ManaCore\Core\Install::create_tables();
		ManaCore\Core\Install::default_options();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	MANACORE_FILE,
	static function () {
		flush_rewrite_rules();
	}
);
