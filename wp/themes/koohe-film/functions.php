<?php
/**
 * Koohe Film — قالب بلوکی (FSE) برای سایت‌های فیلم، سریال و انیمه.
 *
 * @package KooheFilm
 * @author  ManaCore
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

define( 'KOOHE_VERSION', '1.0.0' );
define( 'KOOHE_DIR', trailingslashit( get_template_directory() ) );
define( 'KOOHE_URI', trailingslashit( get_template_directory_uri() ) );

/**
 * بارگذاری فایل‌های کمکی قالب.
 */
foreach (
	array(
		'setup',
		'assets',
		'patterns',
		'block-styles',
		'template-tags',
		'blocks',
		'compat',
		'customize',
	) as $koohe_part
) {
	$koohe_file = KOOHE_DIR . 'inc/' . $koohe_part . '.php';
	if ( file_exists( $koohe_file ) ) {
		require_once $koohe_file;
	}
}
unset( $koohe_part, $koohe_file );
