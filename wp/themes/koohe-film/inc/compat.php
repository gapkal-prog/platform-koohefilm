<?php
/**
 * سازگاری با افزونه‌های پرکاربرد.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------------
 * ووکامرس
 * ------------------------------------------------------------------------- */

/**
 * پشتیبانی از ووکامرس.
 */
function koohe_woocommerce_support() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 400,
			'single_image_width'    => 800,
			'product_grid'          => array(
				'default_rows'    => 3,
				'min_rows'        => 1,
				'default_columns' => 4,
				'min_columns'     => 2,
				'max_columns'     => 6,
			),
		)
	);

	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'koohe_woocommerce_support' );

/**
 * پوشش صفحات ووکامرس با ساختار قالب بلوکی.
 */
function koohe_woocommerce_wrappers() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

	add_action(
		'woocommerce_before_main_content',
		function () {
			echo '<div class="koohe-woo wp-block-group alignwide">';
		},
		10
	);

	add_action(
		'woocommerce_after_main_content',
		function () {
			echo '</div>';
		},
		10
	);
}
add_action( 'init', 'koohe_woocommerce_wrappers' );

/**
 * استایل سبک برای ووکامرس.
 */
function koohe_woocommerce_styles() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$file = KOOHE_DIR . 'assets/css/woocommerce.css';
	if ( ! file_exists( $file ) ) {
		return;
	}

	wp_enqueue_style(
		'koohe-woocommerce',
		KOOHE_URI . 'assets/css/woocommerce.css',
		array( 'koohe-film' ),
		koohe_asset_version( 'assets/css/woocommerce.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'koohe_woocommerce_styles', 20 );

/* ---------------------------------------------------------------------------
 * افزونه‌های سئو — جلوگیری از تداخل با ManaCore SEO
 * ------------------------------------------------------------------------- */

/**
 * اگر افزونه‌ی سئو فعال است، عنوان تکراری تولید نشود.
 *
 * @return bool
 */
function koohe_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'SEOPRESS_VERSION' )
		|| class_exists( 'All_in_One_SEO_Pack' )
		|| function_exists( 'aioseo' );
}

/**
 * غیرفعال‌سازی JSON-LD قالب هنگام فعال بودن افزونه‌ی سئو.
 *
 * @param bool $enabled وضعیت.
 * @return bool
 */
function koohe_maybe_disable_schema( $enabled ) {
	return koohe_seo_plugin_active() ? false : $enabled;
}
add_filter( 'manacore_enable_schema', 'koohe_maybe_disable_schema' );

/* ---------------------------------------------------------------------------
 * افزونه‌های صفحه‌ساز و کش
 * ------------------------------------------------------------------------- */

/**
 * پشتیبانی از Elementor / Beaver / Bricks با ثبت مکان‌های قالب.
 */
function koohe_page_builder_support() {
	if ( defined( 'ELEMENTOR_VERSION' ) ) {
		add_theme_support( 'elementor' );
	}
	if ( class_exists( 'FLBuilder' ) ) {
		add_theme_support( 'fl-theme-builder-headers' );
		add_theme_support( 'fl-theme-builder-footers' );
		add_theme_support( 'fl-theme-builder-parts' );
	}
}
add_action( 'after_setup_theme', 'koohe_page_builder_support', 20 );

/**
 * جلوگیری از بهینه‌سازی نادرست اسکریپت حالت رنگ توسط افزونه‌های کش.
 *
 * @param array $excluded فهرست مستثنی‌ها.
 * @return array
 */
function koohe_exclude_inline_script( $excluded ) {
	$excluded   = (array) $excluded;
	$excluded[] = 'koohe-color-mode';
	$excluded[] = 'manacore-color-mode';
	return $excluded;
}
add_filter( 'rocket_exclude_defer_js', 'koohe_exclude_inline_script' );
add_filter( 'litespeed_optimize_js_excludes', 'koohe_exclude_inline_script' );
add_filter( 'perfmatters_delay_js_exclusions', 'koohe_exclude_inline_script' );

/* ---------------------------------------------------------------------------
 * افزونه‌ی چندزبانه
 * ------------------------------------------------------------------------- */

/**
 * ثبت رشته‌های قابل ترجمه برای Polylang/WPML در صورت وجود.
 */
function koohe_register_translations() {
	if ( ! function_exists( 'pll_register_string' ) ) {
		return;
	}

	pll_register_string( 'koohe-toggle', __( 'تغییر حالت رنگ', 'koohe-film' ), 'Koohe Film' );
	pll_register_string( 'koohe-login', __( 'ورود', 'koohe-film' ), 'Koohe Film' );
}
add_action( 'init', 'koohe_register_translations', 30 );

/* ---------------------------------------------------------------------------
 * AMP و دسترس‌پذیری
 * ------------------------------------------------------------------------- */

/**
 * حذف اسکریپت حالت رنگ در حالت AMP.
 */
function koohe_amp_adjust() {
	if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
		remove_action( 'wp_head', 'koohe_color_mode_script', 1 );
	}
}
add_action( 'wp', 'koohe_amp_adjust' );

/**
 * لینک «پرش به محتوا» برای دسترس‌پذیری.
 */
function koohe_skip_link() {
	echo '<a class="skip-link screen-reader-text koohe-skip-link" href="#koohe-main">'
		. esc_html__( 'پرش به محتوای اصلی', 'koohe-film' )
		. '</a>';
}
add_action( 'wp_body_open', 'koohe_skip_link', 1 );
