<?php
/**
 * دسته‌بندی الگوهای بلوک.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

/**
 * ثبت دسته‌بندی الگوها.
 */
function koohe_pattern_categories() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}

	$categories = array(
		'koohe'         => array(
			'label'       => __( 'کوه فیلم', 'koohe-film' ),
			'description' => __( 'الگوهای اختصاصی قالب کوه فیلم.', 'koohe-film' ),
		),
		'koohe-hero'    => array(
			'label'       => __( 'کوه فیلم — بخش نخست', 'koohe-film' ),
			'description' => __( 'اسلایدرها و بخش‌های معرفی بالای صفحه.', 'koohe-film' ),
		),
		'koohe-listing' => array(
			'label'       => __( 'کوه فیلم — فهرست آثار', 'koohe-film' ),
			'description' => __( 'شبکه‌ها و ردیف‌های نمایش فیلم و سریال.', 'koohe-film' ),
		),
		'koohe-single'  => array(
			'label'       => __( 'کوه فیلم — صفحه‌ی اثر', 'koohe-film' ),
			'description' => __( 'بخش‌های صفحه‌ی تکی فیلم، سریال و انیمه.', 'koohe-film' ),
		),
		'koohe-page'    => array(
			'label'       => __( 'کوه فیلم — برگه‌ها', 'koohe-film' ),
			'description' => __( 'الگوهای آماده‌ی برگه‌ها مانند اشتراک و تماس.', 'koohe-film' ),
		),
	);

	foreach ( $categories as $slug => $args ) {
		register_block_pattern_category( $slug, $args );
	}
}
add_action( 'init', 'koohe_pattern_categories', 9 );
